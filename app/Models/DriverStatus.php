<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Observers\DriverStatusObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([DriverStatusObserver::class])]
class DriverStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'location',
        'latitude',
        'longitude',
        'last_updated',
    ];

    protected $casts = [
        'last_updated' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8'
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopeNearest($query, float $lat, float $lng, ?float $radiusKm = null, int $limit = 20)
    {
        // Haversine formula to calculate great-circle distance between two points on a sphere
        // 6371 = Earth radius in kilometres. Replace with 3959 for miles.
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

        $bindings = [$lat, $lng, $lat];

        $query->selectRaw("driver_id, latitude, longitude, {$haversine} as distance", $bindings)
              ->whereNotNull('latitude')
              ->whereNotNull('longitude')
              ->orderBy('distance');

        if ($radiusKm !== null) {
            $query->havingRaw('distance <= ?', [$radiusKm]);
        }

        return $query->limit($limit);
    }
}