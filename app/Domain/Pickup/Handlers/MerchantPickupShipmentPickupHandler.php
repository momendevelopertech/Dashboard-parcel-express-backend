<?php

namespace App\Domain\Pickup\Handlers;

use App\Domain\Pickup\ShipmentPickupHandlerInterface;
use Illuminate\Http\Request;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantPickupTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domain\Pickup\IdentifiedPickupTask;
use App\Enums\MerchantPickupTaskStatusEnum;

class MerchantPickupShipmentPickupHandler implements ShipmentPickupHandlerInterface
{
    public function handle(array $input, Request $request): array
    {
        $proof = $input['pickup_proof'] ?? null;
        $pickupTaskId = $input['pickup_task_id'] ?? null;
        $trackingNo = $input['tracking_no'] ?? null;
        $waybillTrackingNo = $input['waybill_tracking_no'] ?? null;

        // لازم pickup_task_id في السيناريو ده
        if (!$pickupTaskId) {
            return [
                'success' => false,
                'message' => 'Cannot create Unassigned Shipment Request. pickup_task_id is required.',
                'status' => 422,
            ];
        }

        // Validate that both tracking_no and waybill_tracking_no are not provided at the same time
        if (!empty($trackingNo) && !empty($waybillTrackingNo)) {
            return [
                'success' => false,
                'message' => 'Cannot provide both tracking_no and waybill_tracking_no. Provide only one.',
                'errors' => ['Cannot provide both tracking_no and waybill_tracking_no'],
                'status' => 422,
            ];
        }

        $driverId = Auth::id();

        $proofCount = 0;
        if ($proof)
            $proofCount++;


        if ($proofCount !== 1) {
            return [
                'success' => false,
                'message' => 'Provide a proof',
                'errors' => ['Exactly one file required'],
                'status' => 422,
            ];
        }

        DB::beginTransaction();

        try {
            $file = $proof;
            $proofPath = uploadFile($file, 'public/unassigned_shipments/proofs');

            if (!$proofPath) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to upload proof image.',
                    'errors' => ['File upload failed'],
                    'status' => 500,
                ];
            }

            // نجيب التاسك بالـ pickup_task_id
            $task = MerchantPickupTask::whereKey($pickupTaskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Pickup task not found.',
                    'errors' => ['Invalid pickup_task_id'],
                    'status' => 404,
                ];
            }

            // هنا أهم نقطة 👇
            // ناخد merchant_id من الـ task مباشرة
            $merchantId = $task->merchant_id;

            // Determine which tracking number to store
            // Priority: tracking_no > waybill_tracking_no > null
            $finalTrackingNo = null;
            if (!empty($trackingNo)) {
                $finalTrackingNo = $trackingNo;
            } elseif (!empty($waybillTrackingNo)) {
                $finalTrackingNo = $waybillTrackingNo;
            }

            $record = MerchantPickupShipment::create(
                // [
                //     'shipment_tracking_no' => $finalTrackingNo,
                //     'pickup_task_id' => (int) $pickupTaskId,
                // ],
                // 
                //     'driver_id' => $driverId,
                //     'merchant_id' => $merchantId,          // ✅ من الـ pickup_task
                //     'pickup_proof' => $proofPath,
                //     'status' => MerchantPickupTaskStatusEnum::PICKED, // Status when picked up by driver
                //     'pre_id' => null,
                // 
                [
                    'driver_id' => $driverId,
                    'merchant_id' => $merchantId,          // ✅ من الـ pickup_task
                    'pickup_task_id' => (int) $pickupTaskId,
                    // TODO [PHASE_E]: Remove pickup_proof - orchestrator should set this
                    'pickup_proof' => $proofPath,
                    'status' => MerchantPickupTaskStatusEnum::PICKED, // Status when picked up by driver
                    'shipment_tracking_no' => $finalTrackingNo,
                ]
            );

            // نحدّث حالة التاسك لو محتاجين
            if ($task) {
                new IdentifiedPickupTask($task);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Unassigned shipment proof recorded.',
                'status' => 201,
                'data' => [
                    'pickup_proof' => $proofPath,
                    'merchant_id' => $record->merchant_id,
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
    // public function handle(array $input, Request $request): array
    // {
    //     $proof = $input['proof'] ?? null;
    //     $proof2 = $input['proof_2'] ?? null;
    //     // $merchantId = $input['merchant_id'] ?? null;
    //     $pickupTaskId = $input['pickup_task_id'] ?? null;

    //     if($pickupTaskId == null)
    //     {
    //         return [
    //         'success' => false,
    //         'message' => 'Cannot create Unassigned Shipment Request.',
    //         'status' => 201,
    //         ];
    //     }

    //     $driverId = Auth::id();
    //     $proofCount = 0;
    //     if ($proof) $proofCount++;
    //     if ($proof2) $proofCount++;
    //     if ($proofCount !== 1) {
    //         return [
    //             'success' => false,
    //             'message' => 'Provide only proof_2 or proof, only one of them is required.',
    //             'errors' => ['Exactly one file required'],
    //             'status' => 422,
    //         ];
    //     }
    //     DB::beginTransaction();
    //     try {
    //         $file = $proof ?: $proof2;
    //         $proofPath = uploadFile($file, 'unassigned_shipments/proofs', 'public');
    //         if (!$proofPath) {
    //             DB::rollBack();
    //             return [
    //                 'success' => false,
    //                 'message' => 'Failed to upload proof image.',
    //                 'errors' => ['File upload failed'],
    //                 'status' => 500,
    //             ];
    //         }
    //         $task = null;
    //         if ($pickupTaskId) {
    //             $task = MerchantPickupTask::whereKey($pickupTaskId)->lockForUpdate()->first();
    //             if (!$task) {
    //                 DB::rollBack();
    //                 return [
    //                     'success' => false,
    //                     'message' => 'Pickup task not found.',
    //                     'errors' => ['Invalid pickup_task_id'],
    //                     'status' => 404,
    //                 ];
    //             }
    //             $merchantId = $task->merchant_id;
    //         }

    //         $record = UnassignedShipment::create([
    //             'driver_id' => $driverId,
    //             'merchant_id' => $merchantId ? (int)$merchantId : null,
    //             'pickup_task_id' => $pickupTaskId ? (int)$pickupTaskId : null,
    //             'proof' => $proofPath
    //         ]);

    //         if ($task) {
    //             new IdentifiedPickupTask($task);
    //         }
    //         DB::commit();
    //         return [
    //             'success' => true,
    //             'message' => 'Unassigned shipment proof recorded.',
    //             'status' => 201,
    //             'data' => [
    //                 'proof' => $proofPath,
    //                 'merchant_id' => $record->merchant_id,
    //             ]
    //         ];
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         return [
    //             'success' => false,
    //             'message' => 'An error occurred: ' . $e->getMessage(),
    //             'errors' => [$e->getMessage()],
    //             'status' => 500,
    //         ];
    //     }
    // }
}
