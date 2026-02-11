<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentFeeAllocation extends Model
{
    protected $fillable = [
        'shipment_tracking_no',
        'first_warehouse_id',
        'other_warehouse_id',
        'pickup_driver_id',
        'delivery_driver_id',
        'pickup_driver_amount',
        'first_warehouse_amount',
        'other_warehouse_amount',
        'delivery_driver_amount',
        'company_amount',
        'total_delivery_fee',
        'meta',
        "pre_id"
    ];

    protected $casts = [
        'meta' => 'array',
        'pickup_driver_amount' => 'decimal:3',
        'first_warehouse_amount' => 'decimal:3',
        'other_warehouse_amount' => 'decimal:3',
        'delivery_driver_amount' => 'decimal:3',
        'company_amount' => 'decimal:3',
        'total_delivery_fee' => 'decimal:3',
    ];
    public function others()
    {
        return $this->hasMany(ShipmentFeeAllocationOther::class, 'shipment_fee_allocation_id');
    }
}
