<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickNote extends Model
{
    protected $fillable = [
        "driver_id",
        "shipment_tracking_no",
        "content"
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }
}
