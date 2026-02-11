<?php

namespace App\Enums;

final class ReversePickupRequestStatusEnum
{
    /**
     * Reverse pickup request statuses
     */
    public const PENDING = 'pending';
    public const ASSIGNED = 'assigned';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    /**
     * Get all status values
     */
    public static function getAll(): array
    {
        return [
            self::PENDING,
            self::ASSIGNED,
            self::IN_PROGRESS,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }
}
