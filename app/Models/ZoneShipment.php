<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneShipment extends Model
{
    protected $fillable = [
        "owner_id",
        "owner_type",
        "zone_id",
        "shipment_tracking_no"
    ];

    public function owner()
    {
        return $this->morphTo();
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }
}