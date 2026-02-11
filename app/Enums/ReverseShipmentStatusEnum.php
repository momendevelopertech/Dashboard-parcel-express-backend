<?php

namespace App\Enums;

final class ReverseShipmentStatusEnum
{
    /**
     * Reverse shipment lifecycle statuses
     * Following the scenario document workflow
     */
    public const REVERSE_CREATED = 'REVERSE_CREATED';              // Step 1: System initiation
    public const REVERSE_ASSIGNED = 'REVERSE_ASSIGNED';            // Assigned to driver
    public const REVERSE_PICKED_UP = 'REVERSE_PICKED_UP';          // Step 3: Driver picked up from customer
    public const REVERSE_AT_HUB = 'REVERSE_AT_HUB';                // Step 4: Arrived at hub/warehouse
    public const RETURNED_TO_MERCHANT = 'RETURNED_TO_MERCHANT';    // Step 5: Final delivery to merchant
    public const REVERSE_CANCELLED = 'REVERSE_CANCELLED';          // Cancelled

    /**
     * Get all status values
     */
    public static function getAll(): array
    {
        return [
            self::REVERSE_CREATED,
            self::REVERSE_ASSIGNED,
            self::REVERSE_PICKED_UP,
            self::REVERSE_AT_HUB,
            self::RETURNED_TO_MERCHANT,
            self::REVERSE_CANCELLED,
        ];
    }
}
