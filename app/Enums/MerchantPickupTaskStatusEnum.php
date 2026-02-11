<?php

namespace App\Enums;

final class MerchantPickupTaskStatusEnum
{
    /**
     * Task statuses must match DB enum values (lowercase).
     */
    public const CREATED = 'created'; // TODO : Remove this status and replace by PENDING (because a MerchantPickupTask is always starting as a PENDING task)
    public const PENDING = 'pending';
    public const TO_PICKUP = 'to_pickup';
    public const PICKED = 'picked';
    public const PICKUP_COMPLETED = 'pickup_completed';
    public const CANCELLED = 'cancelled';
}


