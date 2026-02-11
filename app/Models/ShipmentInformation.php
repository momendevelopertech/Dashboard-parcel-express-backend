<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentInformation extends Model
{
    use HasFactory;

    protected $fillable = [
        "shipment_id",
        "merchant_id",
        "package_id",
        "zone_id",
        "tracking_no",
        "in_warehouse",
        "lifecycle_end",
        "weight",
        "length",
        "width",
        "height",
        "unit_id",
        "status",
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
