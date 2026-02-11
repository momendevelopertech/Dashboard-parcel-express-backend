<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReversePickupShipment extends Model
{


    protected $casts = [
        'pickup_proofs' => 'array',
    ];
    
    protected $fillable = [
        'reverse_pickup_task_id',
        'reverse_pickup_request_id',
        'reverse_shipment_id',
        'driver_id',
        'merchant_id',
        'status',
        'pickup_proofs',
    ];

    /**
     * Relationships
     */
    public function reversePickupTask()
    {
        return $this->belongsTo(ReversePickupTask::class, 'reverse_pickup_task_id');
    }

    public function reversePickupRequest()
    {
        return $this->belongsTo(ReversePickupRequest::class, 'reverse_pickup_request_id');
    }

    public function reverseShipment()
    {
        return $this->belongsTo(ReverseShipment::class, 'reverse_shipment_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    /**
     * Accessors
     */
    public function getProofUrlAttribute()
    {
        if (!$this->pickup_proof) {
            return null;
        }

        if (str_starts_with($this->pickup_proof, 'http://') || str_starts_with($this->pickup_proof, 'https://')) {
            return $this->pickup_proof;
        }

        // Use standard Storage::url which generates the correct URL based on the default disk (S3)
        return Storage::url($this->pickup_proof);
    }

    /**
     * Scopes
     */
    public function scopeForPickupTask($query, $pickupTaskId)
    {
        return $query->where('reverse_pickup_task_id', $pickupTaskId);
    }

    public function scopePicked($query)
    {
        return $query->where('status', 'picked');
    }

    public function scopeToPickup($query)
    {
        return $query->where('status', 'to_pickup');
    }
}
