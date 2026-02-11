<?php

namespace App\Models\Scopes;

use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ShipmentScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user) {
            // Apply scope based on branch
            if ($user->branch_user && $branch_id = $user->branch_user->branch_id) {
                $builder->where(function ($query) use ($branch_id) {
                    $query->where('owner_id', $branch_id)
                          ->where('owner_type', Branch::class);
                });
            }

            // Apply scope based on station
            if ($user->station_user && $station_id = $user->station_user->station_id) {
                $builder->orWhere(function ($query) use ($station_id) {
                    $query->where('owner_id', $station_id)
                          ->where('owner_type', Station::class);
                });
            }

            // Apply scope based on hub
            if ($user->hub_user && $hub_id = $user->hub_user->hub_id) {
                $builder->orWhere(function ($query) use ($hub_id) {
                    $query->where('owner_id', $hub_id)
                          ->where('owner_type', Hub::class);
                });
            }
            $hub_id = request('selected_hub');
           
            if ($user->hub_user && $hub_id) {
                $query->where('owner_type', Hub::class)
                    ->where('owner_id', $hub_id);
            }
        }
    }
}
