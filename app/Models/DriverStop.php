<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class DriverStop extends Model
{
    protected $table = 'driver_stops';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'list_id',
        'driver_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'notes',
        'estimated_duration_minutes',
        'is_optimized',
        'optimized_shipment',
        'place_id',
        'contact_person',
        'phone_number',
        'delivery_instructions',
        'is_priority',
        'status',
        'image',
        'package_count',
        'order',
        'type',
        'arrival_time',
        'access_instructions',
    ];

    protected $casts = [
        'is_optimized' => 'boolean',
        'is_priority' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected static function booted(): void
    {

        $recalc = function (self $stop) {
            $stop->list?->recalcStats();
        };
        static::created($recalc);
        static::updated($recalc);
        static::deleted($recalc);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(DriverStopList::class, 'list_id', 'id');
    }
}
