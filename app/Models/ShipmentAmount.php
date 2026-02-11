<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentAmount extends Model
{
    use HasFactory;

    protected $fillable = [
        "shipment_id",
        "amount",
        "display",
        "scale",
        "doubleDisplay",
        "currency",
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}
