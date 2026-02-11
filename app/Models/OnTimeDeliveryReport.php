<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnTimeDeliveryReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_date',
        'total_shipments',
        'on_time_deliveries',
        'delayed_deliveries',
        'on_time_rate',
        'region',
    ];

    protected $casts = [
        'report_date'        => 'date',
        'total_shipments'       => 'integer',
        'on_time_deliveries' => 'integer',
        'delayed_deliveries' => 'integer',
        'on_time_rate'       => 'decimal:2',
    ];
}
