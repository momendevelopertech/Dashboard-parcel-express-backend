<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Shipper;
use App\Models\AssignShipmentToShelf;
use App\Models\Notification;

class CheckShipperRTO extends Command
{
    protected $signature = 'app:check-shipper-rto';
    protected $description = 'Auto-mark parcels as RTO after they sit on shelf beyond the shipper’s configured days';

    public function handle()
    {
        // Load all shippers along with their 
        Shipper::with('setting')->chunk(50, function ($shippers) {
            foreach ($shippers as $shipper) {
                $rtoDays = optional($shipper->setting)->rto_days;
                if (! $rtoDays || $rtoDays < 1) {
                    continue;
                }

                // Compute the cutoff timestamp
                $cutoff = Carbon::now()->subDays($rtoDays);

                // Find all shelf assignments older than cutoff
                $assignments = AssignShipmentToShelf::where('created_at', '<=', $cutoff)->get();

                foreach ($assignments as $assignment) {
                    $trackingNo = $assignment->tracking_no;

                    // Log progress to console
                    $this->info("Marking RTO: {$trackingNo} for Shipper #{$shipper->id}");

                    // Build a fake request to pass into your controller method
                    $request = new Request(['tracking_no' => $trackingNo]);

                    Notification::create([
                        'notifiable_id' => $assignment->shipment->owner_id,
                        'notifiable_type' => $assignment->shipment->owner_type,
                        'title' => 'Parcel Storage Duration Alert',
                        'content' => "Marking RTO: {$trackingNo} for Shipper #{$shipper->id}",
                        'type' => 'rto_days_reached'
                    ]);
                }
            }
        });

        $this->info('Shipper RTO check complete.');
    }
}
