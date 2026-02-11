<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Mail\ShelfAwaitMail;
use App\Models\AssignShipmentToShelf;
use App\Models\Notification;
use App\Models\StockOutTask;
use App\Models\StockOutTaskShipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckShelfParcels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-shelf-parcels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will check the shelf everyday for the parcels which are resting in the shelf for last 30 days.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Daily shelf check task executed at: ' . now());

        $oneMonthAgo = Carbon::now()->subDays(30);
        Log::info($oneMonthAgo);
        Log::info(Carbon::now());
        $parcels = AssignShipmentToShelf::where('created_at', '<=', $oneMonthAgo)
            ->with(['shelf', 'shipment'])
            ->get();

        $task = StockOutTask::create([
            'owner_type' => facility("type"),
            'owner_id' => facility("id"),
            'created_by' => Auth::id(),
        ]);

        foreach ($parcels as $parcel) {
            $shelf = $parcel->shelf;
            $facility = $shelf->owner ?? 'Unknown Facility';
            $daysWaited = intval(Carbon::parse($parcel->created_at)->diffInDays());


            StockOutTaskShipment::create([
                'stock_out_task_id' => $task->id,
                'shipment_tracking_no' => $parcel->tracking_no,
                'shelf_barcode' => $shelf->barcode,
            ]);


            Notification::create([
                'notifiable_id' => $shelf->id,
                'notifiable_type' => get_class($shelf),
                'title' => 'Parcel Storage Duration Alert',
                'content' => "Parcel {$parcel->tracking_no} is in shelf {$shelf->name} for {$daysWaited} days",
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

        $this->info('Daily shelf check completed. Notifications: ' . $parcels->count());
    }
}
