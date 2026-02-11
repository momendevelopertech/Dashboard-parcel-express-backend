<?php

namespace App\Helpers;

class PartnerApiHelper
{
    /**
     * Format shipment ID for API response
     */
    public static function formatShipmentId(int $id): string
    {
        return 'ORD-' . date('Y') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Format partner ID for API response
     */
    public static function formatPartnerId(int $id): string
    {
        return 'PARTNER_' . $id;
    }

    /**
     * Parse internal shipment ID
     */
    public static function parseShipmentId(string $formattedId): ?int
    {
        if (preg_match('/^ORD-\d{4}-(\d+)$/', $formattedId, $matches)) {
            return (int) $matches[1];
        }

        // Fallback for simple format
        if (preg_match('/^ORD-(\d+)$/', $formattedId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Get available shipment statuses
     */
    public static function getShipmentStatuses(): array
    {
        return [
            'created',
            'accepted',
            'picked_up',
            'in_transit',
            'out_for_delivery',
            'delivered',
            'delivery_failed',
            'returned',
            'cancelled',
        ];
    }

    /**
     * Validate status transition
     */
    public static function canTransitionStatus(string $from, string $to): bool
    {
        $transitions = [
            'created' => ['accepted', 'cancelled'],
            'accepted' => ['picked_up', 'cancelled'],
            'picked_up' => ['in_transit', 'returned'],
            'in_transit' => ['out_for_delivery', 'returned'],
            'out_for_delivery' => ['delivered', 'delivery_failed'],
            'delivery_failed' => ['out_for_delivery', 'returned'],
            'returned' => [],
            'delivered' => [],
            'cancelled' => [],
        ];

        return in_array($to, $transitions[$from] ?? []);
    }
}