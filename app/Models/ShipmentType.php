<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentType extends Model
{
    /** @use HasFactory<\Database\Factories\ShipmentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        "name",
        "days",
        "description",
    ];

    public function shipment()
    {
        return $this->hasOne(Shipment::class, 'shipment_type_id');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}
