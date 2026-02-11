<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\ShipmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\MerchantPickupShipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PickupExceptionController extends Controller
{
    /**
     * Handle Pickup Exception
     *
     * Process pickup exceptions when shipments cannot be collected from merchants.
     * Creates exception history and updates pickup shipment status.
     *
     * @OA\Post(
     *     path="/driver/handle_pickup_exception",
     *     summary="Handle pickup exception",
     *     description="Process pickup exceptions and update shipment status with exception history",
     *     operationId="handlePickupException",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_tracking_no", type="string", example="PE041225123456", description="Shipment tracking number"),
     *             @OA\Property(property="pickup_exception", type="string", description="Pickup exception type from system exceptions list")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pickup exception handled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pickup exception handled successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid exception type",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function handle_pickup_exception(Request $request)
    {
        $validated = $request->validate([
            'shipment_tracking_no' => 'required|string|exists:shipments,tracking_no',
            'pickup_exception'  => 'required|string|in:' . implode(',', array_keys(pickup_exceptions())),
        ]);

        $shipment = Shipment::where('tracking_no', trim($validated['shipment_tracking_no']))->first();

        $status = $request->pickup_exception;

        $historyData = [
            "status" => pickup_status($status)['name'],
            "description" => "Marked as " . pickup_status($status)['label'] . " by " . Auth::user()->name,
            "shipment_id" => $shipment->id,
        ];

        shipmentHistory($historyData);
        updateShipmentStatus($shipment->id, pickup_status($status)['name']);

        MerchantPickupShipment::where('shipment_tracking_no', $shipment->tracking_no)->update(['status' => 'not_picked']);
        return sendResponse("Pickup exception handled successfully.", []);
    }
}

