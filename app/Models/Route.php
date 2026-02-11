<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_code',
        'route_date',
        'driver_id',
        'start_location',
        'end_location',
        'estimated_distance',
        'estimated_duration',
        'status',
        'polyline'
    ];

    protected $casts = [
        'route_date' => 'date',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function stops()
    {
        return $this->hasMany(RouteStop::class)->orderBy('sequence');
    }
}
