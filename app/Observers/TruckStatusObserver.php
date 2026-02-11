<?php

namespace App\Observers;

use App\Models\TruckStatus;

class TruckStatusObserver
{
    public function creating(TruckStatus $truckStatus)
    {
        $truckStatus->last_updated = now();
    }

    public function updating(TruckStatus $truckStatus)
    {
        $truckStatus->last_updated = now();
    }
}
