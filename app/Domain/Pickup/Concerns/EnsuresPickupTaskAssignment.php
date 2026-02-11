<?php

namespace App\Domain\Pickup\Concerns;

use App\Models\MerchantPickupShipment;

trait EnsuresPickupTaskAssignment
{
    protected function getPickupTaskAssignment(?string $trackingNo, ?string $preId): ?MerchantPickupShipment
    {
        if (!$trackingNo && !$preId) {
            return null;
        }

        return MerchantPickupShipment::query()
            ->where(function ($q) use ($trackingNo, $preId) {
                $applied = false;

                if ($trackingNo) {
                    $q->where('shipment_tracking_no', $trackingNo);
                    $applied = true;
                }

                if ($preId) {
                    if ($applied) {
                        $q->orWhere('pre_id', $preId);
                    } else {
                        $q->where('pre_id', $preId);
                    }
                }
            })
            ->whereNotNull('pickup_task_id')
            ->first();
    }

    protected function pickupTaskAssignmentError(): array
    {
        return [
            'success' => false,
            'message' => "Cannot pickup shipment that's not assigned to Pickup Task",
            'errors' => ["Cannot pickup shipment that's not assigned to Pickup Task"],
            'status' => 422,
        ];
    }
}

