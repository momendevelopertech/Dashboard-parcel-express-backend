<?php

namespace App\Services;

use App\Enums\MerchantPickupTaskStatusEnum;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantWaybill;
use App\Models\Driver;
use App\Models\DriverBonus;
use App\Models\Shipment;
use App\Models\ShipmentProof;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ConsigneePickupNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;

class ShipmentPickupService
{
    public function handleShipmentPickup(Request $request, $tracking, $driverId, $shipment = null)
    {
        $uploadedPath = null;
        $file = $request->file('pickup_proof');
        $createdProof = null;

        $cleanupFile = function () use (&$uploadedPath) {
            if ($uploadedPath) {
                try {
                    Storage::disk(config('filesystems.default'))->delete($uploadedPath);
                } catch (\Throwable $ignored) {
                }
            }
        };

        try {
            if ($file) {
                $validator = Validator::make($request->all(), [
                    'pickup_proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
                ]);
                if ($validator->fails()) {
                    throw new Exception("Validation failed for pickup_proof: " . implode(', ', $validator->errors()->all()));
                }
                $uploadedPath = uploadFile($file, 'public/pickup/proofs');
                if (!$uploadedPath) {
                    throw new Exception("Failed to upload proof image to S3.");
                }
            }

            if (!$shipment) {
                $shipment = Shipment::where('tracking_no', $tracking)->lockForUpdate()->first();
            }

            // 3) هات Waybill لو موجود (اختياري)
            $waybill = MerchantWaybill::where('tracking_no', $tracking)->lockForUpdate()->first();

            // 4) PickupShipment (اختياري بردو، ممكن يساعدنا في بيانات الإنشاء لو مفيش Shipment)
            $pickupShipment = MerchantPickupShipment::where('shipment_tracking_no', $tracking)
                ->where(function ($q) use ($driverId) {
                    // مش شرط السواق نفس الشخص علشان نقدر نقرأ البيانات—لو عايز تقفّلها على السواق الحالي شيل الـ orWhereNull
                    $q->where('driver_id', $driverId)->orWhereNull('driver_id');
                })
                ->first();

            // 5) لازم يكون فيه Waybill أو Shipment على الأقل عشان نكمل
            if (!$waybill && !$shipment) {
                $cleanupFile();
                throw new Exception("No waybill or shipment found for this tracking number.");
            }

            // 6) Resolve merchant_id بالأولوية: Shipment -> PickupShipment -> Waybill
            $resolvedMerchantId = null;
            if ($shipment && $shipment->merchant_id) {
                $resolvedMerchantId = $shipment->merchant_id;
            } elseif ($pickupShipment && $pickupShipment->merchant_id) {
                $resolvedMerchantId = $pickupShipment->merchant_id;
            } elseif ($waybill && $waybill->merchant_id) {
                $resolvedMerchantId = $waybill->merchant_id;
            }

            if (!$resolvedMerchantId) {
                $cleanupFile();
                throw new Exception("Unable to determine the merchant for this tracking number.");
            }

            // 7) Driver & Merchant facilities check (Authorization)
            $driver = User::find($driverId);
            if (!$driver) {
                $cleanupFile();
                throw new Exception("Driver not found for driver_id: {$driverId}");
            }

            $merchantUser = User::find($resolvedMerchantId);
            if (!$merchantUser || !$merchantUser->owner_type || !$merchantUser->owner_id) {
                $cleanupFile();
                throw new Exception("Merchant record is missing facility ownership info.");
            }

            if (
                (string) $driver->owner_type !== (string) $merchantUser->owner_type ||
                (string) $driver->owner_id !== (string) $merchantUser->owner_id
            ) {
                $cleanupFile();
                throw new Exception("You are not authorized to pick up shipments for this merchant's facility.");
            }

            // 8) لو فيه Waybill متعلّق بعميل تاني والـ Shipment موجود وصحيح: ندي الأولوية للـ Shipment ونتجاهل الـ Waybill
            if ($waybill && $waybill->merchant_id && (int) $waybill->merchant_id !== (int) $resolvedMerchantId) {
                // تجاهل الـ waybill بدل ما نرمي Error، لأن عندنا Shipment صحيح
                $waybill = null;
            }

            // 9) لو مفيش Shipment هننشئه من pickupShipment/request/waybill (حسب المتاح)
            if (!$shipment) {
                $source = $pickupShipment ?? $request;
                $shipmentData = [
                    'tracking_no' => $tracking,
                    'merchant_id' => $resolvedMerchantId,
                    'driver_id' => $driverId,
                    'owner_type' => $merchantUser->owner_type,
                    'owner_id' => $merchantUser->owner_id,
                    'consignee_id' => data_get($source, 'consignee_id'),
                    'customer_id' => data_get($source, 'customer_id'),
                    'shipper_id' => data_get($source, 'shipper_id'),
                    'shipment_type_id' => data_get($source, 'shipment_type_id', 1),
                    'value' => data_get($source, 'value'),
                    'amount' => data_get($source, 'amount'),
                    'delivery_fee' => data_get($source, 'delivery_fee'),
                    'payment_type' => data_get($source, 'payment_type'),
                    'pickup_address' => data_get($source, 'pickup_address'),
                    'country_id' => data_get($source, 'country_id'),
                    'governorate_id' => data_get($source, 'governorate_id'),
                    'state_id' => data_get($source, 'state_id'),
                    'place_id' => data_get($source, 'place_id'),
                    'city_id' => data_get($source, 'city_id'),
                    'zipcode' => data_get($source, 'zipcode'),
                    'streetAddress' => data_get($source, 'streetAddress'),
                    'location_url' => data_get($source, 'location_url'),
                    'longitude' => data_get($source, 'longitude'),
                    'latitude' => data_get($source, 'latitude'),
                    'customer_name' => data_get($source, 'customer_name'),
                    'customer_phone' => data_get($source, 'customer_phone'),
                    'customer_id_card' => data_get($source, 'customer_id_card'),
                    'fee_payer' => data_get($source, 'fee_payer'),
                    'created_by' => $driverId,
                    'is_walkin' => false,
                ];

                try {
                    $shipment = Shipment::create($shipmentData);
                } catch (\Illuminate\Database\QueryException $qe) {
                    if ($qe->getCode() === '23000') {
                        // حد سبقنا وأنشأه
                        $shipment = Shipment::where('tracking_no', $tracking)->lockForUpdate()->first();
                    } else {
                        throw $qe;
                    }
                }
            }

            // 10) لو فيه Waybill ولسه مش مستخدم، علّمه مستخدم
            if ($waybill && !$waybill->used) {
                $waybill->used = true;
                $waybill->save();
            }

            // 11) Bonus & fees allocation
            $currentDriver = \App\Models\Driver::where('user_id', $driverId)->first();
            if ($shipment && $currentDriver) {
                $facilityType = $driver->owner_type;
                $facilityId = $driver->owner_id;
                $stateId = $shipment->state_id ?? optional($shipment->consignee)->state_id;
                if (!isset($shipment->delivery_fee)) {
                    $shipment->delivery_fee = (float) optional($shipment->shipment_delivery)->delivery_fee ?? 0.0;
                }

                app(\App\Services\FeeAllocator::class)->allocateForShipment(
                    $shipment,
                    [
                        'pickup_driver_id' => $currentDriver->id,
                        'delivery_driver_id' => null,
                        'first_warehouse_id' => null,
                    ],
                    [],
                    false,
                    $stateId,
                    false,
                    true,
                    true
                );

                $bonusRow = \App\Models\DriverBonus::query()
                    ->where('driver_id', $currentDriver->user_id) // لو عندك الـ schema بتستخدم drivers.id بدّلها
                    ->when(!empty($stateId), fn($q) => $q->where('state_id', $stateId))
                    ->where(function ($q) use ($facilityType, $facilityId) {
                        $q->where(function ($q1) use ($facilityType, $facilityId) {
                            $q1->where('owner_type', $facilityType)->where('owner_id', $facilityId);
                        })->orWhere(function ($q2) {
                            $q2->whereNull('owner_type')->whereNull('owner_id');
                        });
                    })
                    ->first();

                // $pickupBonus = (float) ($bonusRow->pickup_bonus ?? 0);
                // if ($pickupBonus > 0) {
                //     $ref = 'BON-PU-' . $shipment->tracking_no;
                //     $exists = \App\Models\Transaction::where('reference', $ref)
                //         ->where('type', 'bonus_credit')
                //         ->exists();

                //     if (!$exists) {
                //         \App\Models\Transaction::create([
                //             'from_id' => $facilityId,
                //             'from_type' => $facilityType,
                //             'to_id' => $currentDriver->user_id,
                //             'to_type' => User::class,
                //             'shipment_id' => $shipment->id,
                //             'amount' => $pickupBonus,
                //             'type' => 'bonus_credit',
                //             'reference' => $ref,
                //             'description' => "Pickup bonus for {$shipment->tracking_no}",
                //             'created_by' => $driverId,
                //             'warehouse_id' => $facilityId,
                //         ]);
                //     }
                // }
            }

            // 12) Proof
            if ($uploadedPath && $shipment) {
                $createdProof = \App\Models\ShipmentProof::create([
                    'shipment_id' => $shipment->id,
                    'type' => 'pickup',
                    'path' => $uploadedPath,
                    'uploaded_by' => $driverId,
                ]);
            }

            // 13) إشعار المستلم (لو موجود)
            if ($shipment && $shipment->consignee) {
                $shipment->consignee->notify(new \App\Notifications\ConsigneePickupNotification($shipment));
            }

            // 14) تحديث الحالة والتاريخ
            $status = "PICKED";
            if ($shipment) {
                $historyData = [
                    "status" => status($status)['name'],
                    "description" => status($status)['description'],
                    "shipment_id" => $shipment->id,
                    "proof" => $uploadedPath,
                ];
                shipmentHistory($historyData);
                $shipment->markAsPicked($driverId, MerchantPickupTaskStatusEnum::PICKED);
            }

            // 15) Response proofs
            $proofsResponse = [];
            if ($createdProof) {
                // If path is already a full URL, use it directly; otherwise construct URL
                $proofUrl = (str_starts_with($createdProof->path, 'http://') || str_starts_with($createdProof->path, 'https://'))
                    ? $createdProof->path
                    : Storage::url($createdProof->path);
                $proofsResponse[] = [
                    'id' => $createdProof->id,
                    'path' => $createdProof->path,
                    'url' => $proofUrl,
                    'type' => $createdProof->type,
                ];
            } elseif ($shipment) {
                $proofsResponse = $shipment->proofs()->where('type', 'pickup')->get()->map(function($p) {
                    // If path is already a full URL, use it directly; otherwise construct URL
                    $proofUrl = (str_starts_with($p->path, 'http://') || str_starts_with($p->path, 'https://'))
                        ? $p->path
                        : Storage::url($p->path);
                    return [
                        'id' => $p->id,
                        'path' => $p->path,
                        'url' => $proofUrl,
                        'type' => $p->type,
                    ];
                })->toArray();
            }

            return [
                'success' => true,
                'message' => "Shipment pickup processed successfully.",
                'data' => [
                    'shipment_id' => $shipment?->id,
                    'proofs' => $proofsResponse,
                    'used_waybill' => (bool) ($waybill?->used),
                ],
            ];
        } catch (\Throwable $e) {
            $cleanupFile();
            Log::error("Shipment pickup failed for tracking $tracking: " . $e->getMessage(), ['exception' => $e]);
            return [
                'success' => false,
                'message' => "An error occurred.",
                'errors' => [$e->getMessage()],
                'status' => $e instanceof Exception ? ($e->getCode() ?: 500) : 500,
            ];
        }
    }
    protected function applyFeesAndBonus(Shipment $shipment, int $driverId, \App\Models\User $driver): void
    {
        $currentDriver = \App\Models\Driver::where('user_id', $driverId)->first();
        if (!$currentDriver)
            return;

        $facilityType = $driver->owner_type;
        $facilityId = $driver->owner_id;
        $stateId = $shipment->state_id ?? optional($shipment->consignee)->state_id;

        if (!isset($shipment->delivery_fee)) {
            $shipment->delivery_fee = (float) optional($shipment->shipment_delivery)->delivery_fee ?? 0.0;
        }

        // ✅ مهم: متعملش allocation إلا لو فيه tracking_no
        if (!empty($shipment->tracking_no)) {
            app(\App\Services\FeeAllocator::class)->allocateForShipment(
                $shipment,
                [
                    'pickup_driver_id' => $currentDriver->id,
                    'delivery_driver_id' => null,
                    'first_warehouse_id' => null,
                ],
                [],
                false,
                $stateId,
                false,
                true,
                true
            );
        }

        // البونص يقدر يشتغل حتى لو مافيش tracking — هنستخدم pre_id في الـ reference
        $bonusRow = \App\Models\DriverBonus::query()
            ->where('driver_id', $currentDriver->user_id) // عدّلها لو سكيمتك مختلفة
            ->when(!empty($stateId), fn($q) => $q->where('state_id', $stateId))
            ->where(function ($q) use ($facilityType, $facilityId) {
                $q->where(function ($q1) use ($facilityType, $facilityId) {
                    $q1->where('owner_type', $facilityType)->where('owner_id', $facilityId);
                })->orWhere(function ($q2) {
                    $q2->whereNull('owner_type')->whereNull('owner_id');
                });
            })
            ->first();

        // $pickupBonus = (float) ($bonusRow->pickup_bonus ?? 0);
        // if ($pickupBonus > 0) {
        //     $refId = $shipment->tracking_no ?: $shipment->pre_id; // fallback على pre_id
        //     $ref = 'BON-PU-' . $refId;

        //     $exists = \App\Models\Transaction::where('reference', $ref)
        //         ->where('type', 'bonus_credit')
        //         ->exists();

        //     if (!$exists) {
        //         \App\Models\Transaction::create([
        //             'from_id' => $facilityId,
        //             'from_type' => $facilityType,
        //             'to_id' => $currentDriver->user_id,
        //             'to_type' => \App\Models\User::class,
        //             'shipment_id' => $shipment->id,
        //             'amount' => $pickupBonus,
        //             'type' => 'bonus_credit',
        //             'reference' => $ref,
        //             'description' => "Pickup bonus for {$refId}",
        //             'created_by' => $driverId,
        //             'warehouse_id' => $facilityId,
        //         ]);
        //     }
        // }
    }
    public function handleShipmentPickupByShipment(Request $request, int $driverId, Shipment $shipment): array
    {
        $uploadedPath = null;
        $file = $request->file('pickup_proof');
        $createdProof = null;

        try {
            // (1) pickup_proof اختياري
            if ($file) {
                $validator = \Validator::make($request->all(), [
                    'pickup_proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
                ]);
                if ($validator->fails()) {
                    return [
                        'success' => false,
                        'message' => "Validation failed for pickup_proof",
                        'errors' => $validator->errors()->all(),
                        'status' => 422,
                    ];
                }
                $uploadedPath = uploadFile($file, 'public/pickup/proofs');
                if (!$uploadedPath) {
                    return [
                        'success' => false,
                        'message' => "Failed to upload proof image to S3.",
                        'errors' => ['File upload failed'],
                        'status' => 500,
                    ];
                }
            }

            // (2) تخويل السائق/المالك زي الموجود في الميثود الأصلية
            $driver = \App\Models\User::find($driverId);
            if (!$driver) {
                return ['success' => false, 'message' => 'Driver not found', 'errors' => [], 'status' => 404];
            }
            $merchantUser = \App\Models\User::find($shipment->merchant_id);
            if (!$merchantUser || !$merchantUser->owner_type || !$merchantUser->owner_id) {
                return ['success' => false, 'message' => 'Merchant facility missing', 'errors' => [], 'status' => 422];
            }
            if (
                (string) $driver->owner_type !== (string) $merchantUser->owner_type ||
                (string) $driver->owner_id !== (string) $merchantUser->owner_id
            ) {
                return ['success' => false, 'message' => 'Unauthorized facility', 'errors' => [], 'status' => 403];
            }

            // (3) نفّذ نفس تخصيصات البونص/الفيس (نقلناها لفاكشن مشتركة)
            $this->applyFeesAndBonus($shipment, $driverId, $driver);

            // (4) احفظ الـ pickup_proof (لو موجود)
            if ($uploadedPath) {
                $createdProof = \App\Models\ShipmentProof::create([
                    'shipment_id' => $shipment->id,
                    'type' => 'pickup',
                    'path' => $uploadedPath,
                    'uploaded_by' => $driverId,
                ]);
            }

            // (5) سجّل تاريخ + غيّر الحالة تمامًا زي الميثود الأصلية
            $status = "PICKED";
            shipmentHistory([
                "status" => status($status)['name'],
                "description" => status($status)['description'],
                "shipment_id" => $shipment->id,
            ]);
            $shipment->markAsPicked($driverId, MerchantPickupTaskStatusEnum::PICKED);

            // (6) إشعار المستلم (نفس سلوكك)
            if ($shipment->consignee) {
                $shipment->consignee->notify(new \App\Notifications\ConsigneePickupNotification($shipment));
            }

            // (7) رجّع نفس الـ payload
            $proofsResponse = [];
            if ($createdProof) {
                $proofsResponse[] = [
                    'id' => $createdProof->id,
                    'path' => $createdProof->path, // Full URL from uploadFile()
                    'url' => $createdProof->path,  // Same as path (both are full URL)
                    'type' => $createdProof->type,
                ];
            } else {
                $proofsResponse = $shipment->proofs()->where('type', 'pickup')->get()->map(fn($p) => [
                    'id' => $p->id,
                    'path' => $p->path, // Full URL
                    'url' => $p->path,  // Same as path
                    'type' => $p->type,
                ])->toArray();
            }

            return [
                'success' => true,
                'message' => 'Shipment pickup processed successfully (by shipment).',
                'data' => [
                    'shipment_id' => $shipment->id,
                    'proofs' => $proofsResponse,
                    'used_waybill' => false,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "An error occurred.",
                'errors' => [$e->getMessage()],
                'status' => 500,
            ];
        }
    }

    /**
     * استخرجنا جزء تخصيص المصاريف والبونص علشان نعيد استخدامه في الميثودين.
     */
    // protected function applyFeesAndBonus(Shipment $shipment, int $driverId, \App\Models\User $driver): void
    // {
    //     $currentDriver = \App\Models\Driver::where('user_id', $driverId)->first();
    //     if (!$currentDriver)
    //         return;

    //     $facilityType = $driver->owner_type;
    //     $facilityId = $driver->owner_id;
    //     $stateId = $shipment->state_id ?? optional($shipment->consignee)->state_id;

    //     if (!isset($shipment->delivery_fee)) {
    //         $shipment->delivery_fee = (float) optional($shipment->shipment_delivery)->delivery_fee ?? 0.0;
    //     }

    //     app(\App\Services\FeeAllocator::class)->allocateForShipment(
    //         $shipment,
    //         [
    //             'pickup_driver_id' => $currentDriver->id,
    //             'delivery_driver_id' => null,
    //             'first_warehouse_id' => null,
    //         ],
    //         [],
    //         false,
    //         $stateId,
    //         false,
    //         true,
    //         true
    //     );

    //     $bonusRow = \App\Models\DriverBonus::query()
    //         ->where('driver_id', $currentDriver->user_id) // لو سكيمتك مختلفة عدّلها
    //         ->when(!empty($stateId), fn($q) => $q->where('state_id', $stateId))
    //         ->where(function ($q) use ($facilityType, $facilityId) {
    //             $q->where(function ($q1) use ($facilityType, $facilityId) {
    //                 $q1->where('owner_type', $facilityType)->where('owner_id', $facilityId);
    //             })->orWhere(function ($q2) {
    //                 $q2->whereNull('owner_type')->whereNull('owner_id');
    //             });
    //         })
    //         ->first();

    //     $pickupBonus = (float) ($bonusRow->pickup_bonus ?? 0);
    //     if ($pickupBonus > 0) {
    //         $ref = 'BON-PU-' . ($shipment->tracking_no ?? $shipment->pre_id);
    //         $exists = \App\Models\Transaction::where('reference', $ref)
    //             ->where('type', 'bonus_credit')
    //             ->exists();

    //         if (!$exists) {
    //             \App\Models\Transaction::create([
    //                 'from_id' => $facilityId,
    //                 'from_type' => $facilityType,
    //                 'to_id' => $currentDriver->user_id,
    //                 'to_type' => \App\Models\User::class,
    //                 'shipment_id' => $shipment->id,
    //                 'amount' => $pickupBonus,
    //                 'type' => 'bonus_credit',
    //                 'reference' => $ref,
    //                 'description' => "Pickup bonus for " . ($shipment->tracking_no ?? $shipment->pre_id),
    //                 'created_by' => $driverId,
    //                 'warehouse_id' => $facilityId,
    //             ]);
    //         }
    //     }
    // }
    // App\Services\ShipmentPickupService.php




}
