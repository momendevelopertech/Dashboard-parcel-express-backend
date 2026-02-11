<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentFine extends Model
{
    protected $fillable = [
        "shipment_tracking_no",
        "driver_id",
        "created_by",
        "amount",
        "notes",
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
