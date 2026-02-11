<?php

namespace App\Enums;

final class ReversePickupTaskStatusEnum
{
    /**
     * Reverse pickup task statuses
     */
    public const PENDING = 'pending';
    public const TO_PICKUP = 'to_pickup';
    public const PICKUP = 'pickup';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    /**
     * Get all status values
     */
    public static function getAll(): array
    {
        return [
            self::PENDING,
            self::TO_PICKUP,
            self::PICKUP,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }
}
