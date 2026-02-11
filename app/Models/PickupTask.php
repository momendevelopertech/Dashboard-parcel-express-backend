<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * PickupTask Model
 * 
 * Represents a driver assignment for picking up a specific shipment.
 * Replaces the old ReversePickupTask model.
 * 
 * Key Change: Now links directly to individual shipments instead of requests,
 * enabling per-shipment/per-zone driver assignment.
 * 
 * Relationships:
 * - Belongs to shipment (the item to be picked up)
 * - Belongs to driver (user)
 * - Belongs to return request (for grouping)
 * - Belongs to zone
 */
class PickupTask extends Model
{
    use HasFactory;

    protected $table = 'pickup_tasks';

    protected $fillable = [
        'shipment_id',
        'driver_id',
        'zone_id',
        'return_request_id',
        'status',
        'scheduled_at',
        'picked_at',
        'note',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'picked_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    /**
     * Scopes
     */
    public function scopeByDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeByZone($query, $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAssigned($query)
    {
        return $query->whereNotNull('driver_id')
            ->where('status', 'assigned');
    }

    public function scopeToPickup($query)
    {
        return $query->where('status', 'to_pickup');
    }

    public function scopePicked($query)
    {
        return $query->where('status', 'picked');
    }
}
