<?php

namespace App\Models;

use App\Models\Scopes\ExcludeReturnShipmentsScope;
use Illuminate\Database\Eloquent\Model;

class DriverRunsheetShipment extends Model
{
    protected $fillable = [
        "runsheet_id",
        "driver_id",
        "shipment_tracking_no",
        "timezone",
        "status",
    ];

    public function runsheet()
    {
        return $this->belongsTo(DriverRunsheet::class, 'runsheet_id');
    }

    /**
     * I want to take the runsheet for $driverId and for today
     */
    public function today_runsheet($driverId)
    {
        return DriverRunsheet::where('driver_id', $driverId)
            ->where('created_at', '>=', now()->startOfDay())
            ->where('created_at', '<=', now()->endOfDay())
            ->first();
    }


    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no')
            ->withoutGlobalScope(ExcludeReturnShipmentsScope::class);
    }

    public function submission()
    {
        return $this->hasOneThrough(
            DriverRunsheetSubmission::class,
            DriverRunsheet::class,
            'id',
            'driver_runsheet_id',
            'runsheet_id',
            'id'
        );
    }

    public function shipment_finance()
    {
        return $this->hasOne(ShipmentFinance::class, 'runsheet_shipment_id');
    }
}
