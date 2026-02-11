<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        "country_id",
        "governorate_id",
        "state_id",
        "place_id",
        "name",
        "email",
        "cellphone",
        "zipcode",
        "streetAddress",
        "longitude",
        "latitude",
        "location",
        "allow_return",
        "delivery_priority", 
        "delivery_time",
        "sender_district",
        "sender_location_url",
        "sender_notes", 
        "sender_streetAddress",
        "sender_zipcode"
    ];

    public function owner()
    {
        return $this->morphTo();
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
}
