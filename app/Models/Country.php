<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;
    
    protected $fillable = [
        "name",
        "code",
        "phonecode",
    ];

    public function states()
    {
        return $this->hasMany(State::class, 'country_id');
    }

    public function cities()
    {
        return $this->hasManyThrough(City::class, State::class, 'state_id');
    }

    public function shippers()
    {
        return $this->hasMany(Shipper::class, 'country_id');
    }

    public function consignees()
    {
        return $this->hasMany(Consignee::class, 'country_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class,'country_id');
    }

    public function governorates()
    {
        return $this->hasMany(Governorate::class);
    }

    public function merchant_commissions()
    {
        return $this->hasMany(MerchantCommission::class, 'country_id');
    }

    public function channels()
    {
        return $this->hasMany(CountryChannel::class, 'internal_country_id');
    }
}
