<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Observers\EmployeeDepartmentObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(EmployeeDepartmentObserver::class)]
class EmployeeDepartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }

    protected $hidden = ['owner_id','owner_type'];
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
