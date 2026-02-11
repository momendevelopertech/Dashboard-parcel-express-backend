<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReversePickupRequest extends Model
{
    use HasFactory;

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
     */
    public static function generateRef(): string
    {
        do {
            $ref = 'REV-REQ-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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

    public function reverseTasks()
    {
        return $this->hasMany(ReversePickupTask::class, 'reverse_pickup_request_id');
    }

    public function reverseShipments()
    {
        return $this->hasMany(ReverseShipment::class, 'reverse_pickup_request_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }


    public function scenarios()
    {
        return $this->hasMany(ReversePickupRequestShipment::class);
    }
}
