<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use App\Models\Branch;
use App\Models\Station;
use App\Models\Hub;

class ShipperScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        $selectedWorkspaceId = request('selected_workspace');

        // Allow super admins to see all data
        if ($user && $user->isSuperAdmin()) {
            return;
        }

        if ($user && $selectedWorkspaceId) {
            $builder->where(function ($query) use ($user, $selectedWorkspaceId) {
                if ($user->branch_user) {
                    $query->where('owner_type', Branch::class)
                          ->where('owner_id', $selectedWorkspaceId);
                }

                if ($user->station_user) {
                    $query->orWhere(function ($q) use ($selectedWorkspaceId) {
                        $q->where('owner_type', Station::class)
                          ->where('owner_id', $selectedWorkspaceId);
                    });
                }

                if ($user->hub_user) {
                    $query->orWhere(function ($q) use ($selectedWorkspaceId) {
                        $q->where('owner_type', Hub::class)
                          ->where('owner_id', $selectedWorkspaceId);
                    });
                }
            });
        }
    }
}
