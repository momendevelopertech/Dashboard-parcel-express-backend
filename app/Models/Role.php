<?php

namespace App\Models;

use App\Observers\RoleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as SpatieRole;
#[ObservedBy([RoleObserver::class])]
class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'roleable_type', 'roleable_id'];
    public static function create(array $attributes = [])
    {
        $role = static::query()->create($attributes);

        return $role;
    }

    public function scopeByUser($query)
    {
        $user = Auth::user();
        if (!$user) {
            return $query;
        }

        $facility = facility(); // stdClass with id & type

        if ($facility) {
            if ($facility->type === \App\Models\Station::class) {
                // Load the Station from DB to get its Hub
                $station = \App\Models\Station::with('hub')->find($facility->id);
                if ($station && $station->hub) {
                    $query->where('roleable_type', \App\Models\Hub::class)
                        ->where('roleable_id', $station->hub->id);
                }
            } else {
                // Facility is a Hub
                $query->where('roleable_type', $facility->type)
                    ->where('roleable_id', $facility->id);
            }
        }

        return $query;
    }




    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function roleable()
    {
        return $this->morphTo();
    }
}
