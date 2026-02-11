<?php

namespace App\Enums;

final class ShipmentStatusEnum
{
    /**
     * Shipment statuses.
     */
    public const CREATED = 'CREATED';
    public const ORDER_COLLECTED = 'ORDER_COLLECTED';
    public const ORDER_INBOUNDED = 'ORDER_INBOUNDED';
    public const ORDER_SORTED = 'ORDER_SORTED';
    public const ORDER_LOADED = 'ORDER_LOADED';
    public const ORDER_UNLOADED = 'ORDER_UNLOADED';
    // public const ASSIGNED_FOR_PICKUP = 'ASSIGNED_FOR_PICKUP'; // Duplicate of TO_PICKUP
    public const ASSIGNED_TO_SHELF = 'ASSIGNED_TO_SHELF';
    public const WAITING_CRM = 'WAITING_CRM';
    public const TO_PICKUP = 'TO_PICKUP';
    public const PICKED = 'PICKED';

    public const INVENTORY_CHECK = 'INVENTORY_CHECK';
    public const IN_TRANSIT = 'IN_TRANSIT';
    public const DISPATCH = 'DISPATCH';
    public const OFD = 'OFD';
    public const DELIVERED = 'DELIVERED';
    public const DELIVERY_EXCEPTION = 'DELIVERY_EXCEPTION';
    public const DEFERRED = 'DEFERRED';

    public const CANCELLED = 'CANCELLED';
    public const COMPLETED = 'COMPLETED';
    public const SORTED = 'SORTED';
    public const STOCKOUT = 'STOCKOUT';

    public const MOVE_TO_DISPATCH = 'MOVE_TO_DISPATCH';
    public const MOVE_TO_AREA = 'MOVE_TO_AREA';
    public const MOVE_TO_SUPERVISOR = 'MOVE_TO_SUPERVISOR';
    public const MOVE_TO_SHELF = 'MOVE_TO_SHELF';

    public const CRM_TASK = 'CRM_TASK';
    public const CRM_STARTED = 'CRM_STARTED';

    public const RTO = 'RTO';
    public const RTO_PICKED = 'RTO_PICKED';
    public const RTO_LOADED = 'RTO_LOADED';

    public const LOST = 'LOST';
    public const REDISPATCH = 'REDISPATCH';
    public const DAMAGED = 'DAMAGED';
    public const PICKUP_LOST = 'PICKUP_LOST';

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

    public static function getAll(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
