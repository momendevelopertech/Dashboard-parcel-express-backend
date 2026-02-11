<?php

namespace App\Models;

use App\Models\Scopes\ShipperScope;
use App\Observers\ShipperObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([ShipperObserver::class])]
class Shipper extends Model
{
    use HasFactory;

    public static $current_shipment_id;

    // protected static function booted()
    // {
    //     static::addGlobalScope(new ShipperScope());
    // }

    protected $fillable = [
        'name',
        'email',
        'country_key_contact',
        'contact',
        'alternative_country_key_contact',
        'alternative_contact',
        'country_id',
        'state_id',
        // 'city_id',
        'governorate_id',
        'place_id',
        'address',
        'zip_code',
        'website',
        'notes',
        'is_active'
    ];

    public function scopePe() {
        return $this->where('email', 'pe@gmail.com')->first();
    }

    public function setting()
    {
        return $this->hasOne(ShipperSetting::class, 'shipper_id');
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

    public function owner()
    {
        return $this->morphTo();
    }

    public function accountable()
    {
        return $this->morphOne(Account::class, 'accountable');
    }

    public function scopeByOwner($query)
    {
        $facility = facility();

        if ($facility) {
            $query->where('owner_type', $facility->type)
                ->where('owner_id', $facility->id);
        }

        return $query;
    }
}
