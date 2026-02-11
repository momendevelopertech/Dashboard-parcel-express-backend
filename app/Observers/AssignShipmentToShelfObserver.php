<?php

namespace App\Observers;

use App\Models\AssignShipmentToShelf;
use Illuminate\Http\Request;

class AssignShipmentToShelfObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the AssignShipmentToShelf "creating" event.
     */
    public function creating(AssignShipmentToShelf $shipment)
    {
        $facility = facility();

        if ($facility) {
            $shipment->owner_type = $facility->type;
            $shipment->owner_id = $facility->id;
        }
    }

    /**
     * Handle the AssignShipmentToShelf "updating" event.
     */
    public function updating(AssignShipmentToShelf $shipment)
    {
        $facility = facility();

        if ($facility) {
            $shipment->owner_type = $facility->type;
            $shipment->owner_id = $facility->id;
        }
    }
}
