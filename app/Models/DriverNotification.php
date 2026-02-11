<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverNotification extends Model
{
    protected $fillable = [
        "shipment_tracking_no",
        "driver_id",
        "title",
        "content",
        "type",
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
