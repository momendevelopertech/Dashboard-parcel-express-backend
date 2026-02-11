<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantAddressBook extends Model
{
    use HasFactory;

    protected $fillable = [
        "merchant_id",
        "name",
        "email",
        "cellphone",
        "alternatePhone",
        "country_id",
        "governorate_id",
        "state_id",
        "place_id",
        "zipcode",
        "streetAddress",
        "location_url",
        "country_key_cellphone",
        "country_key_alternatePhone",
    ];

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

    public function merchant()
    {
        return $this->belongsTo(User::class, "merchant_id");
    }
}
