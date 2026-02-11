<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DeliverySlot extends Model
{
    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'capacity',
    ];

    protected $casts = [
        'date'       => 'date',
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
        'capacity'   => 'integer',
    ];

    /**
     * Relationship: scheduled deliveries assigned to this slot
     */
    public function scheduledDeliveries(): HasMany
    {
        return $this->hasMany(ScheduledDelivery::class);
    }

    /**
     * Computed attribute: number of assigned shipments
     */
    protected function usedCount(): Attribute
    {
        return Attribute::get(function () {
            return $this->scheduledDeliveries()->count();
        });
    }

    /**
     * Computed attribute: status based on capacity
     * - available: usedCount < capacity
     * - full: usedCount >= capacity
     */
    protected function status(): Attribute
    {
        return Attribute::get(function () {
            return $this->used_count < $this->capacity
                ? 'available'
                : 'full';
        });
    }
}
