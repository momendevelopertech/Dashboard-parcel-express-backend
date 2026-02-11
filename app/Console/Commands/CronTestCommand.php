<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Models\User;
use App\Notifications\FutureShipmentNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CronTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cron-test-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('cron job is working successfully');
        Log::info('Future deliveries check command started at: ' . now());

        $tomorow = Carbon::tomorrow();
        Log::info('Checking future deliveries for date: ' . $tomorow->toDateString());

        $shipments = Shipment::whereHas('shipment_delivery', function ($query) use ($tomorow) {
            $query->whereDate('future_delivery_date', $tomorow);
        })->with(['shipment_delivery', 'assigned_to_shelf.shelf'])->get();

        Log::info('Found ' . $shipments->count() . ' shipments with future delivery date today.');

        foreach ($shipments as $shipment) {
            $admins = User::whereHas('roles', function ($query) {
                $query->where('name', 'Super Admin');
            })->get();

            foreach ($admins as $user) {
                $user->notify(new FutureShipmentNotification($shipment));
            }
        }

        $this->info('Future deliveries check completed. Total notifications created: ' . $shipments->count());
        $this->info('Cron test command executed successfully!');
    }
}
