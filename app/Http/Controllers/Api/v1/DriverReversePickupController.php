<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ReversePickupRequest;
use App\Services\ReversePickup\ReversePickupTaskService;
use App\Services\ReversePickup\ReverseShipmentService;
use App\Services\ReturnLegService;
use App\Models\ReversePickupTask;
use App\Models\ReversePickupShipment;
use App\Models\ReverseShipment;
use App\Models\User;
use App\Models\Shipment;
use App\Models\ShipmentInformation;
use App\Enums\ShipmentStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DriverReversePickupController extends Controller
{
    public function __construct(
        private ReversePickupTaskService $taskService,
        private ReverseShipmentService $shipmentService
    ) {}

    /**
     * Get driver's assigned reverse pickup tasks
     * GET /api/v1/driver/reverse-pickup/my-tasks
     */

    protected function notifyMerchantPickupAtHub(\App\Models\ReversePickupRequest $reverseRequest): void
    {
        try {
            $merchant = User::with('merchant')->find($reverseRequest->merchant_id);
            if (!$merchant || !$merchant->merchant) {
                Log::warning("merchant {$reverseRequest->merchant_id} does not have merchant profile, skip FCM notification.");
                return;
            }

            // Use MerchantPickupRequestReverseService to send FCM with task count
            $pickupReverseService = resolve(\App\Services\MerchantPickupRequestReverseService::class);
            $pickupReverseService->sendFcmNotification($reverseRequest);

            Log::info("Pickup task notification sent for merchant {$merchant->id}, Reverse Request {$reverseRequest->id}");
        } catch (\Throwable $e) {
            Log::error('FCM notifyMerchantPickupAssigned failed: ' . $e->getMessage());
        }
    }

    public function myTasks(Request $request): JsonResponse
    {
        $driverId = $request->user()->id;

        $tasks = ReversePickupTask::with([
            'merchant',
            'shipments.reverseShipment',
            'reversePickupRequest'
        ])
            ->where('driver_id', $driverId)
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })->when($request->ref, function ($query) use ($request) {
                $query->where('ref', $request->ref);
            })
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return $this->sendResponse($tasks, 'Your reverse pickup tasks retrieved successfully');
    }

    /**
     * Pickup shipment from customer (with proof)
     * POST /api/v1/driver/reverse-pickup/pickup
     */
    public function pickupShipment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reverse_pickup_shipment_tr_no' => 'required|exists:reverse_shipments,tracking_no',
            'reverse_proofs' => 'required|array',
            'reverse_proofs.*' => 'image|max:10240', // 10MB per image
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $reverseShipment = ReverseShipment::where('tracking_no', $validated['reverse_pickup_shipment_tr_no'])->firstOrFail();

            if (!$reverseShipment->reversePickupShipment) {
                return $this->sendError('Shipment not assigned to any task', [], 404);
            }

            // if ($reverseShipment->reversePickupShipment->status === 'picked') {
            //     return $this->sendError('Shipment already picked up', [], 400);
            // }

            // Verify driver owns this task
            if ($reverseShipment->reversePickupShipment->driver_id !== $request->user()->id) {
                return $this->sendError('Unauthorized: This shipment is not assigned to you', [], 403);
            }

            // Upload proof photo
            $proofPaths = null;
            if ($request->hasFile('reverse_proofs')) {
                $proofPaths = uploadFiles($request->file('reverse_proofs'), 'public/reverse_pickup_proofs');
            }

            // Mark as picked (triggers driver commission credit)
            $this->taskService->markAsPicked($reverseShipment->reversePickupShipment->id, $proofPaths);
            $reverseShipment->refresh();

            return $this->sendResponse(
                $reverseShipment->load('reversePickupShipment'),
                'Shipment picked up successfully. Commission credited to your account.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to pickup shipment: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Scan shipment at hub
     * POST /api/v1/driver/reverse-pickup/scan-hub
     */
    public function scanAtHub(Request $request): JsonResponse
    {
        return $this->returnToWarehouse($request);
    }

    /**
     * Unified driver return intake for warehouse scans
     * - ReverseShipment: mark REVERSE_AT_HUB and create return leg
     * - Return leg Shipment: mark inbound for return flow
     */
    public function returnToWarehouse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_no' => 'required|string',
            'note' => 'nullable|string|max:500',
            'proof' => 'nullable|file|image|max:10240',
        ]);

        try {
            $trackingNo = trim($validated['tracking_no']);
            $note = $validated['note'] ?? null;
            $proofPath = null;
            if ($request->hasFile('proof')) {
                $proofPath = uploadFile($request->file('proof'), 'public/return_intake_proofs');
            }

            $reverseShipment = ReverseShipment::where('tracking_no', $trackingNo)->first();
            if ($reverseShipment) {
                $this->taskService->markAsAtHub($reverseShipment, $note, $proofPath);
                $reverseRequest = ReversePickupRequest::find($reverseShipment->reverse_pickup_request_id);
                if ($reverseRequest) {
                    $this->notifyMerchantPickupAtHub($reverseRequest);
                }

                $returnLeg = app(ReturnLegService::class)->createFromReverseShipment($reverseShipment, $request->user(), $note, $proofPath);

                Log::info('Return intake scan processed', [
                    'type' => 'reverse_shipment',
                    'tracking_no' => $trackingNo,
                    'reverse_shipment_id' => $reverseShipment->id,
                    'return_leg_id' => $returnLeg->id ?? null,
                    'has_note' => !empty($note),
                    'has_proof' => !empty($proofPath),
                ]);

                return $this->sendResponse([
                    'type' => 'reverse_shipment',
                    'reverse_shipment' => $reverseShipment->fresh('reversePickupShipment'),
                    'return_leg' => $returnLeg->fresh('shipment_information'),
                ], 'Return shipment scanned at hub successfully');
            }

            $shipment = Shipment::where('tracking_no', $trackingNo)->first();
            if ($shipment) {
                if (($shipment->direction ?? null) !== 'return_to_origin' && !(bool) $shipment->is_return) {
                    return $this->sendError('Shipment is not a return leg.', [], 422);
                }

                $this->markReturnLegAtHub($shipment, $note, $proofPath);

                Log::info('Return intake scan processed', [
                    'type' => 'return_leg',
                    'tracking_no' => $trackingNo,
                    'shipment_id' => $shipment->id,
                    'has_note' => !empty($note),
                    'has_proof' => !empty($proofPath),
                ]);

                return $this->sendResponse([
                    'type' => 'return_leg',
                    'shipment' => $shipment->fresh('shipment_information'),
                ], 'Return leg scanned at hub successfully');
            }

            return $this->sendError('Tracking number not found', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to scan return shipment: ' . $e->getMessage(), [], 500);
        }
    }

    private function markReturnLegAtHub(Shipment $shipment, ?string $note = null, ?string $proofPath = null): void
    {
        $facility = facility();
        if (!$facility && $requestUser = auth()->user()) {
            if ($requestUser->owner_id && $requestUser->owner_type) {
                $facility = (object) [
                    'id' => $requestUser->owner_id,
                    'type' => $requestUser->owner_type,
                ];
            }
        }
        if ($facility && (!$shipment->owner_type || !$shipment->owner_id)) {
            $shipment->owner_type = $facility->type;
            $shipment->owner_id = $facility->id;
            $shipment->facility_type = $facility->type;
            $shipment->facility_id = $facility->id;
        }

        if (!$shipment->shipment_information) {
            ShipmentInformation::create([
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'zone_id' => null,
                'in_warehouse' => true,
                'status' => null,
            ]);
            $shipment->load('shipment_information');
        }

        if (!$shipment->shipment_information->in_warehouse) {
            $shipment->shipment_information->in_warehouse = true;
            $shipment->shipment_information->save();
        }

        if ($shipment->status !== ShipmentStatusEnum::ORDER_SORTED) {
            $inboundDescription = 'Return leg received at hub';
            if ($note) {
                $inboundDescription .= ' - Note: ' . $note;
            }

            shipmentHistory([
                'status' => status(ShipmentStatusEnum::ORDER_INBOUNDED)['label'],
                'description' => $inboundDescription,
                'shipment_id' => $shipment->id,
                'proof' => $proofPath,
            ]);

            shipmentHistory([
                'status' => status(ShipmentStatusEnum::ORDER_SORTED)['label'],
                'description' => 'Return leg ready for return dispatch',
                'shipment_id' => $shipment->id,
            ]);

            updateShipmentStatus($shipment->id, status(ShipmentStatusEnum::ORDER_SORTED)['label']);
        } elseif ($note || $proofPath) {
            $scanDescription = 'Return leg scanned at hub';
            if ($note) {
                $scanDescription .= ' - Note: ' . $note;
            }

            shipmentHistory([
                'status' => status(ShipmentStatusEnum::ORDER_SORTED)['label'],
                'description' => $scanDescription,
                'shipment_id' => $shipment->id,
                'proof' => $proofPath,
            ]);
        }

        $shipment->is_sorted = true;
        $shipment->save();
    }

    /**
     * Deliver shipment to merchant (triggers merchant fee)
     * POST /api/v1/driver/reverse-pickup/deliver-to-merchant
     */
    public function deliverToMerchant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reverse_shipment_id' => 'required|exists:reverse_shipments,id',
            'delivery_proof' => 'nullable|image|max:10240',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            // Mark as returned to merchant (triggers merchant fee debit)
            $this->shipmentService->markAsReturnedToMerchant($validated['reverse_shipment_id']);

            $returnLeg = Shipment::where('parent_reverse_shipment_id', $validated['reverse_shipment_id'])->first();
            if ($returnLeg && strtoupper($returnLeg->status ?? '') !== ShipmentStatusEnum::DELIVERED) {
                shipmentHistory([
                    'status' => status(ShipmentStatusEnum::DELIVERED)['label'],
                    'description' => 'Return leg delivered via reverse pickup delivery',
                    'shipment_id' => $returnLeg->id,
                ]);
                updateShipmentStatus($returnLeg->id, status(ShipmentStatusEnum::DELIVERED)['label']);
                $returnLeg->update(['delivered_at' => now()]);
            }

            return $this->sendResponse(
                [],
                'Shipment delivered to merchant successfully. Merchant charged for return fee.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to deliver shipment: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get shipment details by tracking number
     * GET /api/v1/driver/reverse-pickup/shipment/{tracking_no}
     */
    public function getShipmentDetails(string $tracking_no): JsonResponse
    {
        $reverseShipment = ReverseShipment::with([
            'parentShipment',
            'sender',
            'receiver',
            'senderAddress',
            'receiverAddress',
            'reversePickupRequest',
            'reversePickupShipment.reversePickupTask'
        ])
            ->where('tracking_no', $tracking_no)
            ->firstOrFail();

        return $this->sendResponse($reverseShipment, 'Shipment details retrieved successfully');
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
