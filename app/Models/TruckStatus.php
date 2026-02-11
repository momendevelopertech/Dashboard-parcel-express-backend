<?php

namespace App\Models;

use App\Observers\TruckStatusObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([TruckStatusObserver::class])]
class TruckStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'truck_id',
        'status',
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

    public function truck()
    {
        return $this->belongsTo(Truck::class, 'truck_id');
    }
}
