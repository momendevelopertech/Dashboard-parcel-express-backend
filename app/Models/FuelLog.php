<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelLog extends Model
{
    use HasFactory;

    protected $table = 'fuel_logs';

    protected $fillable = [
        'driver_id',
        'log_date',
        'distance_km',
        'fuel_liters',
        'efficiency'
    ];

    protected $casts = [
        'log_date'     => 'date',
        'distance_km'  => 'decimal:2',
        'fuel_liters'  => 'decimal:2',
        'efficiency'   => 'decimal:2',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
