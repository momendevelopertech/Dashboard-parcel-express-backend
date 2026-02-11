<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ReturnRequest Model
 * 
 * Represents a merchant's request to pick up returned items from customers.
 * Replaces the old ReversePickupRequest model with clearer naming.
 * 
 * Relationships:
 * - Has many shipments (return shipments)
 * - Has many pickup tasks (for driver assignment)
 * - Belongs to merchant (user)
 * - Belongs to customer (user)
 */
class ReturnRequest extends Model
{
    use HasFactory;

    protected $table = 'return_requests';

    protected $fillable = [
        'ref',
        'original_shipment_id',
        'customer_id',
        'merchant_id',
        'pickup_address_id',
        'delivery_address_id',
        'no_of_shipments',
        'picked_shipments_no',
        'scheduled_at',
        'status',
        'pricing_calculated',
        'note',
        'owner_id',
        'owner_type',
    ];

    protected $casts = [
        'pricing_calculated' => 'array',
        'scheduled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (!$request->ref) {
                $request->ref = static::generateRef();
            }
        });
    }

    /**
     * Generate unique reference number
     * Format: REQ-{6-digit-random}
     */
    public static function generateRef(): string
    {
        do {
            $ref = 'REQ-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('ref', $ref)->exists());

        return $ref;
    }

    /**
     * Relationships
     */
    public function originalShipment()
    {
        return $this->belongsTo(Shipment::class, 'original_shipment_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function pickupAddress()
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    public function deliveryAddress()
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    /**
     * Return shipments created from this request
     */
    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'return_request_id')
            ->where('is_return', true);
    }

    /**
     * Pickup tasks for driver assignment
     */
    public function pickupTasks()
    {
        return $this->hasMany(PickupTask::class, 'return_request_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }

    /**
     * Scopes
     */
    public function scopeByMerchant($query, $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
