<?php

namespace App\Listeners;

use App\Events\OfdCountChanged;
use App\Models\CrmComplaint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleOfdCountChange
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OfdCountChanged $event): void
    {
        $oldOfdCount = $event->oldOfdCount;
        $newOfdCount = $event->newOfdCount;
        $shipmentDelivery = $event->shipmentDelivery;

        if ($newOfdCount == 3) {
            Log::info("Shipment Delivery with OFD count: $newOfdCount");
            // CrmComplaint::create([
            //     'shipment_id' => $shipmentDelivery->shipment_id,
            //     'complaint_details' => "Shipment Delivery with OFD count: $newOfdCount",
            // ]);
        }
    }
}
