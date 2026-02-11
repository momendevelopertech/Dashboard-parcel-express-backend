<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Observers\HubObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(HubObserver::class)]
class Hub extends Model
{
    protected $fillable = [
        'name',
        'location',
        'contact_number',
        'country_id',
        'governorate_id',
        'state_id',
        'place_id',
        'city_id',
        'lat',
        'lng',
        'address',
        'timezone',
    ];

    public function hub_users()
    {
        return $this->hasMany(HubUser::class, 'hub_id');
    }
    public function accessScope()
    {
        return $this->morphOne(AccessScope::class, 'owner');
    }
    public function stations()
    {
        return $this->hasMany(Station::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function drivers()
    {
        return $this->morphMany(Driver::class, 'owner');
    }

    public function expenses()
    {
        return $this->morphMany(Expense::class, 'owner');
    }

    public function zone_shipments()
    {
        return $this->morphMany(ZoneShipment::class, 'owner');
    }

    public function transfer_shipments()
    {
        return $this->morphMany(TransferShipment::class, 'ownership');
    }

    public function zones()
    {
        return $this->morphMany(Zone::class, 'owner');
    }

    public function owner_branches()
    {
        return $this->morphMany(Branch::class, 'owner');
    }

    public function owner_stations()
    {
        return $this->morphMany(Station::class, 'owner');
    }

    public function owner_shelves()
    {
        return $this->morphMany(Shelf::class, 'owner');
    }

    public function scopeByOwner($query)
    {
        $user = Auth::user();

        if ($user) {
            if ($user->branch_user && $branch_id = $user->branch_user->branch_id) {
                $query->where('owner_type', Branch::class)
                    ->where('owner_id', $branch_id);
            }

            if ($user->station_user && $station_id = $user->station_user->station_id) {
                $query->where('owner_type', Station::class)
                    ->where('owner_id', $station_id);
            }
            if ($user->hub_user && $hub_id = $user->hub_user->hub_id) {
                $query->where('owner_type', Hub::class)
                    ->where('owner_id', $hub_id);
            }
        }
        return $query;
    }

    public function employeeBranches()
    {
        return $this->morphMany(EmployeeBranch::class, 'morphable');
    }
}
