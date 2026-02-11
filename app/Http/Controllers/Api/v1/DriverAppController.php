<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Address;
use App\Models\DeliveryException;
use App\Models\OldAddress;
use App\Models\Shipment;
use App\Models\ShipmentAddressRevision;
use App\Models\User;
use App\Services\AddressService;
use App\Services\ShipmentValidationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DriverAppController extends Controller
{
    /**
     * Generate Address Update Token
     *
     * Generate a secure token for updating shipment delivery address.
     * Returns a URL that allows address modification for the specified shipment.
     *
     * @OA\Post(
     *     path="/driver_app/get_address_token",
     *     summary="Generate address update token",
     *     description="Generate secure token for updating shipment delivery address",
     *     operationId="getAddressToken",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_no", type="string", example="PE041225123456", description="Shipment tracking number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token generated successfully"),
     *             @OA\Property(property="url", type="string", example="https://app.com/update-address?token=abc123", description="Address update URL with token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Token generation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function get_address_token(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no'
        ]);

        DB::beginTransaction();
        try {
            $shipment = Shipment::with('consignee')
                ->where('tracking_no', $request->tracking_no)
                ->firstOrFail();

            $addressService = new AddressService();
            $tokenData = $addressService->generateAddressUpdateToken($shipment);

            DB::commit();

            return response()->json([
                'success' => true,
                'url' => $tokenData['url'],
                'message' => 'Token generated successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendResponse("Shipment not found.", [], false, [], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error generating token.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Manually Update Delivery Address
     *
     * Update shipment delivery address manually by driver. Supports both text addresses
     * and Google Maps links with automatic coordinate extraction and geocoding.
     *
     * @OA\Post(
     *     path="/driver_app/manual_update_address",
     *     summary="Update delivery address manually",
     *     description="Update shipment delivery address with text or maps link, includes coordinate extraction and geocoding",
     *     operationId="updateAddressManually",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_no", type="string", example="PE041225123456", description="Shipment tracking number"),
     *             @OA\Property(property="new_address", type="string", example="123 Main St, Riyadh or https://maps.google.com/...", description="New address (text or Google Maps link)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="streetAddress", type="string", example="123 Main St, Riyadh"),
     *                 @OA\Property(property="latitude", type="number", format="float", example=24.7136),
     *                 @OA\Property(property="longitude", type="number", format="float", example=46.6753)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment or consignee not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Address update error or geocoding failure",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function manual_update_address(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
            'new_address' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            /** @var \App\Models\Shipment $shipment */
            $shipment = Shipment::with(['consignee', 'deliveryAddress'])->where('tracking_no', $request->tracking_no)->firstOrFail();

            $validationService = new ShipmentValidationService();
            if (!$validationService->isShipmentOFD($shipment)) {
                return sendResponse("Shipment is not out for delivery.", [], false, ["Shipment is not out for delivery."], 422);
            }
            if (!$validationService->isShipmentAssigned($shipment)) {
                return sendResponse("Shipment is not assigned.", [], false, ["Shipment is not assigned."], 422);
            }
            if ($validationService->isShipmentDelivered($shipment)) {
                return sendResponse("Shipment is already delivered.", [], false, ["Shipment is already delivered."], 422);
            }
            if ($validationService->isShipmentInException($shipment)) {
                return sendResponse("Shipment is already in exception.", [], false, ["Shipment is already in exception."], 422);
            }
            if (!$shipment->consignee) {
                throw new \Exception('No consignee associated with this shipment');
            }
            $consignee = $shipment->consignee;

            $hasPending = ShipmentAddressRevision::where('shipment_id', $shipment->id)
                ->where('approved', false)->where('rejected', false)->exists();

            if ($hasPending) {
                DB::rollBack();
                return sendResponse(
                    'There is already a pending address update request for this shipment.',
                    [],
                    false,
                    ['pending_request_exists'],
                    409
                );
            }

            $currentAddress = $shipment->deliveryAddress;
            if (!$currentAddress) {
                $currentAddress = Address::where('consignee_id', $consignee->id)
                    ->where('is_active', true)
                    ->orderByDesc('id')
                    ->first();
            }
            if (!$currentAddress) {
                $currentAddress = Address::create([
                    'consignee_id' => $consignee->id,
                    'country_id' => $consignee->country_id,
                    'governorate_id' => $consignee->governorate_id,
                    'state_id' => $consignee->state_id,
                    'place_id' => $consignee->place_id,
                    'city_id' => $consignee->city_id,
                    'zipcode' => $consignee->zipcode,
                    'streetAddress' => $consignee->streetAddress,
                    'longitude' => $consignee->longitude,
                    'latitude' => $consignee->latitude,
                    'location_url' => $consignee->location,
                    'approved' => true,
                    'is_active' => true,
                ]);
            }

            $addressService = new AddressService();
            $parsed = $addressService->parseInputAddress($request->input('new_address'));


            $newAddress = Address::create([
                'consignee_id' => $consignee->id,
                'country_id' => $parsed['country_id'] ?? $currentAddress->country_id ?? $consignee->country_id,
                'governorate_id' => $parsed['governorate_id'] ?? $currentAddress->governorate_id ?? $consignee->governorate_id,
                'state_id' => $parsed['state_id'] ?? $currentAddress->state_id ?? $consignee->state_id,
                'place_id' => $parsed['place_id'] ?? $currentAddress->place_id ?? $consignee->place_id,
                'city_id' => $parsed['city_id'] ?? $currentAddress->city_id ?? $consignee->city_id,
                'zipcode' => $parsed['zipcode'] ?? $currentAddress->zipcode ?? $consignee->zipcode,
                'streetAddress' => $parsed['streetAddress'] ?? $currentAddress->streetAddress ?? $consignee->streetAddress,
                'longitude' => $parsed['longitude'] ?? $currentAddress->longitude ?? $consignee->longitude,
                'latitude' => $parsed['latitude'] ?? $currentAddress->latitude ?? $consignee->latitude,
                'location_url' => $parsed['location'] ?? $currentAddress->location_url ?? $consignee->location,
                'approved' => false,
                'is_active' => false,
            ]);

            $revision = ShipmentAddressRevision::create([
                'shipment_id' => $shipment->id,
                'old_address_id' => $currentAddress->id,
                'new_address_id' => $newAddress->id,
                'changed_by' => Auth::id(),
                'reason' => 'Driver requested address change via app',
                'approved' => false,
                'rejected' => false,
            ]);

            $oldAddrPayload = [
                'street' => $currentAddress->streetAddress,
                'latitude' => $currentAddress->latitude,
                'longitude' => $currentAddress->longitude,
                'country_id' => $currentAddress->country_id,
                'state_id' => $currentAddress->state_id,
                'governorate_id' => $currentAddress->governorate_id,
                'place_id' => $currentAddress->place_id,
                'city_id' => $currentAddress->city_id,
                'location' => $currentAddress->location_url,
            ];
            $newAddrPayload = [
                'street' => $newAddress->streetAddress,
                'latitude' => $newAddress->latitude,
                'longitude' => $newAddress->longitude,
                'country_id' => $newAddress->country_id,
                'state_id' => $newAddress->state_id,
                'governorate_id' => $newAddress->governorate_id,
                'place_id' => $newAddress->place_id,
                'city_id' => $newAddress->city_id,
                'location' => $newAddress->location_url,
            ];

            DB::table('shipment_histories')->insert([
                'trackNode' => null,
                'operatorInfo' => 'Driver',
                'operationHub' => null,
                'operationHubType' => 'driver',
                'originActionName' => 'Address Update Request (Driver)',
                'name' => 'ADDRESS_UPDATE',
                'description' => 'Driver requested address update - pending admin approval',
                'type' => 'ADDRESS_UPDATE',
                'fromPkgId' => null,
                'time' => now(),
                'operatorId' => Auth::id(),
                'shipment_id' => $shipment->id,
                'proof' => null,
                'data' => json_encode([
                    'old_address' => $oldAddrPayload,
                    'new_address' => $newAddrPayload,
                    'status' => 'pending_approval',
                    'source' => 'driver_app',
                    'revision_id' => $revision->id,
                ]),
                'c_show' => 1,
                'updated_at' => now(),
                'created_at' => now()
            ]);

            // Notify supervisors across assigned workspaces (determined by Merchant's Workspace)
            $workspaceId = null;
            $workspaceType = null;

            // First, try to get workspace from merchant
            if ($shipment->merchant_id) {
                $merchant = User::find($shipment->merchant_id);
                Log::info('Address Update - Merchant Info', [
                    'merchant_id' => $shipment->merchant_id,
                    'merchant_found' => $merchant ? true : false,
                    'merchant_owner_id' => $merchant->owner_id ?? null,
                    'merchant_owner_type' => $merchant->owner_type ?? null,
                ]);

                if ($merchant && $merchant->owner_id && $merchant->owner_type) {
                    $workspaceId = $merchant->owner_id;
                    $workspaceType = $merchant->owner_type;
                }
            }

            // Fallback to shipment's workspace if merchant workspace not available
            if (!$workspaceId && $shipment->owner_id && $shipment->owner_type) {
                $workspaceId = $shipment->owner_id;
                $workspaceType = $shipment->owner_type;
                Log::info('Address Update - Using Shipment Workspace', [
                    'shipment_owner_id' => $shipment->owner_id,
                    'shipment_owner_type' => $shipment->owner_type,
                ]);
            }

            Log::info('Address Update - Final Workspace', [
                'workspace_id' => $workspaceId,
                'workspace_type' => $workspaceType,
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
            ]);

            // Send notification to supervisors if workspace is determined
            if ($workspaceId && $workspaceType) {
                $driver = Auth::user();
                $driverName = $driver->name ?? 'Driver';

                // Get facility name and type for badge
                $facilityName = null;
                $facilityType = null;
                try {
                    $facilityModel = $workspaceType::find($workspaceId);
                    if ($facilityModel) {
                        $facilityName = $facilityModel->name;
                        $facilityType = class_basename($workspaceType); // Hub, Station, or Branch
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not fetch facility name', ['error' => $e->getMessage()]);
                }

                $notificationCount = notify_workspace_users(
                    $workspaceId,
                    $workspaceType,
                    ['Address Updates access'],
                    '📍 طلب تحديث عنوان جديد',
                    "طلب السائق {$driverName} تحديث عنوان للشحنة رقم {$shipment->tracking_no}. يرجى مراجعة الطلب والموافقة عليه.",
                    [
                        'shipment_id' => $shipment->id,
                        'tracking_no' => $shipment->tracking_no,
                        'revision_id' => $revision->id,
                        'driver_id' => Auth::id(),
                        'driver_name' => $driverName,
                        'old_address' => $oldAddrPayload,
                        'new_address' => $newAddrPayload,
                        'status' => 'pending_approval',
                        'facility_name' => $facilityName,
                        'facility_type' => $facilityType,
                        'facility_id' => $workspaceId,
                    ],
                    'address_update_request',
                    true // Send push notification
                );

                Log::info('Address Update - Notifications Sent', [
                    'notification_count' => $notificationCount,
                ]);
            } else {
                Log::warning('Address Update - No Workspace Found', [
                    'shipment_id' => $shipment->id,
                    'merchant_id' => $shipment->merchant_id,
                ]);
            }

            DB::commit();

            return sendResponse('Address update request submitted. Awaiting admin approval.', [
                'status' => 'pending_approval',
                'revision_id' => $revision->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse('Error submitting address update request', [], false, [$e->getMessage()], 500);
        }
    }

}
