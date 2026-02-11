<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Delivery extends Model
{
    protected $fillable = [
        'driver_id',
        'scheduled_delivery_time',
        'actual_delivery_time',
        'status',
        'delivery_date',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(CustomerFeedback::class);
    }

    public function isOnTime(): bool
    {
        if (! $this->actual_delivery_time) {
            return false;
        }
        return $this->actual_delivery_time <= $this->scheduled_delivery_time;
    }
}
