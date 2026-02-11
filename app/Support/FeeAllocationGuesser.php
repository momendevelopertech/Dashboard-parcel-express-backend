<?php

namespace App\Support;

use App\Models\Shipment;

class FeeAllocationGuesser
{
    /**
     * استنباط الـ first + others لطلبات التحويل.
     *
     * @return array [firstWarehouseId, otherWarehouseIds[]]
     */
    public static function forTransfer(Shipment $shipment, int $receivingWarehouseId): array
    {
        // لو عندك في Shipment حقول صريحة، بدّل الأسماء باللي عندك فعلاً
        $candidates = collect([
            $shipment->from_warehouse_id ?? null, // مثال: مصدر التحويل (Sohar)
            $shipment->origin_warehouse_id ?? null,
            $shipment->from_hub_id ?? null,
            $shipment->from_station_id ?? null,
            $shipment->other_warehouse_id ?? null,
        ])->filter()->unique()->values();

        // Others = كل المرشحين ماعدا المستلم (Muscat)
        $others = $candidates->reject(fn($id) => (int) $id === (int) $receivingWarehouseId)
            ->values()
            ->all();

        return [$receivingWarehouseId, $others];
    }
}
