<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverLocationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'event_type',
        'latitude',
        'longitude',
        'event_timestamp'
    ];

    protected $casts = [
        'latitude'        => 'decimal:7',
        'longitude'       => 'decimal:7',
        'event_timestamp' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
