<?php

namespace App\Observers;
use App\Models\Hub;
use App\Models\Branch;
use Illuminate\Http\Request;
use App\Models\DriverCommission;
use Illuminate\Support\Facades\Auth;

class DriverCommissionObserver
{
    
    public function creating(DriverCommission $commission)
    {
        $user = Auth::user();

        if ($user) {
            if ($user->branch_user && $user->branch_user->branch) {
                $commission->owner()->associate($user->branch_user->branch);
            }

            if ($user->station_user && $user->station_user->station) {
                $commission->owner()->associate($user->station_user->station);
            }

            if ($user->hub_user && $user->hub_user->hub) {
                $commission->owner()->associate($user->hub_user->hub);
            }
        }
    }
}
