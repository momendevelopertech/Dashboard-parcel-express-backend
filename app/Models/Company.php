<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "payment_proof_required"
    ];

    public function driver()
    {
        return $this->hasOne(Driver::class, 'company_id')->orderBy('id', 'desc');
    }

    public function drivers()
    {
        return $this->hasMany(Driver::class, 'company_id');
    }

    public function country_channels()
    {
        return $this->hasMany(CountryChannel::class, 'company_id');
    }

    public function governorate_channels()
    {
        return $this->hasMany(GovernorateChannel::class, 'company_id');
    }

    public function state_channels()
    {
        return $this->hasMany(StateChannel::class, 'company_id');
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
}
