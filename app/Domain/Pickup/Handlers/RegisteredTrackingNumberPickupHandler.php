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
use App\Models\MerchantWaybill;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Domain\Pickup\ShipmentPickupFactory;

class RegisteredTrackingNumberPickupHandler implements ShipmentPickupHandlerInterface
{
    use EnsuresPickupTaskAssignment;

    public function handle(array $input, Request $request): array
    {
        $pickupTaskId = $input['pickup_task_id'] ?? null;
        $trackingNo = $input['tracking_no'] ?? null;
        $proof = $input['pickup_proof'] ?? null;
        $driverId = Auth::id();

        if (empty($trackingNo) || !$proof) {
            return [
                'success' => false,
                'message' => 'Both tracking number and proof are required.',
                'errors' => ['Missing required fields'],
                'status' => 422,
            ];
        }

        DB::beginTransaction();
        try {
            // FIRST: Check if tracking_no is a valid unused merchant waybill (like original controller)
            $normalizedTracking = $this->normalizeTrackingNo($trackingNo);
            $merchantWaybillFromTracking = null;
            $realMerchantId = null;

            foreach ($normalizedTracking as $possibleTracking) {
                $merchantWaybillFromTracking = MerchantWaybill::where('tracking_no', $possibleTracking)
                    ->where('used', false)
                    ->lockForUpdate()
                    ->first();
                if ($merchantWaybillFromTracking) {
                    $trackingNo = $possibleTracking; // Use the found format
                    $realMerchantId = $merchantWaybillFromTracking->merchant_id;
                    break;
                }
            }

            // SECOND: Check for existing shipment
            $shipment = null;
            foreach ($normalizedTracking as $possibleTracking) {
                $shipment = Shipment::where('tracking_no', $possibleTracking)->lockForUpdate()->first();
                if ($shipment) {
                    $trackingNo = $possibleTracking; // Use the found format
                    break;
                }
            }

            // If we have a merchant waybill but NO shipment, handle as unregistered waybill (like original controller)
            if ($merchantWaybillFromTracking && !$shipment) {
                if (!$request->hasFile('pickup_proof')) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Proof is required when using a merchant waybill without an existing shipment.',
                        'errors' => ['Proof is required for merchant waybill pickup'],
                        'status' => 422,
                    ];
                }

                $proofPath = uploadFile($request->file('pickup_proof'), 'public/unassigned_shipments/proofs');
                if (!$proofPath) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Failed to upload proof image.',
                        'errors' => ['File upload failed'],
                        'status' => 500,
                    ];
                }

                $actualPickupTaskId = $pickupTaskId ?: ($input['pickup_task_id'] ? (int) $input['pickup_task_id'] : null);

                $pickupTask=MerchantPickupTask::find($request->pickup_task_id);

                $merchantId = $realMerchantId ?: ($input['merchant_id'] ? (int) $input['merchant_id'] : null);

                // Create or update MerchantPickupShipment record (Unassigned waybill case)
                Log::info("we are inside RegisteredTrackingNumberPickupHandler and shipment_tracking_no is ".$trackingNo." pickup_task_id is ".$actualPickupTaskId);

                $record = MerchantPickupShipment::updateOrCreate(
                    [
                        'shipment_tracking_no' => $trackingNo,
                        'pickup_task_id' => $actualPickupTaskId,
                    ],
                    [
                        'driver_id' => $driverId,
                        'merchant_id' => $pickupTask->merchant_id,
                        'status' => MerchantPickupTaskStatusEnum::PICKED,
                        // TODO [PHASE_E]: Remove pickup_proof - orchestrator should set this
                        'pickup_proof' => $proofPath,
                        'pre_id' => null,
                    ]
                );

                $merchantWaybillFromTracking->used = true;
                $merchantWaybillFromTracking->save();

                DB::commit();
                return [
                    'success' => true,
                    'message' => 'Merchant waybill pickup recorded.',
                    'status' => 201,
                    'data' => [
                        'tracking_no' => $trackingNo,
                        'merchant_id' => $merchantId,
                        'proof' => $proofPath,
                    ],
                ];
            }

            if (!$shipment) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Shipment not found for this tracking number.',
                    'errors' => ['Shipment not found'],
                    'status' => 404,
                ];
            }

            // BYPASS: use pickup_task_id if present
            if ($pickupTaskId) {
                // If driver passed a pickup_task_id, we should allow pickup even when the
                // shipment was not pre‑assigned to that task. In that case we will:
                //  - Confirm the task exists
                //  - Reuse existing MerchantPickupShipment row for this shipment+task if found
                //  - Otherwise create a new MerchantPickupShipment row linked to that task

                $task = \App\Models\MerchantPickupTask::lockForUpdate()->find($pickupTaskId);
                if (!$task) {
                    DB::rollBack();
                    return $this->pickupTaskAssignmentError();
                }

                $pickupAssignment = MerchantPickupShipment::where('pickup_task_id', $pickupTaskId)
                    ->when($shipment->tracking_no, fn ($q) =>
                        $q->where('shipment_tracking_no', $shipment->tracking_no)
                    )
                    ->when(!$shipment->tracking_no && $shipment->pre_id, fn ($q) =>
                        $q->where('pre_id', $shipment->pre_id)
                    )
                    ->first();

                if (!$pickupAssignment) {
                    $pickupAssignment = MerchantPickupShipment::create([
                        'pickup_task_id' => $task->id,
                        'merchant_id' => $task->merchant_id,
                        'driver_id' => $driverId,
                        'shipment_tracking_no' => $shipment->tracking_no,
                        'pre_id' => $shipment->pre_id,
                        'status' => MerchantPickupTaskStatusEnum::TO_PICKUP,
                    ]);
                }
            } else {
                $pickupAssignment = $this->getPickupTaskAssignment($shipment->tracking_no ?? $trackingNo, $shipment->pre_id);

                if (!$pickupAssignment) {
                    DB::rollBack();
                    return $this->pickupTaskAssignmentError();
                }
            }

            // Check if already picked
            $existingPicked = MerchantPickupShipment::where('shipment_tracking_no', $trackingNo)->where("pickup_task_id",$task->id,)
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

            // Resolve merchant_id from shipment
            $merchantUser = User::find($shipment->merchant_id);
            $merchantModel = $merchantUser?->merchant;
            $realMerchantId = $merchantModel?->id;

            // Call ShipmentPickupService for fee allocation and bonuses
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

            // Ensure shipment status is set to PICKED
            $shipment->refresh();
            if ($shipment->status !== \App\Enums\ShipmentStatusEnum::PICKED) {
                $shipment->markAsPicked($driverId, MerchantPickupTaskStatusEnum::PICKED);
            }

            // Upload the proof image (if not already handled by service)
            $proofPath = null;
            if ($request->hasFile('pickup_proof')) {
                $proofPath = uploadFile($request->file('pickup_proof'), 'public/pickup_proofs');

                // Check if upload was successful before saving to database
                if (!$proofPath) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Failed to upload proof image to S3.',
                        'errors' => ['File upload failed'],
                        'status' => 500,
                    ];
                }

                // Save ShipmentProof if not already created by service
                $existingProof = ShipmentProof::where('shipment_id', $shipment->id)->where('type', 'pickup')->exists();
                if (!$existingProof) {
                    ShipmentProof::create([
                        'shipment_id' => $shipment->id,
                        'type' => 'pickup',
                        'path' => $proofPath,
                        'uploaded_by' => $driverId,
                    ]);
                }
            } else {
                // Use proof from service result if available - prefer 'url' (full URL) for database storage
                $proofPath = $serviceResult['data']['proofs'][0]['url'] ?? $serviceResult['data']['proofs'][0]['path'] ?? null;
            }

            // merchant_id already resolved above

            // Find or create MerchantPickupShipment (with lock)
            $pickupShipment = MerchantPickupShipment::whereKey($pickupAssignment->id)->lockForUpdate()->first();
            if (!$pickupShipment) {
                DB::rollBack();
                return $this->pickupTaskAssignmentError();
            }

            $pickupShipment->shipment_tracking_no = $trackingNo;
            $pickupShipment->pre_id = $shipment->pre_id ?? $pickupShipment->pre_id;
            $pickupShipment->status = MerchantPickupTaskStatusEnum::PICKED;
            $pickupShipment->driver_id = $driverId;
            // TODO [PHASE_E]: Remove - orchestrator should set pickup_proof
            // Only set if not already set (backward compatibility)
            if ($proofPath && empty($pickupShipment->pickup_proof)) {
                $pickupShipment->pickup_proof = $proofPath;
            }
            if ($realMerchantId) {
                $pickupShipment->merchant_id = $task->merchant_id;
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

            // Update pickupRequest actual_count, discrepancy and notifications
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
                        if ($task->status !== MerchantPickupTaskStatusEnum::PICKUP_COMPLETED) {
                            $task->status = MerchantPickupTaskStatusEnum::PICKED;
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

            // Notify merchant/consignee of pickup (history already added by service)
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
                'message' => 'Shipment pickup processed successfully.',
                'data' => [
                    'identifier_type' => 'tracking_no',
                    'identifier' => $shipment->tracking_no,
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

    /**
     * Normalize tracking number to handle PE/ME/DR prefixes
     */
    private function normalizeTrackingNo(string $trackingNo): array
    {
        $prefix = substr($trackingNo, 0, 2);
        $possibleTrackingNos = [$trackingNo];

        if ($prefix === 'PE' && strlen($trackingNo) > 2) {
            $withoutPE = substr($trackingNo, 2);
            $withoutPEPrefix = substr($withoutPE, 0, 2);
            if ($withoutPEPrefix === 'ME' || $withoutPEPrefix === 'DR') {
                $possibleTrackingNos[] = $withoutPE;
            } elseif (strlen($withoutPE) >= 12) {
                $possibleTrackingNos[] = 'ME' . $withoutPE;
                $possibleTrackingNos[] = 'DR' . $withoutPE;
            }
        } elseif ($prefix !== 'ME' && $prefix !== 'DR' && $prefix !== 'PE') {
            $possibleTrackingNos[] = 'ME' . $trackingNo;
            $possibleTrackingNos[] = 'DR' . $trackingNo;
        }

        return $possibleTrackingNos;
    }
}
