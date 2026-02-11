<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OldAddress extends Model
{
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
        'location',
        'approved',
        'rejected',
        'approved_by',
        'approved_at',
        'comments',
        'shipment_id',
    ];

    protected $casts = [
        'approved' => 'boolean',
        'rejected' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function consignee()
    {
        return $this->belongsTo(Consignee::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function approved_by()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
