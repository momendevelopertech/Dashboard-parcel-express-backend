<?php

namespace App\Models;

use App\Enums\ReverseShipmentStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReverseShipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_no',
        'parent_shipment_id',
        'original_tracking_no',
        'type',
        'sender_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'consignee_id',
        'latitude',
        'longitude',
        'location_url',
        'receiver_id',
        'merchant_id',
        'sender_address_id',
        'receiver_address_id',
        'status',
        'pricing_calculated',
        'driver_commission',
        'merchant_fee_charged',
        'reverse_pickup_request_id',
        'picked_at',
        'delivered_to_merchant_at',
    ];

    protected $casts = [
        'pricing_calculated' => 'array',
        'driver_commission' => 'decimal:3',
        'merchant_fee_charged' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'picked_at' => 'datetime',
        'delivered_to_merchant_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reverseShipment) {
            if (!$reverseShipment->tracking_no) {
                $reverseShipment->tracking_no = static::generateTrackingNumber();
            }
            if (!$reverseShipment->type) {
                $reverseShipment->type = 'reverse_pickup';
            }
            if (!$reverseShipment->status) {
                $reverseShipment->status = ReverseShipmentStatusEnum::REVERSE_CREATED;
            }
        });
    }

    /**
     * Generate unique tracking number
     * Format: RET-PE{YYMMDD}{6-digit-random}
     */
    public static function generateTrackingNumber(): string
    {
        do {
            $date = now()->format('ymd');
            $random = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $tracking = 'RET-PE' . $date . $random;
        } while (static::where('tracking_no', $tracking)->exists());

        return $tracking;
    }

    /**
     * Relationships
     */
   public function shipmentHistories()
    {
        return $this->hasMany(ShipmentHistory::class, 'shipment_id')->orderBy('id', 'desc');
    }

    public function parentShipment()
    {
        return $this->belongsTo(Shipment::class, 'parent_shipment_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function senderAddress()
    {
        return $this->belongsTo(Address::class, 'sender_address_id');
    }

    public function receiverAddress()
    {
        return $this->belongsTo(Address::class, 'receiver_address_id');
    }

    public function reversePickupRequest()
    {
        return $this->belongsTo(ReversePickupRequest::class, 'reverse_pickup_request_id');
    }

    public function transactions()
    {
        return $this->hasMany(ReversePickupTransaction::class, 'reverse_shipment_id');
    }

    public function reversePickupShipment()
    {
        return $this->hasOne(ReversePickupShipment::class, 'reverse_shipment_id');
    }

    public function consignee()
    {
        return $this->belongsTo(Consignee::class, 'consignee_id');
    }

    /**
     * Reuse existing shipment history for timeline tracking
     */
    public function histories()
    {
        return $this->morphMany(ShipmentHistory::class, 'shipment');
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

    public function scopeNotCharged($query)
    {
        return $query->where('merchant_fee_charged', false);
    }

    public function scopeCharged($query)
    {
        return $query->where('merchant_fee_charged', true);
    }
}
