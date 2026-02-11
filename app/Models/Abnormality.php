<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Abnormality extends Model
{
    protected  $fillable = [
        "shipment_tracking_no",
        "type",
        "subtype",
        "notes"
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }
}
