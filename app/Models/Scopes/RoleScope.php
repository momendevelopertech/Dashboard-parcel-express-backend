<?php

namespace App\Models\Scopes;

use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class RoleScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $user = Auth::user();
        if ($user) {
            if ($user->branch_user && $user->branch_user->scopeable instanceof Branch) {
                $builder->where('scopeable_id', $user->branch_user->scopeable->id);
            }

            if ($user->station_user && $user->station_user->scopeable instanceof Station) {
                $builder->where('scopeable_id', $user->station_user->scopeable->id);
            }

            if ($user->hub_user && $user->hub_user->scopeable instanceof Hub) {
                $builder->where('scopeable_id', $user->hub_user->scopeable->id);
            }
        }
    }
}
