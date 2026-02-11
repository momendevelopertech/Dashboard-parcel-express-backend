<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Shipment;
use App\Models\Notification;

class CheckFutureDeliveries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-future-deliveries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'It will check all the shipments which has future delivery date of today.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Future deliveries check command started at: ' . now());

        $tomorow = Carbon::tomorow();
        Log::info('Checking future deliveries for date: ' . $tomorow->toDateString());

        $shipments = Shipment::whereHas('shipment_delivery', function ($query) use ($tomorow) {
            $query->whereDate('future_delivery_date', $tomorow);
        })->with(['shipment_delivery', 'assigned_to_shelf.shelf'])->get();

        Log::info('Found ' . $shipments->count() . ' shipments with future delivery date today.');

        foreach ($shipments as $shipment) {
            if ($shipment->assigned_to_shelf && $shipment->assigned_to_shelf->shelf) {
                $shelf = $shipment->assigned_to_shelf->shelf;

                create_notification(
                    $shelf,
                    'Future Delivery Parcel Alert',
                    "Parcel {$shipment->tracking_no} in shelf {$shelf->name} is scheduled for future delivery today. Please move it to dispatch.",
                    [],
                    'future_delivery_alert'
                );
                Log::info("Notification created for Shipment {$shipment->tracking_no} at Shelf {$shelf->name}");
            } else {
                Log::warning("Shipment {$shipment->tracking_no} is missing shelf assignment.");
            }
        }

        $this->info('Future deliveries check completed. Total notifications created: ' . $shipments->count());
    }
}
