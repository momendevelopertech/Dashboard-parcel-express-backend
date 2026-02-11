<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentIntegration extends Model
{
    protected $fillable = [
        "shipment_tracking_no",
        "data"
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }
}
