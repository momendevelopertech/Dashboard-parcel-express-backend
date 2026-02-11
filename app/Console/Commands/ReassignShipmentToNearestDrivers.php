<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shipment;
use App\Services\NearestDriverService;

class ReassignShipmentToNearestDrivers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reassign-shipment-to-nearest-drivers';

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
        // Find shipments in active workflow (customer shipments accepted by admin)
        // that have offers sent but no driver has accepted yet
        $shipments = Shipment::whereNotNull('owner_id')
            ->whereNotNull('owner_type')
            ->whereHas('driverAssignments')
            ->whereDoesntHave('driverAssignments', function ($q) {
                $q->whereNotNull('accepted_at');
            })
            ->get();

        $service = new NearestDriverService();
        $count = 0;
        foreach ($shipments as $shipment) {
            // Re-assign to nearest drivers, skip those already assigned
            $service->assignNearestDrivers($shipment);
            $this->info("Reassigned shipment {$shipment->id} to nearest drivers.");
            $count++;
        }
        $this->info("Total shipments reassigned: {$count}");
    }
}
