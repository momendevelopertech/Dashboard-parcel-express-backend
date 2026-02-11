<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentFulfillmentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_date',
        'shipment_type',
        'total_shipments',
        'fulfilled_shipments',
        'failed_shipments',
        'fulfillment_rate',
    ];

    protected $casts = [
        'report_date'       => 'date',
        'total_shipments'      => 'integer',
        'fulfilled_shipments'  => 'integer',
        'failed_shipments'     => 'integer',
        'fulfillment_rate'  => 'decimal:2',
    ];
}
