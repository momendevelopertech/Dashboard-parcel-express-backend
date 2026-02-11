<?php

namespace App\Services;

use App\Http\Resources\SorterResource;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Zone;
use Exception;
use Illuminate\Support\Facades\Auth;

class SorterService
{
    public function disallowDeliveredParcels(Shipment $shipment)
    {
        if (strtolower($shipment->coreStatus()->name) === 'delivered') {
            return sendResponse(
                'Shipment is already delivered.',
                new SorterResource([
                    'tracking_no' => $shipment->tracking_no,
                    'action' => 'MOVE_TO_SUPERVISOR',
                ])
            );
        }
    }
    public function determineParcelZone(Shipment $shipment, ?Zone $zone = null): array
    {
        $user = Auth::user();

        if (!$zone) {
            $zone = $shipment->consignee ? $shipment->consignee->zone() : null;
        }

        if (!$zone) {
            throw new Exception("This shipment doesn't have any zone.");
        }

        $zone = Zone::find($zone->id);
        $zoneOwner = $zone->owner;
        $userOwner = $user->owner;

        if (!$zoneOwner || !$userOwner) {
            throw new Exception("Invalid zone or user ownership.");
        }

        if ($zoneOwner->is($userOwner)) {
            return [
                'zone' => $zone,
                'action' => 'MOVE_TO_DISPATCH',
                'area' => $zone->name,
                'description' => "Move to Dispatch: Zone - {$zone->name}",
            ];
        }

        return [
            'zone' => $zone,
            'action' => 'MOVE_TO_AREA',
            'area' => $zoneOwner->name,
            'description' => "This shipment belongs to {$zoneOwner->name}",
        ];
    }

    // public function determineParcelZone(Shipment $shipment): array
    // {
    //     $user = Auth::user();
    //     $zone = $shipment->consignee->zone();

    //     if (!$zone) {
    //         throw new Exception("This shipment doesn't have any zone.");
    //     }

    //     $zone = Zone::find($zone->id);
    //     $zoneOwner = $zone->owner;
    //     $userOwner = $user->owner;

    //     if (!$zoneOwner || !$userOwner) {
    //         throw new Exception("Invalid zone or user ownership.");
    //     }

    //     if ($zoneOwner->is($userOwner)) {
    //         return [
    //             'zone' => $zone,
    //             'action' => 'MOVE_TO_DISPATCH',
    //             'area' => $zone->name,
    //             'description' => "Move to Dispatch: Zone - {$zone->name}"
    //         ];
    //     }

    //     return [
    //         'zone' => $zone,
    //         'action' => 'MOVE_TO_AREA',
    //         'area' => $zoneOwner->name,
    //         'description' => "This shipment belongs to {$zoneOwner->name}"
    //     ];
    // }
}
