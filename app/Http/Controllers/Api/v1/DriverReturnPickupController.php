<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\PickupTask;
use App\Models\Shipment;
use App\Services\Return\ReturnShipmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * DriverReturnPickupController
 * 
 * Handles driver-facing return pickup operations using unified architecture.
 * Replaces DriverReversePickupController with simplified logic.
 * 
 * Key Changes:
 * - Uses Shipment model with is_return=true (not ReverseShipment)
 * - Uses PickupTask model (not ReversePickupTask)
 * - No dual tracking or return leg creation
 * - Simplified status flow
 */
class DriverReturnPickupController extends Controller
{
    public function __construct(
        private ReturnShipmentService $returnShipmentService
    ) {
    }

    /**
     * Get driver's assigned return pickup tasks
     * GET /api/v1/driver/return-pickup/my-tasks
     */
    public function myTasks(Request $request): JsonResponse
    {
        $driverId = $request->user()->id;

        $tasks = PickupTask::with([
            'shipment' => function ($query) {
                $query->withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                    ->where('is_return', true)
                    ->with(['consignee', 'returnRequest']);
            },
            'driver',
            'returnRequest.merchant',
        ])
            ->where('driver_id', $driverId)
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return $this->sendResponse($tasks, 'Your return pickup tasks retrieved successfully');
    }

    /**
     * Pickup shipment from customer (with proof)
     * POST /api/v1/driver/return-pickup/pickup
     */
    public function pickupShipment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_no' => 'required|string',
            'proofs' => 'required|array',
            'proofs.*' => 'image|max:10240', // 10MB per image
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->where('tracking_no', $validated['tracking_no'])
                ->where('is_return', true)
                ->firstOrFail();

            // Verify driver owns this shipment
            if ($shipment->driver_id !== $request->user()->id) {
                return $this->sendError('Unauthorized: This shipment is not assigned to you', [], 403);
            }

            // Upload proof photos
            $proofPaths = null;
            if ($request->hasFile('proofs')) {
                $proofPaths = uploadFiles($request->file('proofs'), 'public/return_pickup_proofs');
            }

            // Mark as picked (triggers driver commission credit)
            $this->returnShipmentService->markAsPicked($shipment->id, $proofPaths);
            $shipment->refresh();

            return $this->sendResponse(
                $shipment->load('pickupTask'),
                'Shipment picked up successfully. Commission credited to your account.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to pickup shipment: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Scan shipment at hub
     * POST /api/v1/driver/return-pickup/scan-hub
     */
    public function scanAtHub(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_no' => 'required|string',
            'note' => 'nullable|string|max:500',
            'proof' => 'nullable|file|image|max:10240',
        ]);

        try {
            $trackingNo = trim($validated['tracking_no']);
            $note = $validated['note'] ?? null;

            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->where('tracking_no', $trackingNo)
                ->where('is_return', true)
                ->firstOrFail();

            // Mark as at hub
            $this->returnShipmentService->markAsAtHub($shipment->id, $note);

            Log::info('Return shipment scanned at hub', [
                'tracking_no' => $trackingNo,
                'shipment_id' => $shipment->id,
                'has_note' => !empty($note),
            ]);

            return $this->sendResponse(
                $shipment->fresh(),
                'Return shipment scanned at hub successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to scan return shipment: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Deliver shipment to merchant (triggers merchant fee)
     * POST /api/v1/driver/return-pickup/deliver-to-merchant
     */
    public function deliverToMerchant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
            'delivery_proof' => 'nullable|image|max:10240',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->where('tracking_no', $validated['tracking_no'])
                ->where('is_return', true)
                ->firstOrFail();

            // Mark as delivered to merchant (triggers merchant fee debit)
            $this->returnShipmentService->markAsDeliveredToMerchant($shipment->id);

            return $this->sendResponse(
                $shipment->fresh(),
                'Shipment delivered to merchant successfully. Merchant charged for return fee.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to deliver shipment: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get shipment details by tracking number
     * GET /api/v1/driver/return-pickup/shipment/{tracking_no}
     */
    public function getShipmentDetails(string $tracking_no): JsonResponse
    {
        $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
            ->with([
                'parentShipment',
                'consignee',
                'pickupAddress',
                'deliveryAddress',
                'returnRequest',
                'pickupTask.driver',
            ])
            ->where('tracking_no', $tracking_no)
            ->where('is_return', true)
            ->firstOrFail();

        return $this->sendResponse($shipment, 'Shipment details retrieved successfully');
    }

    /**
     * Send response helper
     */
    protected function sendResponse($data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Send error helper
     */
    protected function sendError(string $message, array $errors = [], int $code = 404): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
