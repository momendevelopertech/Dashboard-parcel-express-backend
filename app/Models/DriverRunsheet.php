<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Enums\ShipmentStatusEnum;

class DriverRunsheet extends Model
{
    protected $fillable = [
        "driver_id",
        "timezone",
        "status",
        "confirmed_at",
        "holded_at",
        "notes",
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'holded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['total_delivered_cod'];

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'driver_runsheet_id');
    }

    public function submission()
    {
        return $this->hasOne(DriverRunsheetSubmission::class, 'driver_runsheet_id', 'id')->orderBy('id', 'desc');
    }

    public function submissions()
    {
        return $this->hasMany(DriverRunsheetSubmission::class, 'driver_runsheet_id', 'id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function assigned_shipments()
    {
        return $this->hasMany(DriverRunsheetShipment::class, 'runsheet_id');
    }

    public function delivered_shipments()
    {
        return $this->hasMany(DriverRunsheetShipment::class, 'runsheet_id')->where('status', 'delivered');
    }

    public function not_delivered_shipments()
    {
        return $this->hasMany(DriverRunsheetShipment::class, 'runsheet_id')->where('status', 'not_delivered');
    }

    public function returned_shipments()
    {
        return $this->hasMany(DriverRunsheetShipment::class, 'runsheet_id')->where('status', 'returned');
    }

    public function holding_shipments()
    {
        return $this->hasMany(DriverRunsheetShipment::class, 'runsheet_id')->where('status', 'holding');
    }

    public function difference_shipments()
    {
        return $this->hasMany(DriverRunsheetShipment::class, 'runsheet_id')
            ->where('status', 'not_delivered')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('driver_runsheet_shipments as dro')
                    ->whereColumn('dro.shipment_tracking_no', 'driver_runsheet_shipments.shipment_tracking_no')
                    ->where('dro.status', 'returned')
                    ->whereColumn('dro.runsheet_id', 'driver_runsheet_shipments.runsheet_id');
            });
    }

    public function shipments()
    {
        return $this->hasManyThrough(
            Shipment::class,
            DriverRunsheetShipment::class,
            'runsheet_id',
            'tracking_no',
            'id',
            'shipment_tracking_no'
        );
    }

    /**
     * Get delivered shipments through the relationship
     */
    public function deliveredShipments()
    {
        return $this->hasManyThrough(
            Shipment::class,
            DriverRunsheetShipment::class,
            'runsheet_id',
            'tracking_no',
            'id',
            'shipment_tracking_no'
        )->where('shipments.status', ShipmentStatusEnum::DELIVERED);
    }

    /**
     * Calculate total COD for delivered shipments
     */
    protected function totalDeliveredCod(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->deliveredShipments()->sum('shipments.total_cod') ?? 0
        );
    }

    /**
     * Get created_at in the runsheet's timezone
     */
    public function getCreatedAtInTimezoneAttribute()
    {
        return $this->created_at?->setTimezone($this->timezone ?? config('app.timezone'));
    }

    /**
     * Get confirmed_at in the runsheet's timezone
     */
    public function getConfirmedAtInTimezoneAttribute()
    {
        return $this->confirmed_at?->setTimezone($this->timezone ?? config('app.timezone'));
    }
}
