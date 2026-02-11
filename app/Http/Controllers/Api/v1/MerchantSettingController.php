<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
/**
 * Controller handling merchant settings management
 * 
 * Manages merchant notification preferences and settings. Features:
 * - Email and WhatsApp notification toggles
 * - Automatic settings creation
 * - Boolean value conversion
 * - Merchant relationship validation
 */
class MerchantSettingController extends Controller
{
    /**
     * Get Merchant Settings
     *
     * @OA\Get(
     *     path="/merchant/settings/{merchantId}",
     *     summary="Get merchant settings",
     *     description="
     * Retrieve settings for a specific merchant including notification preferences.
     * 
     * **Features:**
     * - Notification settings
     * - Default settings creation
     * - Merchant-specific configurations
     * - Email and WhatsApp preferences
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant ID verification
     * ",
     *     operationId="getMerchantSettings",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="merchantId",
     *         in="path",
     *         description="Merchant ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant settings retrieved successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Merchant not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function show($merchantId)
    {
        try {
            $user = User::find($merchantId);

            if (!$user->merchant) {
                return sendResponse("Merchant not found.", [], [], 404);
            }

            $merchant = Merchant::with('settings')->find($user->merchant->id);

            if (!$merchant) {
                return sendResponse("Merchant not found.", [], [], 404);
            }

            // Create default settings if they don't exist
            if (!$merchant->settings) {
                MerchantSetting::create([
                    'merchant_id' => $merchant->id,
                    'notifications' => ['email' => true, 'whatsapp' => true]
                ]);
                $merchant->load('settings');
            }

            return sendResponse("Merchant settings retrieved successfully.", $merchant->settings);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * Update Merchant Settings
     *
     * @OA\Post(
     *     path="/merchant/settings/{merchantId}",
     *     summary="Update merchant settings",
     *     description="
     * Update merchant settings including notification preferences.
     * 
     * **Features:**
     * - Notification preferences update
     * - Boolean value conversion
     * - Default settings creation if needed
     * - Manual validation implementation
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant ID verification
     * ",
     *     operationId="updateMerchantSettings",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="merchantId",
     *         in="path",
     *         description="Merchant ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Settings update data",
     *         @OA\JsonContent(
     *             @OA\Property(property="merchant_id", type="integer"),
     *             @OA\Property(property="notifications", type="object",
     *                 @OA\Property(property="email", type="boolean"),
     *                 @OA\Property(property="whatsapp", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant settings updated successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Merchant not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, $merchantId)
    {
        try {
            // Manual validation using if statements
            if (!$request->has('merchant_id')) {
                return sendResponse("Merchant ID is required.", [], ["merchant_id is missing"], 422);
            }

            if (!$request->has('notifications') || !is_array($request->notifications)) {
                return sendResponse("Notifications must be an array.", [], ["notifications must be array"], 422);
            }

            // Check if merchant exists
            $merchant = Merchant::find($merchantId);
            $user = $merchant->user;

            if (!$user) {
                return sendResponse("Merchant not found.", [], [], 404);
            }

            // Convert and validate boolean values
            $notifications = $request->notifications;

            if (isset($notifications['email'])) {
                $notifications['email'] = $this->convertToBoolean($notifications['email']);
            }

            if (isset($notifications['whatsapp'])) {
                $notifications['whatsapp'] = $this->convertToBoolean($notifications['whatsapp']);
            }

            $settings = $user->merchant->settings;

            if (!$settings) {
                $settings = MerchantSetting::create([
                    'merchant_id' => $merchant->id,
                    'notifications' => $notifications ?? ['email' => true, 'whatsapp' => true]
                ]);
            } else {
                $settings->update([
                    'notifications' => $notifications ?? $settings->notifications
                ]);
            }
            $settings->save();
            return sendResponse("Merchant settings updated successfully.", $settings);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * Convert various boolean representations to actual boolean
     */
    private function convertToBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return false;
    }

    public function toggleCreatedShipmentNotification(Request $request)
    {
        $request->validate([
            'merchant_id' => 'required|exists:users,id',
            'created_shipment_notification' => 'required|boolean'
        ]);

        $user = User::find($request->merchant_id);
        $merchant = $user->merchant;

        $settings = $merchant->settings;

        if (!$settings) {
            $settings = MerchantSetting::create([
                'merchant_id' => $merchant->id,
                'created_shipment_notification' => $request->created_shipment_notification
            ]);
        } else {
            $settings->update([
                'created_shipment_notification' => $request->created_shipment_notification
            ]);
        }

        return sendResponse("Created shipment notification toggled successfully.", $settings);
    }
}
