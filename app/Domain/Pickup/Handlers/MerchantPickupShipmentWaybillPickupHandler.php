<?php

namespace App\Domain\Pickup\Handlers;

use App\Domain\Pickup\ShipmentPickupHandlerInterface;
use Illuminate\Http\Request;
use App\Models\MerchantWaybill;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantPickupTask;
use App\Domain\Pickup\IdentifiedPickupTask;
use App\Enums\MerchantPickupTaskStatusEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MerchantPickupShipmentWaybillPickupHandler implements ShipmentPickupHandlerInterface
{
    /**
     * Normalize tracking number for PE/ME/DR prefixes
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

    public function handle(array $input, Request $request): array
    {

        $waybillTrackingNo = $input['waybill_tracking_no'] ?? null;
        $trackingNo = $input['tracking_no'] ?? null;
        $proof = $input['pickup_proof'] ?? null;
        $pickupTaskId = $input['pickup_task_id'] ?? null;
        $merchantId = $input['merchant_id'] ?? null;
        $merchantPickupShipmentId = $input['merchant_pickup_shipment_id'] ?? null; // Specific record to update
        $driverId = Auth::id();

        if ($pickupTaskId == null) {
            return [
                'success' => false,
                'message' => 'Cannot create Unassigned Shipment Request.',
                'status' => 201,
            ];
        }

        // Validate that both waybill_tracking_no and tracking_no are not provided at the same time
        if (!empty($waybillTrackingNo) && !empty($trackingNo)) {
            return [
                'success' => false,
                'message' => 'Cannot provide both waybill_tracking_no and tracking_no. Provide only one.',
                'errors' => ['Cannot provide both waybill_tracking_no and tracking_no'],
                'status' => 422,
            ];
        }

        // Accept proof
        $proofFile = $proof;

        if (!$proofFile || empty($pickupTaskId)) {
            return [
                'success' => false,
                'message' => 'proof and pickup_task_id are required.',
                'errors' => ['Missing required fields'],
                'status' => 422,
            ];
        }

        DB::beginTransaction();
        try {
            $finalTrackingNo = null;
            $waybill = null;
            $waybillSource = null;

            // Case 1: waybill_tracking_no provided - handle merchant waybill or driver waybill
            if (!empty($waybillTrackingNo)) {
                // Normalize tracking for lookup
                $normalizedTracking = $this->normalizeTrackingNo($waybillTrackingNo);

                Log::info('MerchantPickupShipmentWaybillPickup: Searching for waybill', [
                    'original_waybill' => $waybillTrackingNo,
                    'normalized_options' => $normalizedTracking,
                    'pickup_task_id' => $pickupTaskId,
                    'driver_id' => $driverId,
                ]);

                // Get merchant_id from pickup task first (needed for merchant waybill lookup)
                $taskMerchantId = null;
                if ($pickupTaskId) {
                    $tempTask = MerchantPickupTask::find($pickupTaskId);
                    $taskMerchantId = $tempTask?->merchant_id;
                    Log::info('MerchantPickupShipmentWaybillPickup: Pickup task found', [
                        'task_id' => $tempTask?->id,
                        'merchant_id' => $taskMerchantId,
                    ]);
                }

                // Try merchant waybill first
                // If we have merchant_id from task, search with that constraint
                // Otherwise, search any unused merchant waybill with this tracking number
                foreach ($normalizedTracking as $possibleTracking) {
                    Log::info('MerchantPickupShipmentWaybillPickup: Checking merchant waybill', [
                        'tracking_no' => $possibleTracking,
                        'merchant_id' => $taskMerchantId ?? 'any',
                    ]);

                    $query = MerchantWaybill::where('tracking_no', $possibleTracking)
                        ->where('used', false);

                    // If we have merchant_id from task, add that constraint
                    if ($taskMerchantId) {
                        $query->where('merchant_id', $taskMerchantId);
                    }

                    $merchantWaybill = $query->lockForUpdate()->first();

                    if ($merchantWaybill) {
                        $waybill = $merchantWaybill;
                        $finalTrackingNo = $possibleTracking;
                        $waybillSource = 'merchant';
                        Log::info('MerchantPickupShipmentWaybillPickup: Merchant waybill found', [
                            'waybill_id' => $merchantWaybill->id,
                            'tracking_no' => $finalTrackingNo,
                            'merchant_id' => $merchantWaybill->merchant_id,
                        ]);
                        break;
                    }
                }

                // If merchant waybill not found, try driver waybill
                if (!$waybill) {
                    Log::info('MerchantPickupShipmentWaybillPickup: Merchant waybill not found, trying driver waybill');
                    foreach ($normalizedTracking as $possibleTracking) {
                        $driverWaybill = \App\Models\DriverWaybill::where('tracking_no', $possibleTracking)
                            ->where('driver_id', $driverId)
                            ->where('used', false)
                            ->lockForUpdate()
                            ->first();
                        if ($driverWaybill) {
                            $waybill = $driverWaybill;
                            $finalTrackingNo = $possibleTracking;
                            $waybillSource = 'driver';
                            Log::info('MerchantPickupShipmentWaybillPickup: Driver waybill found', [
                                'waybill_id' => $driverWaybill->id,
                                'tracking_no' => $finalTrackingNo,
                            ]);
                            break;
                        }
                    }
                }

                if (!$waybill) {
                    Log::error('MerchantPickupShipmentWaybillPickup: No waybill found', [
                        'waybill_tracking_no' => $waybillTrackingNo,
                        'normalized_options' => $normalizedTracking,
                        'task_merchant_id' => $taskMerchantId,
                        'driver_id' => $driverId,
                    ]);
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Waybill not found for this merchant/driver or already used.',
                        'errors' => ['Invalid waybill_tracking_no for this merchant/driver'],
                        'status' => 404,
                    ];
                }

                // Case 2: tracking_no provided - handle merchant waybill
            } elseif (!empty($trackingNo)) {
                // Normalize tracking for merchant waybill lookup
                $normalizedTracking = $this->normalizeTrackingNo($trackingNo);

                foreach ($normalizedTracking as $possibleTracking) {
                    $waybill = MerchantWaybill::where('tracking_no', $possibleTracking)
                        ->where('used', false)
                        ->lockForUpdate()
                        ->first();
                    if ($waybill) {
                        $finalTrackingNo = $possibleTracking;
                        $waybillSource = 'merchant';
                        break;
                    }
                }


                if (!$waybill) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Unused merchant waybill not found for this tracking_no.',
                        'errors' => ['Unused merchant waybill not found'],
                        'status' => 404,
                    ];
                }
            }
            // Case 3: Neither provided - finalTrackingNo remains null

            $proofPath = uploadFile($proofFile, 'public/unassigned_shipments/proofs', 'public');
            if (!$proofPath) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to upload proof image.',
                    'errors' => ['File upload failed'],
                    'status' => 500,
                ];
            }

            // Mark waybill as used (only if a waybill was found)
            if ($waybill) {
                $waybill->used = true;
                $waybill->save();
            }

            // Get pickup task to determine merchant_id
            $task = null;
            if ($pickupTaskId) {
                $task = MerchantPickupTask::whereKey($pickupTaskId)->lockForUpdate()->first();
                if (!$task) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Pickup task not found.',
                        'errors' => ['Invalid pickup_task_id'],
                        'status' => 404,
                    ];
                }
                $merchantId = $task->merchant_id;
            }

            // Determine merchant_id based on waybill source
            $finalMerchantId = $merchantId;
            if (!$finalMerchantId && $waybillSource === 'merchant' && isset($waybill->merchant_id)) {
                $finalMerchantId = $waybill->merchant_id;
            }
            Log::info("MerchantPickupShipmentWaybillPickupHandler: Looking for existing record", [
                'merchant_pickup_shipment_id' => $merchantPickupShipmentId,
                'shipment_tracking_no' => $finalTrackingNo,
                'pickup_task_id' => $pickupTaskId,
            ]);
             
            // Find existing record to update
            $record = null;
            
            // Priority 1: If specific merchant_pickup_shipment_id is provided, use it (most reliable)
            if ($merchantPickupShipmentId) {
                $record = MerchantPickupShipment::where('id', (int) $merchantPickupShipmentId)
                    ->lockForUpdate()
                    ->first();
                    
                if ($record) {
                    Log::info("MerchantPickupShipmentWaybillPickupHandler: Found record by ID", [
                        'record_id' => $record->id,
                    ]);
                }
            }
            
            // Priority 2: Look by pickup_task_id + tracking_no match
            if (!$record && $pickupTaskId && $finalTrackingNo) {
                $record = MerchantPickupShipment::where('pickup_task_id', (int) $pickupTaskId)
                    ->where('shipment_tracking_no', $finalTrackingNo)
                    ->lockForUpdate()
                    ->first();
                    
                if ($record) {
                    Log::info("MerchantPickupShipmentWaybillPickupHandler: Found record by task+tracking", [
                        'record_id' => $record->id,
                    ]);
                }
            }
            
            if ($record) {
                // Update existing record
                $record->shipment_tracking_no = $finalTrackingNo;
                $record->driver_id = $driverId;
                $record->merchant_id = $finalMerchantId ? (int) $finalMerchantId : $record->merchant_id;
                $record->status = MerchantPickupTaskStatusEnum::PICKED;
                // TODO [PHASE_E]: Remove pickup_proof - orchestrator should set this
                $record->pickup_proof = $proofPath;
                $record->save();
            } else {
                // Create new record
                $record = MerchantPickupShipment::create([
                    'shipment_tracking_no' => $finalTrackingNo,
                    'pickup_task_id' => $pickupTaskId ? (int) $pickupTaskId : null,
                    'driver_id' => $driverId,
                    'merchant_id' => $finalMerchantId ? (int) $finalMerchantId : null,
                    'status' => MerchantPickupTaskStatusEnum::PICKED,
                    'pickup_proof' => $proofPath,
                    'pre_id' => null,
                ]);
            }

            if ($task) {
                new IdentifiedPickupTask($task);
            }
            DB::commit();
            return [
                'success' => true,
                'message' => 'Unassigned shipment waybill pickup recorded.',
                'status' => 201,
                'data' => [
                    'tracking_no' => $finalTrackingNo,
                    'merchant_id' => $record->merchant_id,
                    'pickup_proof' => $proofPath,
                    'pickup_task_id' => $record->pickup_task_id,
                ],
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
}
