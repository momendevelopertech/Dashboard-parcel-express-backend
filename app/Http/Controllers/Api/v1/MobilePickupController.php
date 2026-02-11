<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;


use App\Enums\ShipmentStatusEnum;
use App\Http\Resources\MerchantPickupTaskResource;
use App\Http\Resources\MerchantPickupTaskDetailResource;
use App\Models\DriverBonusesTransaction;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantPickupTask;
use App\Models\DriverBonus;
use App\Models\Shipment;
use App\Models\ShipmentProof;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Driver;
use App\Models\PickupMissedShipmentsTransaction;
use App\Models\PickupRequest;
use App\Services\FeeAllocator;
use App\Notifications\ConsigneePickupNotification;
use App\Services\ShipmentPickupService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Domain\Pickup\ShipmentPickupFactory;
use App\Models\Address;
use App\Models\DriverWaybill;
use App\Models\MerchantWaybill;
use App\Models\ShipmentAddressRevision;

class MobilePickupController extends Controller
{
    /**
     * Get Pickup Tasks (Admin View)
     *
     * Retrieve all pickup tasks with optional search functionality.
     * This method provides an overview of all pickup tasks in the system.
     *
     * @OA\Get(
     *     path="/pickup_tasks",
     *     summary="Get all pickup tasks",
     *     description="Retrieve pickup tasks with search functionality and merchant details",
     *     operationId="getAllPickupTasks",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="Search query for merchant name"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tasks reterived successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="driver_id", type="integer"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $tasks = MerchantPickupTask::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $tasks = $tasks
                ->whereHas('merchant', function ($q) use ($query) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
                })
                ->with("country", "state", "city", "owner")
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $tasks = $tasks->with("country:id,name", "governorate:id,en_name,ar_name,country_id", "state:id,en_name,ar_name,country_id,governorate_id", "city:id,en_name,ar_name,country_id,governorate_id,state_id", "owner")->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Tasks reterived successfully.", new MerchantPickupTaskResource($tasks), []);
    }

    /**
     * Get Driver Pickup Tasks (All Statuses by Default)
     *
     * If no status is provided: Returns ALL pickup tasks assigned to the authenticated driver, no matter their status.
     * If status is provided: Filters pickup tasks by the given status (backward compatible).
     *
     * @OA\Get(
     *     path="/driver/pickup_tasks",
     *     summary="Get driver pickup tasks (all statuses by default)",
     *     description="Retrieve all pickup tasks for authenticated driver, or filter by status.",
     *     operationId="getDriverPickupTasks",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "assigned", "to_pickup", "picked", "completed"}),
     *         description="Optional task status filter (if omitted, returns all pickup tasks)"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pickup tasks retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pickup tasks"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="driver_id", type="integer"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function pickup_tasks(Request $request)
    {
        $status = $this->normalizePickupTaskStatuses($request->query('status'));
        $pickupRef = $request->query('pickup_ref');
        $driverId = Auth::id();
        $shipments = MerchantPickupTask::with([
            'merchant.merchant' => function ($q) {
                // Only select needed fields, EXCLUDE lat/lng!
                $q->select([
                    'id',
                    'country_id',
                    'governorate_id',
                    'state_id',
                    'place_id',
                    'user_id',
                    'address',
                    'country_code',
                    'contact_no',
                    'is_guest',
                    'currency',
                    'facility_to_facility_fees',
                    'owner_type',
                    'owner_id',
                    'created_at',
                    'updated_at',
                    'lat',
                    'lng',
                    'image'
                ]);
            },
            'shipments' => function ($q) {
                $q->select([
                    'id',
                    'pickup_task_id',
                    'shipment_tracking_no',
                    'pre_id',
                    'status',
                    'pickup_proof',
                    'created_at',
                ]);
            },

            'merchant.merchant.governorate:id,en_name,ar_name,country_id',
            'merchant.merchant.state:id,en_name,ar_name,country_id,governorate_id',
            'merchant.merchant.place:id,en_name,ar_name,state_id',
            'shipments.shipment.shipment_items',
            'pickupRequest:id'
        ])
            ->where('driver_id', $driverId)
            // Only filter by status if specified; otherwise return ALL
            ->when($status, function ($query) use ($status) {
                $query->whereIn('status', $status);
            })
            ->when($pickupRef, function ($query) use ($pickupRef) {
                $query->where('ref', 'like', "%{$pickupRef}%");
            })
            ->get();

        // Use MerchantPickupTaskDetailResource to remove geojson fields from mobile responses
        return sendResponse('Pickup tasks', MerchantPickupTaskDetailResource::collection($shipments));
    }

    /**
     * Process Shipment Pickup
     *
     * Mark an shipment as picked up by the driver and update related task status.
     * Automatically completes the pickup task when all shipments are collected.
     *
     * @OA\Post(
     *     path="/driver/shipment_pickup",
     *     summary="Process shipment pickup",
     *     description="Mark shipment as picked and update task status when all shipments collected",
     *     operationId="processShipmentPickup",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_tracking_no", type="string", example="PE041225123456", description="Shipment tracking number to pickup")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment pickup processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment pickup processed successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Database transaction error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */


    /**
     * Check if shipment tracking number exists.
     *
     * @OA\Post(
     *     path="/driver/check-tracking-no",
     *     summary="Validate tracking number",
     *     description="Return true/false if a shipment tracking number exists",
     *     operationId="checkTrackingNumber",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tracking_no"},
     *             @OA\Property(property="tracking_no", type="string", example="PE041225123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tracking number status",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tracking number status"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="exists", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function checkTrackingNo(Request $request)
    {
        $validated = $request->validate([
            'tracking_no' => 'required|string'
        ]);

        $exists = Shipment::where('tracking_no', $validated['tracking_no'])->exists();

        $owner = 'none';

        // Check if it's a merchant waybill
        $merchantWaybillExists = MerchantWaybill::where('tracking_no', $validated['tracking_no'])->exists();
        // Check if it's a driver waybill
        $driverWaybillExists = DriverWaybill::where('tracking_no', $validated['tracking_no'])->exists();

        if ($merchantWaybillExists) {
            $owner = 'merchant';
        } elseif ($driverWaybillExists) {
            $owner = 'driver';
        }

        return sendResponse('Tracking number status', ['exists' => $exists, 'owner' => $owner]);
    }


    private function resolveOwnerFromMerchant(?int $merchantId): array
    {
        if (!$merchantId) {
            return [null, null];
        }

        $merchant = User::find($merchantId);

        if (!$merchant) {
            // fallback: اعتبر اليوزر نفسه هو المالك
            return [User::class, $merchantId];
        }

        // لو عندك على users أعمدة owner_type/owner_id بنستخدمهم
        if (!empty($merchant->owner_type) && !empty($merchant->owner_id)) {
            return [$merchant->owner_type, $merchant->owner_id];
        }


        return [User::class, $merchantId];
    }

    private function normalizePickupTaskStatuses(?string $status): ?array
    {
        if (!$status) {
            return null;
        }

        $status = strtolower($status);

        $map = [
            'created' => ['created'],
            'pending' => ['pending'],
            'assigned' => ['pending'],
            'to_pick' => ['to_pick', 'to_pickup'],
            'to_pickup' => ['to_pickup', 'to_pick'],
            'picked' => ['picked'],
            'not_picked' => ['not_picked'],
            'cancelled' => ['cancelled'],
            'completed' => ['pickup_completed'],
            'pickup_complete' => ['pickup_completed'],
            'pickup_completed' => ['pickup_completed'],
        ];

        return $map[$status] ?? [$status];
    }
    public function shipment_pickup(Request $request)
    {
        $request->validate([
            // Identifiers
            'tracking_no' => 'nullable|string',
            'pre_id' => 'nullable|string',
            'waybill_tracking_no' => 'nullable|string',

            // Require pickup_proof only when pre_id is present
            'pickup_proof' => 'required|file|mimes:jpg,jpeg,png,pdf',

            // IDs
            'merchant_id' => 'nullable|integer|exists:users,id',
            'pickup_task_id' => 'required|integer|exists:merchant_pickup_tasks,id',
        ]);
        $input = [
            'tracking_no' => $request->input('tracking_no', ''),
            'pre_id' => $request->input('pre_id', ''),
            'waybill_tracking_no' => $request->input('waybill_tracking_no', ''),
            'pickup_proof' => $request->file('pickup_proof'),
            'merchant_id' => $request->input('merchant_id'),
            'pickup_task_id' => $request->input('pickup_task_id'),
            'merchant_pickup_shipment_id' => $request->input('merchant_pickup_shipment_id'), // For Case 9: specific record to update
        ];

        try {
            $handler = ShipmentPickupFactory::make($input);

            $result = $handler->handle($input, $request);
        } catch (\InvalidArgumentException $e) {
            return sendResponse('Unable to determine pickup scenario: ' . $e->getMessage(), [], false, [], 422);
        } catch (\Throwable $e) {
            return sendResponse('Internal error: ' . $e->getMessage(), [], false, [], 500);
        }
        $success = $result['success'] ?? false;
        $message = $result['message'] ?? ($success ? 'Picked successfully' : 'Pickup failed');
        $data = $result['data'] ?? [];
        $errors = $result['errors'] ?? [];
        $status = $result['status'] ?? ($success ? 200 : 422);
        $proofUpdated = false;
        if (!empty($input['pre_id']) && $request->hasFile('pickup_proof')) {
            $shipmentForProof = Shipment::where('pre_id', $input['pre_id'])->first();
            if ($shipmentForProof) {
                $proofPath = uploadFile($request->file('pickup_proof'), 'public/pickup_proofs');

                // Only save if upload was successful
                if ($proofPath && !ShipmentProof::where('shipment_id', $shipmentForProof->id)->where('type', 'pickup_2')->exists()) {
                    ShipmentProof::create([
                        'shipment_id' => $shipmentForProof->id,
                        'type' => 'pickup_2',
                        'path' => $proofPath,
                        'uploaded_by' => Auth::id(),
                    ]);
                    $proofUpdated = true;

                    // Also update MerchantPickupShipment.pickup_proof
                    $merchantPickupShipment = MerchantPickupShipment::where('shipment_id', $shipmentForProof->id)
                        ->orWhere('pre_id', $input['pre_id'])
                        ->first();
                    if ($merchantPickupShipment) {
                        // TODO [PHASE_E]: Remove - orchestrator should set pickup_proof
                        if (empty($merchantPickupShipment->pickup_proof)) {
                            $merchantPickupShipment->pickup_proof = $proofPath;
                            $merchantPickupShipment->save();
                        }
                    }
                }
            }
        }

        if (!$success && $proofUpdated) {
            $errorMessages = $result['errors'] ?? [];
            if (in_array('Shipment already picked previously.', $errorMessages)) {
                $success = true;
                $message = 'Proof updated successfully for picked shipment.';
                $status = 200;
                $errors = [];
            }
        }

        // PICKUP_BONUS_UNIFICATION: Create pickup bonus for ALL successful pickups
        // Uses unified ShipmentPickupFactory::addPickupBonus() which:
        // - Creates bonus with status = 'pending'
        // - Is idempotent (checks for existing bonus before creating)
        // - Handles all bonus configuration logic internally
        if ($success) {
            $driverId = Auth::id();
            $shipment = $data['shipment'] ?? null;

            // Resolve shipment if not in handler result
            if (!$shipment) {
                // Try handler-returned tracking_no first (e.g., from waybill handler)
                if (!empty($data['tracking_no'])) {
                    $shipment = Shipment::where('tracking_no', $data['tracking_no'])->first();
                } elseif (!empty($input['tracking_no'])) {
                    $shipment = Shipment::where('tracking_no', $input['tracking_no'])->first();
                } elseif (!empty($input['pre_id'])) {
                    $shipment = Shipment::where('pre_id', $input['pre_id'])->first();
                } elseif (!empty($input['waybill_tracking_no'])) {
                    // For waybill-only scenarios (case 9), waybill becomes tracking_no
                    $shipment = Shipment::where('tracking_no', $input['waybill_tracking_no'])->first();
                }
            }


            if ($shipment && $driverId) {
                $shipment->update(["picked_at" => now()]);

                try {
                    ShipmentPickupFactory::addPickupBonus($shipment, $driverId);
                } catch (\Throwable $e) {
                    // Bonus failure should NOT fail the pickup
                    Log::warning('Pickup bonus creation failed', [
                        'shipment_id' => $shipment->id ?? null,
                        'driver_id' => $driverId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return sendResponse($message, $data, $success, $errors, $status);
    }

    /**
     * Reset shipments to CREATED when pickup attempt is missed.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pickup_missed_list(Request $request)
    {
        $validated = $request->validate([
            'tracking_nos' => ['required', 'array', 'min:1'],
            'tracking_nos.*' => ['string', 'distinct'],
            'note' => ['nullable', 'string', 'max:2000'],
            'pickup_proof' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:5120'],
        ]);

        $identifiers = $validated['tracking_nos'];
        $driverId = Auth::id();
        $note = $validated['note'] ?? null;
        $proofPath = null;
        if ($request->hasFile('pickup_proof')) {
            $proofPath = uploadFile($request->file('pickup_proof'), 'public/missed_proofs');
        }

        $pickupShipments = MerchantPickupShipment::with([
            'shipment' => function ($query) {
                $query->select('id', 'tracking_no', 'pre_id', 'status', 'merchant_id', 'driver_id', 'pickup_request_id');
            }
        ])
            ->where(function ($query) use ($identifiers) {
                $query
                    ->whereIn('shipment_tracking_no', $identifiers)
                    ->orWhereIn('pre_id', $identifiers)
                    ->orWhereHas('shipment', function ($shipmentQuery) use ($identifiers) {
                        $shipmentQuery->whereIn('tracking_no', $identifiers)
                            ->orWhereIn('pre_id', $identifiers);
                    });
            })
            ->lockForUpdate()
            ->get();

        Log::info('pickup_missed_list', ['pickupShipments' => $pickupShipments->toArray()]);

        // ------------------------------------------------

        if ($pickupShipments->isEmpty()) {
            return sendResponse("No shipments found for provided tracking numbers.", [], false, ["Shipments not found"], 404);
        }

        if ($pickupShipments->first()->status !== ShipmentStatusEnum::PICKUP_TO_PICKUP) {
            return sendResponse(
                "This Order isnt Marked for Shipment to Reset it's Pickup status",
                [],
                false,
                ["Invalid shipment status"],
                422
            );
        }

        foreach ($pickupShipments as $pickupShipment) {
            $pickupShipment->status = ShipmentStatusEnum::PICKUP_CANCELLED;
            $pickupShipment->save();

            // Find Shipment by shipment_id to be 100% sure
            $shipment = $pickupShipment->shipment_id
                ? \App\Models\Shipment::find($pickupShipment->shipment_id)
                : $pickupShipment->shipment;
            if ($shipment) {
                $shipment->update([
                    'pickup_request_id' => null,
                    'status' => ShipmentStatusEnum::CREATED,
                ]);
            }
        }

        // Missing Shipments Transaction ------------------------------------------------------------

        // add creation of missed transaction for not found items
        $firstPickup = $pickupShipments->first();
        $merchantId = $firstPickup ? ($firstPickup->merchant_id ?? optional($firstPickup->shipment)->merchant_id) : null;
        $driverIdForMissed = $driverId ?? optional($firstPickup)->driver_id;
        foreach ($identifiers as $identifier) {
            PickupMissedShipmentsTransaction::create([
                'shipment_id' => $shipment->id,
                'pickup_task_id' => $firstPickup->pickup_task_id,
                'merchant_id' => $merchantId,
                'driver_id' => $driverIdForMissed,
                'note' => $note ?? null,
                'proof_path' => $proofPath ?? null,
            ]);
        }

        return sendResponse("Shipments reset to CREATED.", [
            'updated' => $pickupShipments->map(function ($pickupShipment) {
                return $pickupShipment->shipment_tracking_no ?? $pickupShipment->pre_id;
            })->values(),
            'note' => $note,
            'proof_path' => $proofPath,
            'proof_url' => $proofPath ? asset('storage/' . $proofPath) : null,
        ]);
    }


    /**
     * Get Pickup Task Details
     *
     * Retrieve detailed information about a specific pickup task including
     * merchant details, location information, and shipment counts.
     *
     * @OA\Get(
     *     path="/driver/pickup_tasks/edit/{id}",
     *     summary="Get pickup task details",
     *     description="Retrieve detailed pickup task information with merchant and shipment details",
     *     operationId="getPickupTaskDetails",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Pickup task ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task"),
     *             @OA\Property(
     *           property="data",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="status", type="string"),
     *           @OA\Property(property="driver_id", type="integer"),
     *           @OA\Property(property="picked_shipments_count", type="integer"),
     *           @OA\Property(property="to_pickup_shipments_count", type="integer"),
     *           @OA\Property(property="created_at", type="string", format="date-time")
     *       )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function edit($id)
    {
        $user = MerchantPickupTask::with('merchant:id,name', 'merchant.merchant.governorate:id,en_name,ar_name', 'merchant.merchant.state:id,en_name,ar_name', 'merchant.merchant.place:id,en_name,ar_name', 'shipments.shipment')
            ->withCount('picked_shipments', 'to_pickup_shipments')
            ->find($id);

        return sendResponse("Task", new MerchantPickupTaskResource($user));
    }
    /**
     * Revert shipment pickup status.
     *
     * @OA\Post(
     *     path="/driver/shipment_unpickup",
     *     summary="Unpickup a shipment",
     *     description="Revert a shipment's status from PICKED to CREATED/TO_PICKUP",
     *     operationId="shipmentUnpickup",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pickup_task_id"},
     *             @OA\Property(property="pickup_task_id", type="integer"),
     *             @OA\Property(property="tracking_no", type="string"),
     *             @OA\Property(property="pre_id", type="string"),
     *             @OA\Property(property="waybill_tracking_no", type="string"),
     *             @OA\Property(property="proof_2", type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment unpicked successfully"
     *     )
     * )
     */
    public function shipment_unpickup(Request $request)
    {
        $validated = $request->validate([
            'pickup_task_id' => 'nullable|integer|exists:merchant_pickup_tasks,id',
            'shipment_id' => 'nullable|integer|exists:shipments,id',
            'unassigned_shipment_id' => 'nullable|integer|exists:merchant_pickup_shipments,id', // Map to unified table
            'merchant_pickup_shipment_id' => 'nullable|integer|exists:merchant_pickup_shipments,id',
            'tracking_no' => 'nullable|string',
            'pre_id' => 'nullable|string',
            'waybill_tracking_no' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $unassignedShipmentId = $validated['unassigned_shipment_id'] ?? null;
            $merchantPickupShipmentId = $validated['merchant_pickup_shipment_id'] ?? null;
            $shipmentId = $validated['shipment_id'] ?? null;
            $pickupTaskId = $validated['pickup_task_id'] ?? null;

            // --- CASE 1: Unified Pickup Shipment ID (or old Unassigned ID) ---
            $targetPickupShipmentId = $merchantPickupShipmentId ?: $unassignedShipmentId;

            if ($targetPickupShipmentId) {
                $pickupShipment = \App\Models\MerchantPickupShipment::lockForUpdate()->find($targetPickupShipmentId);

                if (!$pickupShipment) {
                    DB::rollBack();
                    return sendResponse('Pickup shipment record not found.', [], false, [], 404);
                }

                $pickupTaskId = $pickupTaskId ?: $pickupShipment->pickup_task_id;

                // If it's linked to a shipment, we need to revert the shipment status too
                if ($pickupShipment->shipment_id) {
                    $shipment = Shipment::find($pickupShipment->shipment_id);
                    if ($shipment && $shipment->status === ShipmentStatusEnum::PICKED) {
                        $shipment->status = ShipmentStatusEnum::CREATED;
                        $shipment->save();

                        // Remove Bonuses
                        DriverBonusesTransaction::where('shipment_id', $shipment->id)
                            ->where('driver_id', Auth::id())
                            ->where('action', 'pickup')
                            ->where('status', 'pending')
                            ->delete();
                    }
                }

                // If it was "unassigned" (no shipment_id), we might want to delete it or revert status
                if (!$pickupShipment->shipment_id) {
                    $pickupShipment->delete();
                } else {
                    $pickupShipment->status = 'to_pickup';
                    $pickupShipment->save();
                }

                // Update Task Status
                if ($pickupTaskId) {
                    $this->revertTaskStatus($pickupTaskId);
                }

                DB::commit();
                return sendResponse('Shipment unpicked successfully.', []);
            }

            // --- CASE 2: Identified Shipment ID Provided ---
            if ($shipmentId) {
                $shipment = Shipment::lockForUpdate()->find($shipmentId);

                if (!$shipment) {
                    DB::rollBack();
                    return sendResponse('Shipment not found.', [], false, [], 404);
                }

                $pickupTaskId = $this->processIdentifiedUnpickup($shipment, $pickupTaskId);
                if (!$pickupTaskId) {
                    DB::rollBack();
                    return sendResponse('Failed to process unpickup.', [], false, [], 422);
                }

                DB::commit();
                return sendResponse('Shipment unpicked successfully.', []);
            }

            // --- CASE 3: Identifier Search (Tracking/PreID/Waybill) ---
            $trackingNo = $validated['tracking_no'] ?? null;
            $preId = $validated['pre_id'] ?? null;
            $waybillTrackingNo = $validated['waybill_tracking_no'] ?? null;

            if ($trackingNo || $preId || $waybillTrackingNo) {
                $shipment = $this->findShipmentByIdentifiers($trackingNo, $preId, $waybillTrackingNo);

                if (!$shipment) {
                    DB::rollBack();
                    return sendResponse('Shipment not found with provided identifiers.', [], false, [], 404);
                }

                $pickupTaskId = $this->processIdentifiedUnpickup($shipment, $pickupTaskId);
                if (!$pickupTaskId) { // Error handled inside or returns false/null
                    DB::rollBack();
                    return sendResponse('Unpickup failed or valid pickup task not found.', [], false, [], 422);
                }

                DB::commit();
                return sendResponse('Shipment unpicked successfully.', []);
            }

            DB::rollBack();
            return sendResponse('Please provide a valid identifier (shipment_id, unassigned_shipment_id, tracking_no, etc).', [], false, [], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse('Error: ' . $e->getMessage(), [], false, [], 500);
        }
    }

    private function findShipmentByIdentifiers($trackingNo, $preId, $waybillTrackingNo)
    {
        $query = Shipment::query();

        if ($trackingNo) {
            $query->where('tracking_no', $trackingNo);
        } elseif ($preId) {
            $query->where('pre_id', $preId);
        } elseif ($waybillTrackingNo) {
            $merchantWaybill = MerchantWaybill::where('tracking_no', $waybillTrackingNo)->first();
            if ($merchantWaybill) {
                $query->where('tracking_no', $merchantWaybill->tracking_no);
            } else {
                $driverWaybill = DriverWaybill::where('tracking_no', $waybillTrackingNo)->first();
                if ($driverWaybill) {
                    $query->where('tracking_no', $driverWaybill->tracking_no);
                } else {
                    $query->where('tracking_no', $waybillTrackingNo);
                }
            }
        }
        return $query->lockForUpdate()->first();
    }

    private function processIdentifiedUnpickup(Shipment $shipment, $pickupTaskIdRequest = null)
    {
        // 1. Verify Status
        if ($shipment->status !== ShipmentStatusEnum::PICKED) {
            // throw new \Exception("Shipment is not in PICKED status."); // Or handle gracefully
            return false;
        }

        // 2. Find and Revert MerchantPickupShipment
        $pickupShipmentQuery = MerchantPickupShipment::where(function ($q) use ($shipment) {
            $q->where('shipment_tracking_no', $shipment->tracking_no)
                ->orWhere('pre_id', $shipment->pre_id ?? 'xxx');
        });

        if ($pickupTaskIdRequest) {
            $pickupShipmentQuery->where('pickup_task_id', $pickupTaskIdRequest);
        }

        $pickupShipment = $pickupShipmentQuery->first();

        if (!$pickupShipment) {
            return false;
        }

        $pickupTaskId = $pickupShipment->pickup_task_id;

        // Revert Shipment
        $shipment->status = ShipmentStatusEnum::CREATED;
        $shipment->save();

        // Revert Pickup Shipment
        $pickupShipment->status = 'to_pickup';
        $pickupShipment->save();

        // Remove Bonuses
        DriverBonusesTransaction::where('shipment_id', $shipment->id)
            ->where('driver_id', Auth::id())
            ->where('action', 'pickup')
            ->where('status', 'pending')
            ->delete();

        // Update Task
        $this->revertTaskStatus($pickupTaskId);

        return $pickupTaskId;
    }

    private function revertTaskStatus($pickupTaskId)
    {
        if (!$pickupTaskId) return;

        $task = MerchantPickupTask::find($pickupTaskId);
        if ($task && in_array($task->status, ['completed', 'pickup_completed'])) {
            $task->status = 'to_pickup';
            $task->save();
        }
    }
}
