<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
class DriverStopList extends Model
{
    protected $table = 'driver_stop_lists';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'driver_id',
        'name',
        'description',
        'is_active',
        'is_default',
        'total_stops',
        'delivered_stops',
        'pending_stops',
        'failed_stops',
        'total_distance_meters',
        'total_duration_seconds',
        'optimized_route_data',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'optimized_route_data' => 'array',
        'metadata' => 'array',
        'total_distance_meters' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function stops(): HasMany
    {
        return $this->hasMany(DriverStop::class, 'list_id', 'id');
    }

    public function recalcStats(): void
    {
        $total = $this->stops()->count();
        $delivered = $this->stops()->where('status', 'delivered')->count();
        $failed = $this->stops()->where('status', 'failed')->count();
        $pending = $this->stops()->whereIn('status', ['pending', 'in_transit', 'cancelled'])->count();

        $this->forceFill([
            'total_stops' => $total,
            'delivered_stops' => $delivered,
            'failed_stops' => $failed,
            'pending_stops' => $pending,
        ])->save();
    }
}
