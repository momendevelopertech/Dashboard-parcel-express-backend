<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ShipperCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        "shipper_id",
        "state_id",
        "delivery_fee",
    ];

    public function shipper()
    {
        return $this->belongsTo(Shipper::class, 'shipper_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
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
                $query->orWhere(function ($query) use ($station_id) {
                    $query->where('owner_type', Station::class)
                        ->where('owner_id', $station_id);
                });
            }

            if ($user->hub_user && $hub_id = $user->hub_user->hub_id) {
                $query->orWhere(function ($query) use ($hub_id) {
                    $query->where('owner_type', Hub::class)
                        ->where('owner_id', $hub_id);
                });
            }
        }

        return $query;
    }
}
