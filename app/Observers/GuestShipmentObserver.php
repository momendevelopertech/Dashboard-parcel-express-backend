<?php

namespace App\Observers;

use App\Models\GuestShipment;
use App\Services\NearestDriverService;

class GuestShipmentObserver
{
    public function __construct(private NearestDriverService $nearestDriverService) {}

    // /**
    //  * Handle the GuestShipment "created" event.
    //  */
    // public function created(GuestShipment $guestShipment): void
    // {
    //     // Automatically assign nearest drivers once the guest shipment is created.
    //     $this->nearestDriverService->assignNearestDrivers($guestShipment);
    // }
}
