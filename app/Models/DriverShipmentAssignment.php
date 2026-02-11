<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverShipmentAssignment extends Model
{
    protected $fillable = [
        'shipment_id',
        'shipment_tracking_no',
        'driver_id',
        'timezone',
        'assigned_by',
        'assigned_at',
        'confirmed_at',
        'delivered_at',
        'returned_at',
        'status',
        'offered_at',
        'accepted_at',
        'from_merchant',
        'rejection_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
        'offered_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function guest_shipment()
    {
        return $this->belongsTo(GuestShipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    // Driver relation
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    // Assigned by user relation
    public function assigned_by()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }


    public function markAsReturned(string $notes = null)
    {
        $this->update([
            'is_returned' => true,
            'return_notes' => $notes,
            'status' => 'returned',
        ]);

        $status = "ORDER_RETURNED";

        shipmentHistory([
            "status" => status($status)['label'],
            "description" => status($status)['description'],
            "shipment_id" => $this->shipment_id,
        ]);
    }

    public function shipment_deliveries()
    {
        return $this->hasOne(ShipmentDelivery::class, 'assignment_id');
    }

    /**
     * Get assigned_at in the assignment's timezone
     */
    public function getAssignedAtInTimezoneAttribute()
    {
        return $this->assigned_at?->setTimezone($this->timezone ?? config('app.timezone'));
    }

    /**
     * Get confirmed_at in the assignment's timezone
     */
    public function getConfirmedAtInTimezoneAttribute()
    {
        return $this->confirmed_at?->setTimezone($this->timezone ?? config('app.timezone'));
    }

    /**
     * Get delivered_at in the assignment's timezone
     */
    public function getDeliveredAtInTimezoneAttribute()
    {
        return $this->delivered_at?->setTimezone($this->timezone ?? config('app.timezone'));
    }

    /**
     * Get returned_at in the assignment's timezone
     */
    public function getReturnedAtInTimezoneAttribute()
    {
        return $this->returned_at?->setTimezone($this->timezone ?? config('app.timezone'));
    }
}
