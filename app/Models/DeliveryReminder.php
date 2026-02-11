<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryReminder extends Model
{
    protected $fillable = [
        'shipment_id', 'recipient', 'recipient_id',
        'reminder_time', 'methods', 'status', 'enabled',
    ];

    protected $casts = [
        'reminder_time' => 'datetime',
        'methods'       => 'array',
        'enabled'       => 'boolean',
    ];

    public function scheduledDelivery()
    {
        return $this->belongsTo(ScheduledDelivery::class, 'shipment_id', 'shipment_id');
    }
}
