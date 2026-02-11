<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{

    protected $fillable = ['name', 'guard_name', 'type']; // Add 'type' to fillable attributes

    public static function create(array $attributes = [])
    {
        $permission = static::query()->firstOrCreate([
            'name' => $attributes['name'],
            'guard_name' => $attributes['guard_name'],
            'type' => $attributes['type'],
        ], $attributes);

        return $permission;
    }

    /**
     * Get the parent permission (self-referential relationship).
     */
    public function parent()
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }

    /**
     * Get the child permissions (self-referential relationship).
     */
    public function children()
    {
        return $this->hasMany(Permission::class, 'parent_id');
    }


    public function scopeByOwner($query)
    {
        $user = Auth::user();

        if (!$user) {
            return $query;
        }

        // need to modify according to admin role
        if ($user->hasRole("Super Admin")) {
            return $query;
        }


        if ($user->branch_user) {
            return $query->where('type', 'branch');
        }

        if ($user->station_user) {
            return $query->where('type', 'station');
        }

        if ($user->hub_user) {
            return $query->where('type', 'hub');
        }


        return $query;
    }


}
