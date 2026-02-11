<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'consignee_id',
        'country_id',
        'governorate_id',
        'state_id',
        'place_id',
        'city_id',
        'zipcode',
        'streetAddress',
        'longitude',
        'latitude',
        'location_url',
        'label',
        'approved',
        'approved_at',
        'approved_by',
        'times_used',
        'last_used_at',
        'first_approved_shipment_id',
        'is_active',
        'address_signature',
    ];

    protected $casts = [
        'approved' => 'bool',
        'is_active' => 'bool',
        'approved_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_verified' => 'bool',
        'verified_at' => 'datetime',

    ];


    public const VM_OTP = 'OTP_VERIFICATION';
    public const VM_SUPERVISOR = 'SUPERVISOR_APPROVAL';
    public const VM_DELIVERY = 'DELIVERY_LOCATION';

    /* Relations */
    public function consignee()
    {
        return $this->belongsTo(Consignee::class);
    }
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }
    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }
    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }
    public function firstApprovedShipment()
    {
        return $this->belongsTo(Shipment::class, 'first_approved_shipment_id');
    }

    /* Scopes */
    public function scopeForConsignee($q, $consigneeId)
    {
        return $q->where('consignee_id', $consigneeId);
    }

    public function scopeApproved($q)
    {
        return $q->where('approved', true)->where('is_active', true);
    }

    /* Helpers */
    public function approve(int $userId, ?int $shipmentId = null): void
    {
        $updates = [
            'approved' => true,
            'approved_at' => $this->approved_at ?: now(),
            'approved_by' => $this->approved_by ?: $userId,
        ];
        if (!$this->first_approved_shipment_id && $shipmentId) {
            $updates['first_approved_shipment_id'] = $shipmentId;
        }
        $this->fill($updates)->save();
    }
}
