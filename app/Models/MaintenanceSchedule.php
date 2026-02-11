<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'truck_id',
        'maintenance_type',
        'scheduled_at',
        'status',
        'notes'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /**
     * Relationship: associated truck.
     */
    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }
}
