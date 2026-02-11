<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentFeeAllocationOther extends Model
{
    protected $fillable = [
        'shipment_fee_allocation_id',
        'warehouse_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function allocation()
    {
        return $this->belongsTo(ShipmentFeeAllocation::class, 'shipment_fee_allocation_id');
    }
}
