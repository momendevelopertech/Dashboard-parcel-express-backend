<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;

class OFDService
{
    public function remainingExceptionShipments(User $driver)
    {
        return Shipment::whereHas('driverAssignments', function ($query) use ($driver) {
            $query->where('driver_id', $driver->id);
        })
            ->whereHas('shipmentHistories', function ($query) {
                $query->where('status', 'signed');
            })
            ->get();
    }

    public function remainingOFDShipments(User $driver)
    {
        return Shipment::whereHas('driverAssignments', function ($query) use ($driver) {
            $query->where('driver_id', $driver->id);
        })
            ->whereHas('shipmentHistories', function ($query) {
                $query->where('status', 'out_for_delivery');
            })
            ->get();
    }
}
