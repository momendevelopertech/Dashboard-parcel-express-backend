<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TruckObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the Truck "creating" event.
     */
    public function creating(Truck $truck)
    {
        $facility = facility();

        if ($facility) {
            $truck->owner_type = $facility->type;
            $truck->owner_id = $facility->id;
        }

        if (empty($truck->barcode)) {
            $truck->barcode = strtoupper(uniqid());
        }
    }

    /**
     * Handle the Truck "updating" event.
     */
    public function updating(Truck $truck)
    {
        $facility = facility();

        if ($facility) {
            $truck->owner_type = $facility->type;
            $truck->owner_id = $facility->id;
        }
    }
}
