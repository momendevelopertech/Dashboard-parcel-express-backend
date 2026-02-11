<?php

namespace App\Enums;

final class DriverBonusesTransactionActionsEnum
{
    public const PICKUP = "Pickup";
    public const RETURN_PICKUP = "return_pickup";
    public const ASSIGN = "Assign";
    public const DELIVERY = "Delivery";

    /**
     * Normalize arbitrary status strings to known enum values when available.
     */
    public static function normalize(string $status): string
    {
        static $values = null;

        if ($values === null) {
            $values = array_values((new \ReflectionClass(self::class))->getConstants());
        }

        $upper = strtoupper($status);

        return in_array($upper, $values, true) ? $upper : $upper;
    }
}

