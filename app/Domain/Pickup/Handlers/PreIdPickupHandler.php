<?php

namespace App\Domain\Pickup\Handlers;

use App\Domain\Pickup\Concerns\EnsuresPickupTaskAssignment;
use App\Domain\Pickup\IdentifiedPickupTask;
use App\Domain\Pickup\ShipmentPickupHandlerInterface;
use App\Enums\MerchantPickupTaskStatusEnum;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\MerchantPickupShipment;
use App\Models\ShipmentProof;
use App\Models\User;
use App\Models\MerchantPickupTask;
use App\Models\PickupRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domain\Pickup\ShipmentPickupFactory;
use App\Enums\ShipmentStatusEnum;
use App\Models\ShipmentHistory;

class PreIdPickupHandler implements ShipmentPickupHandlerInterface
{
    use EnsuresPickupTaskAssignment;

    public function handle(array $input, Request $request): array
    {
        $pickupTaskId = $input['pickup_task_id'] ?? null;
        $preId = $input['pre_id'] ?? null;
        $driverId = Auth::id();
        $proof = $input['pickup_proof'] ?? null;
        if (empty($preId) || !$proof) {
            return [
                'success' => false,
                'message' => 'pre_id and proof are required.',
                'errors' => ['Missing required fields'],
                'status' => 422,
            ];
        }
        DB::beginTransaction();
        try {
            $shipment = Shipment::where('pre_id', $preId)->lockForUpdate()->first();
            if (!$shipment) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Shipment not found for this pre_id.',
                    'errors' => ['Shipment not found'],
                    'status' => 404,
                ];
            }

            // BYPASS: use pickup_task_id if present
            if ($pickupTaskId) {
                // If mobile sends a pickup_task_id and that task exists, we allow pickup
                // even if the shipment was not pre‑assigned to that task. We just attach
                // (or create) a MerchantPickupShipment row for this shipment under that task.
                $task = MerchantPickupTask::lockForUpdate()->find($pickupTaskId);
                if (!$task) {
                    DB::rollBack();
                    return $this->pickupTaskAssignmentError();
                }

                $pickupAssignment = MerchantPickupShipment::where('pickup_task_id', $pickupTaskId)
                    ->where(function ($q) use ($shipment) {
                        // First, try exact shipment_id match (most reliable)
                        $q->where('shipment_id', $shipment->id);

                        // Only use tracking_no/pre_id as fallback if they're not null
                        if ($shipment->tracking_no) {
                            $q->orWhere('shipment_tracking_no', $shipment->tracking_no);
                        }
                        if ($shipment->pre_id) {
                            $q->orWhere('pre_id', $shipment->pre_id);
                        }
                    })
                    ->first();

                if (!$pickupAssignment) {
                    $pickupAssignment = MerchantPickupShipment::create([
                        'pickup_task_id' => $pickupTaskId,
                        'merchant_id' => $task->merchant_id,
                        'driver_id' => $driverId,
                        'shipment_id' => $shipment->id,
                        'shipment_tracking_no' => $shipment->tracking_no,
                        'pre_id' => $shipment->pre_id,
                        'status' => MerchantPickupTaskStatusEnum::TO_PICKUP,
                    ]);
                }
            } else {
                $pickupAssignment = $this->getPickupTaskAssignment($shipment->tracking_no, $shipment->pre_id);

                if (!$pickupAssignment) {
                    DB::rollBack();
                    return $this->pickupTaskAssignmentError();
                }
            }

            // Check if already picked
            $existingPicked = MerchantPickupShipment::where('pre_id', $preId)
                ->where('status', MerchantPickupTaskStatusEnum::PICKED)
                ->first();
            if ($existingPicked) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Shipment already picked previously.',
                    'errors' => ['Shipment already picked previously.'],
                    'status' => 409,
                ];
            }

            // Call ShipmentPickupService for fee allocation and bonuses (if shipment has tracking_no)
            if (!empty($shipment->tracking_no)) {
                $pickupService = app(\App\Services\ShipmentPickupService::class);
                $serviceResult = $pickupService->handleShipmentPickupByShipment($request, $driverId, $shipment);
                if (!($serviceResult['success'] ?? false)) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => $serviceResult['message'] ?? 'Pickup service validation failed',
                        'errors' => $serviceResult['errors'] ?? [],
                        'status' => $serviceResult['status'] ?? 422,
                    ];
                }
            }

            // NOTE: Proof handling removed - now handled exclusively by PickupOrchestrator
            // This eliminates the dual-handling bug that caused proof loss
            // Resolve merchant_id from shipment
            $merchantUser = User::find($shipment->merchant_id);
            $merchantModel = $merchantUser?->merchant;
            $realMerchantId = $merchantModel?->id;

            // Find or create MerchantPickupShipment (with lock)
            $pickupShipment = MerchantPickupShipment::whereKey($pickupAssignment->id)->lockForUpdate()->first();
            if (!$pickupShipment) {
                DB::rollBack();
                return $this->pickupTaskAssignmentError();
            }
            $proofPath = null;
            if ($request->hasFile('pickup_proof')) {
                $proofPath = uploadFile($request->file('pickup_proof'), 'public/pickup_proofs');
            }
            if (!is_null($pickupShipment->pickup_proof)) {
                $proofPath = $pickupShipment->pickup_proof;
            }
            $pickupShipment->pre_id = $preId;
            if ($shipment->tracking_no) {
                $pickupShipment->shipment_tracking_no = $shipment->tracking_no;
            }
            $pickupShipment->status = MerchantPickupTaskStatusEnum::PICKED;
            $pickupShipment->driver_id = $driverId;
            $pickupShipment->pickup_proof = $proofPath;
            // NOTE: pickup_proof is NOT set here - it's set by PickupOrchestrator.proofHandler
            if ($realMerchantId) {
                $pickupShipment->merchant_id = $realMerchantId;
            }

            // Find and link pickup task
            $task = null;
            if (!empty($pickupShipment->pickup_task_id)) {
                $task = MerchantPickupTask::lockForUpdate()->find($pickupShipment->pickup_task_id);
            } elseif ($realMerchantId) {
                $task = MerchantPickupTask::where('merchant_id', $realMerchantId)
                    ->whereIn('status', [
                        MerchantPickupTaskStatusEnum::CREATED,
                        MerchantPickupTaskStatusEnum::PENDING,
                        MerchantPickupTaskStatusEnum::TO_PICKUP,
                    ])
                    ->lockForUpdate()
                    ->latest()
                    ->first();
            }

            if ($task) {
                $pickupShipment->pickup_task_id = $pickupShipment->pickup_task_id ?: $task->id;
                if (!empty($task->pickup_request_id)) {
                    $pickupShipment->pickup_request_id = $pickupShipment->pickup_request_id ?: $task->pickup_request_id;
                }
            }
            $pickupShipment->save();
            $this->storeShipmetHistory($pickupShipment);
            // Find and link pickup request
            $pickupRequest = null;
            if ($task && !empty($task->pickup_request_id)) {
                $pickupRequest = PickupRequest::lockForUpdate()->find($task->pickup_request_id);
            } elseif (!empty($pickupShipment->pickup_request_id)) {
                $pickupRequest = PickupRequest::lockForUpdate()->find($pickupShipment->pickup_request_id);
            } elseif ($merchantUser) {
                $pickupRequest = PickupRequest::lockForUpdate()
                    ->where('merchant_user_id', $merchantUser->id)
                    ->where(function ($q) {
                        $q->whereIn('status', ['pending', 'assigned', 'in_progress'])
                            ->orWhere(function ($qq) {
                                $qq->where('status', 'completed')
                                    ->whereColumn('actual_count', '<', 'shipments_count');
                            });
                    })
                    ->orderByDesc('scheduled_at')
                    ->first();

                if ($pickupRequest && empty($pickupShipment->pickup_request_id)) {
                    $pickupShipment->pickup_request_id = $pickupRequest->id;
                    $pickupShipment->save();
                }
            }
            // Update task status
            if ($task) {
                new IdentifiedPickupTask($task);
            }

            $warning = null;
            if ($pickupRequest) {
                $actual = MerchantPickupShipment::where('pickup_request_id', $pickupRequest->id)
                    ->where('status', MerchantPickupTaskStatusEnum::PICKED)->count();
                $expected = (int) $pickupRequest->shipments_count;
                $pickupRequest->actual_count = $actual;
                $pickupRequest->counted_by = $driverId;
                $pickupRequest->counted_at = now();
                $diff = $actual - $expected;
                $pickupRequest->discrepancy = $diff === 0 ? 'none' : ($diff < 0 ? 'under' : 'over');
                $shouldComplete = $actual >= $expected;
                if (!$shouldComplete && $pickupRequest->status === 'completed') {
                    $pickupRequest->status = 'in_progress';
                } else {
                    $pickupRequest->status = $shouldComplete ? 'completed' : 'in_progress';
                }
                $pickupRequest->save();

                // Update task based on pickup request completion
                if ($task) {
                    if ($shouldComplete) {
                        $targetStatus = $pickupTaskId
                            ? MerchantPickupTaskStatusEnum::PICKED
                            : MerchantPickupTaskStatusEnum::PICKUP_COMPLETED;

                        if ($task->status !== $targetStatus) {
                            $task->status = $targetStatus;
                            $task->completed_at = now();
                            $task->save();
                        }
                    } else {
                        if ($task->status !== MerchantPickupTaskStatusEnum::TO_PICKUP) {
                            $task->status = MerchantPickupTaskStatusEnum::TO_PICKUP;
                            $task->save();
                        }
                    }
                }
                if ($pickupRequest->discrepancy !== 'none' && !$pickupRequest->discrepancy_notified) {
                    // Get merchant's workspace and notify only users in that workspace
                    $merchantModel = $merchantUser?->merchant;
                    if ($merchantModel && $merchantModel->owner_id && $merchantModel->owner_type) {
                        // Get facility info
                        $facilityName = null;
                        $facilityType = null;
                        try {
                            $facilityModel = $merchantModel->owner_type::find($merchantModel->owner_id);
                            if ($facilityModel) {
                                $facilityName = $facilityModel->name;
                                $facilityType = class_basename($merchantModel->owner_type);
                            }
                        } catch (\Exception $e) {
                            Log::warning('Could not fetch facility name', ['error' => $e->getMessage()]);
                        }

                        notify_workspace_users(
                            $merchantModel->owner_id,
                            $merchantModel->owner_type,
                            ['Pickup Task access'],
                            'Pickup Count Discrepancy',
                            sprintf(
                                'Pickup %s has %s-count (expected %d, actual %d).',
                                $pickupRequest->ref,
                                $pickupRequest->discrepancy,
                                $expected,
                                $actual
                            ),
                            [
                                'type' => 'pickup_discrepancy',
                                'pickup_request_id' => $pickupRequest->id,
                                'ref' => $pickupRequest->ref,
                                'expected' => $expected,
                                'actual' => $actual,
                                'diff' => $diff,
                                'facility_name' => $facilityName,
                                'facility_type' => $facilityType,
                                'facility_id' => $merchantModel->owner_id,
                            ],
                            'pickup_request',
                            false
                        );
                    }
                    $pickupRequest->discrepancy_notified = true;
                    $pickupRequest->save();
                }
                if ($pickupRequest->discrepancy === 'under') {
                    $warning = ['level' => 'red', 'title' => 'Under count (accepted)', 'message' => "Expected {$pickupRequest->shipments_count}, got {$pickupRequest->actual_count}."];
                } elseif ($pickupRequest->discrepancy === 'over') {
                    $warning = ['level' => 'amber', 'title' => 'Over count (accepted)', 'message' => "Expected {$pickupRequest->shipments_count}, got {$pickupRequest->actual_count}."];
                }
            }

            // Add shipment history (only if service didn't add it - i.e., no tracking_no)
            if (empty($shipment->tracking_no)) {
                shipmentHistory([
                    'status' => MerchantPickupTaskStatusEnum::PICKED,
                    'description' => 'Shipment picked from merchant by driver (PRE-ID only)',
                    'shipment_id' => $shipment->id,
                    // proof is added by orchestrator
                ]);
                $shipment->markAsPicked($driverId);
            }

            // Notify merchant/consignee of pickup
            if ($merchantUser) {
                try {
                    $identifier = $shipment->tracking_no ?: $shipment->pre_id;
                    create_notification(
                        $merchantUser,
                        'تم استلام الشحنة',
                        'تم استلام شحنتك رقم ' . $identifier . ' من قبل السائق.',
                        [
                            'type' => 'shipment_picked',
                            'shipment_id' => $shipment->id,
                            'tracking_no' => $shipment->tracking_no,
                            'pre_id' => $shipment->pre_id,
                        ],
                        'shipments',
                        true
                    );
                } catch (\Throwable $notifyErr) {
                    // Log but don't fail
                }
            }

            DB::commit();
            return [
                'success' => true,
                'message' => 'Shipment pickup processed successfully (PRE-ID only).',
                'data' => [
                    'identifier_type' => 'pre_id',
                    'identifier' => $shipment->pre_id,
                    'pickup_updates' => [
                        'merchant_pickup_shipment_status' => $pickupShipment->status,
                        'pickup_request_status' => $pickupRequest->status ?? null,
                        'pickup_request_ref' => $pickupRequest->ref ?? null,
                        'expected_count' => $pickupRequest->shipments_count ?? null,
                        'actual_count' => $pickupRequest->actual_count ?? null,
                        'discrepancy' => $pickupRequest->discrepancy ?? 'none',
                        'warning' => $warning,
                    ]
                ]
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
                'status' => 500,
            ];
        }
    }
    private function storeShipmetHistory($merchantPickupShipment)
    {
        $shipmentStatus = ShipmentStatusEnum::PICKED;
        $history = ShipmentHistory::where('shipment_id', $merchantPickupShipment->shipment_id)
            ->where('name', $shipmentStatus)
            ->first();

        if (empty($history)) {
            shipmentHistory([
                "description" => status($shipmentStatus)['description'] ?? 'Shipment picked from merchant',
                "shipment_id" => $merchantPickupShipment->shipment_id,
                "status" => status($shipmentStatus)['label'] ?? $shipmentStatus,
                "proof" => $merchantPickupShipment->pickup_proof,
            ]);

            Log::info('[MerchantPickupShipmentObserver] Created shipment history with proof', [
                'shipment_id' => $merchantPickupShipment->shipment_id,
                'proof' => $merchantPickupShipment->pickup_proof,
            ]);
        } else {
            if (empty($history->proof)) {
                $history->update([
                    'proof' => $merchantPickupShipment->pickup_proof,
                ]);

                Log::info('[MerchantPickupShipmentObserver] Updated shipment history with proof', [
                    'history_id' => $history->id,
                    'shipment_id' => $merchantPickupShipment->shipment_id,
                    'proof' => $merchantPickupShipment->pickup_proof,
                ]);
            }
        }
    }
}
