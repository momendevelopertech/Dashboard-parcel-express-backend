<?php

use App\Mail\ShelfAwaitMail;
use App\Models\AssignShipmentToShelf;
use App\Models\Notification;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Jobs\NotifyFutureDeliveryDue;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('app:daily-task', function () {
    Log::info('Daily shelf check task executed at: ' . now());

    $oneMonthAgo = Carbon::now()->subDays(30);
    $parcels = AssignShipmentToShelf::where('created_at', '<=', $oneMonthAgo)
        ->with(['shelf', 'shipment'])
        ->get();

    foreach ($parcels as $parcel) {
        $shelf = $parcel->shelf;
        $facility = $shelf->owner ?? 'Unknown Facility';
        $daysWaited = intval(Carbon::parse($parcel->created_at)->diffInDays());

        Notification::create([
            'notifiable_id' => $shelf->id,
            'notifiable_type' => get_class($shelf),
            'title' => 'Parcel Storage Duration Alert',
            'content' => "Parcel {$parcel->tracking_no} is in shelf {$shelf->barcode} for {$daysWaited} days",
            'type' => 'shelf_storage_alert'
        ]);

        Mail::to(env('SYSTEM_EMAIL'))
            ->send(new ShelfAwaitMail(
                trackingNo: $parcel->tracking_no,
                shelfBarcode: $shelf->barcode,
                facilityName: $facility->name,
                daysWaited: $daysWaited
            ));
    }
})->purpose('Check for Parcels in Shelf')->dailyAt('00:00');

// Notify about FUTURE_DELIVERY shipments due today
Artisan::command('notify:future-delivery-due', function () {
    $this->info('Checking for future delivery shipments due today...');

    NotifyFutureDeliveryDue::dispatchSync();

    $this->info('Future delivery due notifications sent.');
})->purpose('Send notifications for FUTURE_DELIVERY shipments due today')->hourly();
