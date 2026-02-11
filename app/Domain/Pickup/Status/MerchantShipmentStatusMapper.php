<?php

namespace App\Domain\Pickup\Status;

use App\Enums\MerchantPickupTaskStatusEnum;
use App\Enums\ShipmentStatusEnum;

class MerchantShipmentStatusMapper
{
    protected const MAP = [
        // MerchantPickupTaskStatusEnum::CREATED => ShipmentStatusEnum::ASSIGNED_FOR_PICKUP,
        // MerchantPickupTaskStatusEnum::PENDING => ShipmentStatusEnum::ASSIGNED_FOR_PICKUP,
        MerchantPickupTaskStatusEnum::TO_PICKUP => ShipmentStatusEnum::TO_PICKUP,
        MerchantPickupTaskStatusEnum::PICKED => ShipmentStatusEnum::PICKED,
        MerchantPickupTaskStatusEnum::PICKUP_COMPLETED => ShipmentStatusEnum::COMPLETED,
        MerchantPickupTaskStatusEnum::CANCELLED => ShipmentStatusEnum::CANCELLED,
    ];

    public static function toShipment(?string $merchantStatus): ?string
    {
        if (!$merchantStatus) {
            return null;
        }

        return self::MAP[$merchantStatus] ?? null;
    }
}

