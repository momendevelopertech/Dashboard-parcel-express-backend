<?php

namespace App\Services;

use App\Models\DriverStatus;
use App\Models\DriverShipmentAssignment;
use App\Models\Shipment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class NearestDriverService
{
    public function assignNearestDrivers(Shipment $shipment, int $limit = 20, ?float $radiusKm = null): void
    {
        info("assignNearestDrivers", ["shipment" => $shipment]);
        $lat = $shipment->consignee->latitude;
        $lng = $shipment->consignee->longitude;

        if ($lat === null || $lng === null) {
            Log::warning('Shipment latitude/longitude missing; cannot assign nearest drivers', [
                'tracking_no' => $shipment->tracking_no,
            ]);
            return;
        }

        $driverStatuses = DriverStatus::nearest($lat, $lng, $radiusKm, $limit)
            ->with('driver')
            ->get();

        info("DriverShipmentAssignment", ["driverStatuses" => $driverStatuses]);

        DB::transaction(function () use ($driverStatuses, $shipment) {
            foreach ($driverStatuses as $status) {
                $temp = DriverShipmentAssignment::firstOrCreate([
                    'shipment_tracking_no' => $shipment->tracking_no,
                    'shipment_id' => $shipment->id,
                    'driver_id'         => $status->driver_id,
                ], [
                    'status'     => 'offered',
                    'assigned_at' => now(),
                    'offered_at' => now(),
                    'from_merchant' => true,
                ]);
            }
        });
    }
}
