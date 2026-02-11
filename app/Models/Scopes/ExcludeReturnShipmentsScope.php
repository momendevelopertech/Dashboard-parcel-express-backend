<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope to exclude return shipments from regular shipment queries.
 * Return shipments should only appear in return-specific endpoints.
 */
class ExcludeReturnShipmentsScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(function ($query) {
            $query->where('is_return', false)
                ->orWhereNull('is_return');
        });
    }
}
