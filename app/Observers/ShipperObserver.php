<?php

namespace App\Observers;

use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use App\Models\Shipper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ShipperObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function creating(Shipper $obs)
    {
        $facility = facility();

        if ($facility) {
            $obs->owner_type = $facility->type;
            $obs->owner_id = $facility->id;
        }
    }

    public function updating(Shipper $obs)
    {
        $facility = facility();

        if ($facility) {
            $obs->owner_type = $facility->type;
            $obs->owner_id = $facility->id;
        }
    }
}
