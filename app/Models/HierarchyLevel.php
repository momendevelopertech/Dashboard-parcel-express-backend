<?php

namespace App\Models;

use App\Observers\HierarchyLevelObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(HierarchyLevelObserver::class)]
class HierarchyLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_name',
        'level',
        'description',
    ];
    protected $hidden = ['owner_id','owner_type'];
    public function employeeHierarchy()
    {
        return $this->hasMany(EmployeeHierarchy::class);
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function scopeByOwner($query)
    {
        $user = Auth::user();
        $selectedWorkspaceId = request('selected_workspace');
    
        // Allow Super Admins to bypass workspace restrictions
        if ($user && $user->isSuperAdmin()) {
            return $query;
        }
    
        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                $query->where('owner_type', Branch::class)
                    ->where('owner_id', $selectedWorkspaceId);
            }
    
            if ($user->station_user) {
                $query->orWhere(function ($query) use ($selectedWorkspaceId) {
                    $query->where('owner_type', Station::class)
                        ->where('owner_id', $selectedWorkspaceId);
                });
            }
    
            if ($user->hub_user) {
                $query->orWhere(function ($query) use ($selectedWorkspaceId) {
                    $query->where('owner_type', Hub::class)
                        ->where('owner_id', $selectedWorkspaceId);
                });
            }
        }
    
        return $query;
    }
}
