<?php

namespace App\Models;

use App\Observers\TransferTaskObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([TransferTaskObserver::class])]
class TransferTask extends Model
{
    protected $fillable = [
        'owner_id',
        'owner_type',
        'created_by',
        'truck_driver_id',
        'truck_id',
        'origin_id',
        'origin_type',
        'status',
        'notes',
    ];

    public function truck_driver()
    {
        return $this->belongsTo(TruckDriver::class, 'truck_driver_id');
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class, 'truck_id');
    }

    public function origin()
    {
        return $this->morphTo();
    }

    public function destinations()
    {
        return $this->hasMany(TransferDestination::class, 'transfer_task_id');
    }

    public function scopeByOwner($query)
    {
        $user = Auth::user();

        $selectedWorkspaceId = request('selected_workspace');

        if ($user && $user->isSuperAdmin()) {
            return $query;
        }

        if ($user) {

            if ($user->branch_user && $selectedWorkspaceId) {
                $query->where('owner_type', Branch::class)
                    ->where('owner_id', $selectedWorkspaceId);
            }

            if ($user->station_user && $selectedWorkspaceId) {
                $query->orWhere(function ($query) use ($selectedWorkspaceId) {
                    $query->where('owner_type', Station::class)
                        ->where('owner_id', $selectedWorkspaceId);
                });
            }

            if ($user->hub_user && $selectedWorkspaceId) {
                $query->orWhere(function ($query) use ($selectedWorkspaceId) {
                    $query->where('owner_type', Hub::class)
                        ->where('owner_id', $selectedWorkspaceId);
                });
            }
        }

        return $query;
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function adminReads()
    {
        return $this->hasMany(AdminNotificationRead::class, 'readable_id')->where('read_type', 'transfer_task');
    }
}
