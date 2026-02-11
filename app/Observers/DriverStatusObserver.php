<?php

namespace App\Observers;

use App\Models\DriverStatus;

class DriverStatusObserver
{
    public function creating(DriverStatus $driverStatus)
    {
        $driverStatus->last_updated = now();
    }

    public function updating(DriverStatus $driverStatus)
    {
        $driverStatus->last_updated = now();
    }
}
