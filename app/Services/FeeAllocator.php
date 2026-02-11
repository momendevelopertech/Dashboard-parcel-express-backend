<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\TransferFee;
use App\Models\ShipmentFeeAllocation;
use App\Models\ShipmentFeeAllocationOther;
use App\Models\DriverBonus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FeeAllocator
{
    /**
     * Allocate delivery_fee across pickup, first warehouse, 0..N other warehouses, delivery driver, and company.
     *
     * @param Shipment     $shipment                         Must have delivery_fee set (decimal).
     * @param array     $ids                           ['first_warehouse_id'=>?int,'pickup_driver_id'=>?int,'delivery_driver_id'=>?int]
     * @param int[]     $otherWarehouseIds             0..N other warehouses
     * @param bool      $upscaleWhenSurplus            If F >= fixed sum: false => remainder to company (default). true => proportionally upscale (company=0).
     * @param int|null  $stateId                       For driver bonus lookup (delivery side). If null, will not use DriverBonus.
     * @param bool      $useDriverBonusForDelivery     If true, use DriverBonus.delivery_bonus for delivery driver amount when possible.
     */
    public function allocateForShipment(
        Shipment $shipment,
        array $ids = [],
        array $otherWarehouseIds = [],
        bool $upscaleWhenSurplus = false,
        ?int $stateId = null,
        bool $useDriverBonusForDelivery = true,
        bool $useDriverBonusForPickup = true,
        bool $preserveExistingFirst = true
    ): ShipmentFeeAllocation {
        $F = (float) ($shipment->delivery_fee ?? 0.0);

        // Fixed config from transfer_fees (will be 0 if missing)
        $pickupFixed = (float) TransferFee::getAmount('pickup_driver');
        $firstFixed = (float) TransferFee::getAmount('first_warehouse');
        $otherFixed = (float) TransferFee::getAmount('other_warehouse');  // per each "other"
        $tfDelivery = (float) TransferFee::getAmount('delivery_driver');  // fallback

        $hasPickup = !empty($ids['pickup_driver_id']);
        $hasDelivery = !empty($ids['delivery_driver_id']);
        $hasFirst = !empty($ids['first_warehouse_id']);
        $Nothers = count($otherWarehouseIds);

        // Pickup driver's fixed part from DriverBonus (by driver+state) if enabled and available
        if ($useDriverBonusForPickup && $hasPickup && $stateId) {
            $facilityType = function_exists('facility') ? facility('type') : null;
            $facilityId = function_exists('facility') ? facility('id') : null;

            $pickupBonusRow = DriverBonus::query()
                ->where('driver_id', $ids['pickup_driver_id'])
                ->where('state_id', $stateId)
                ->orderByRaw(
                    'CASE ' .
                        'WHEN owner_type = ? AND owner_id = ? THEN 0 ' .
                        'WHEN owner_type IS NULL AND owner_id IS NULL THEN 1 ' .
                        'ELSE 2 END',
                    [$facilityType, $facilityId]
                )
                ->first();

            $pickupFixed = (float) ($pickupBonusRow->pickup_bonus ?? $pickupFixed ?? 0.0);
        }

        // Delivery driver's fixed part from DriverBonus (by driver+state) if enabled and available
        $deliveryFixed = 0.0;
        if ($useDriverBonusForDelivery && $hasDelivery && $stateId) {
            $facilityType = function_exists('facility') ? facility('type') : null;
            $facilityId = function_exists('facility') ? facility('id') : null;

            $deliveryBonusRow = DriverBonus::query()
                ->where('driver_id', $ids['delivery_driver_id'])
                ->where('state_id', $stateId)
                ->orderByRaw(
                    'CASE ' .
                        'WHEN owner_type = ? AND owner_id = ? THEN 0 ' .
                        'WHEN owner_type IS NULL AND owner_id IS NULL THEN 1 ' .
                        'ELSE 2 END',
                    [$facilityType, $facilityId]
                )
                ->first();

            $deliveryFixed = (float) ($deliveryBonusRow->delivery_bonus ?? 0.0);
        }
        if ($deliveryFixed <= 0.0) {
            $deliveryFixed = $tfDelivery; // fallback to transfer_fees config if no bonus row
        }

        // Desired (pre-scaling) vector
        $desired = [
            'pickup' => $hasPickup ? $pickupFixed : 0.0,
            'first' => $hasFirst ? $firstFixed : 0.0,
            'others' => $Nothers ? $otherFixed * $Nothers : 0.0,
            'delivery' => $hasDelivery ? $deliveryFixed : 0.0,
        ];

        $S_fixed = array_sum($desired);

        // Nothing to pay
        if ($F <= 0 || $S_fixed <= 0) {
            return $this->persist($shipment, $ids, $otherWarehouseIds, [
                'pickup' => 0.0,
                'first' => 0.0,
                'others' => 0.0,
                'delivery' => 0.0,
                'company' => max(0.0, $F),
            ]);
        }

        // Apply rules
        if ($F >= $S_fixed) {
            if ($upscaleWhenSurplus) {
                $k = $F / $S_fixed; // upscale
                $alloc = [
                    'pickup' => round($desired['pickup'] * $k, 3),
                    'first' => round($desired['first'] * $k, 3),
                    'others' => round($desired['others'] * $k, 3),
                    'delivery' => round($desired['delivery'] * $k, 3),
                    'company' => 0.000,
                ];
            } else {
                // Fixed + remainder to company (DEFAULT)
                $alloc = $desired;
                $alloc['company'] = round($F - $S_fixed, 3);
            }
        } else {
            // Not enough: proportionally scale down (company=0)
            $k = $F / $S_fixed;
            $alloc = [
                'pickup' => round($desired['pickup'] * $k, 3),
                'first' => round($desired['first'] * $k, 3),
                'others' => round($desired['others'] * $k, 3),
                'delivery' => round($desired['delivery'] * $k, 3),
                'company' => 0.000,
            ];
        }

        // return $this->persist($shipment, $ids, $otherWarehouseIds, $alloc);
        return $this->persist($shipment, $ids, $otherWarehouseIds, $alloc, $preserveExistingFirst);
    }

    // app/Services/FeeAllocator.php


    private function persist(
        Shipment $shipment,
        array $ids,
        array $otherWarehouseIds,
        array $alloc,
        bool $preserveExistingFirst = true
    ): ShipmentFeeAllocation {
        return DB::transaction(function () use ($shipment, $ids, $otherWarehouseIds, $alloc, $preserveExistingFirst) {

            // 1) Read existing
            $existing = ShipmentFeeAllocation::where('shipment_tracking_no', $shipment->tracking_no)->first();

            // 2) Decide FIRST (preserve if exists)
            $incomingFirst = Arr::get($ids, 'first_warehouse_id');
            $firstId = ($preserveExistingFirst && $existing && $existing->first_warehouse_id)
                ? (int) $existing->first_warehouse_id
                : (int) $incomingFirst;

            // 3) Collect existing OTHERS from children + meta
            $existingOtherIds = [];
            if ($existing) {
                $existingOtherIds = array_map(
                    'intval',
                    ShipmentFeeAllocationOther::where('shipment_fee_allocation_id', $existing->id)->pluck('warehouse_id')->all()
                );
                $existingOtherIds = array_merge(
                    $existingOtherIds,
                    array_map('intval', (array) data_get($existing, 'meta.others_ids', []))
                );
            }

            // 4) Merge new OTHERS + existing, remove FIRST, dedupe
            $mergedOthers = array_values(array_unique(array_filter(
                array_map('intval', array_merge($existingOtherIds, $otherWarehouseIds)),
                fn($wid) => $wid > 0
            )));
            if ($firstId) {
                $mergedOthers = array_values(array_filter($mergedOthers, fn($wid) => $wid !== (int) $firstId));
            }

            // 5) If exactly ONE other, store it in the summary col
            $singleOtherId = count($mergedOthers) === 1 ? $mergedOthers[0] : null;

            // 6) Upsert master
            $search=[];
            if(!empty($shipment->tracking_no))
                {
                    $search=['shipment_tracking_no' => $shipment->tracking_no];
                }
            else
                {
                    $search=['pre_id' => $shipment->pre_id];
                }
            $row = ShipmentFeeAllocation::updateOrCreate(
                $search,
                [
                    'first_warehouse_id' => $firstId ?: null,
                    'other_warehouse_id' => $singleOtherId,
                    'pickup_driver_id' => Arr::get($ids, 'pickup_driver_id'),
                    'delivery_driver_id' => Arr::get($ids, 'delivery_driver_id'),

                    'pickup_driver_amount' => $alloc['pickup'],
                    'first_warehouse_amount' => $alloc['first'],
                    'other_warehouse_amount' => $alloc['others'],
                    'delivery_driver_amount' => $alloc['delivery'],
                    'company_amount' => $alloc['company'],
                    'total_delivery_fee' => (float) $shipment->delivery_fee,

                    'meta' => [
                        'others_ids' => $mergedOthers, // keep ALL others here
                    ],
                ]
            );

            // 7) Rebuild children equally from merged others
            ShipmentFeeAllocationOther::where('shipment_fee_allocation_id', $row->id)->delete();
            $eachOther = (count($mergedOthers) && $alloc['others'] > 0)
                ? round($alloc['others'] / count($mergedOthers), 3)
                : 0.0;

            foreach ($mergedOthers as $wid) {
                ShipmentFeeAllocationOther::create([
                    'shipment_fee_allocation_id' => $row->id,
                    'warehouse_id' => $wid,
                    'amount' => $eachOther,
                ]);
            }

            return $row->fresh(['others']);
        });
    }
}
