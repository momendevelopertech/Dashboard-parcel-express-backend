<?php

namespace App\Models;

use App\Observers\TruckDriverObserver;
use App\Traits\LogsUserActions;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([TruckDriverObserver::class])]
class TruckDriver extends Model
{
    use LogsUserActions;
    protected $fillable = [
        'owner_id',
        'owner_type',
        "user_id",
        "country_code",
        "phone_number",
        "id_card_number",
        "company",
        "status"
    ];

    protected $hidden = [
        'password',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trucks()
    {
        return $this->hasMany(Truck::class, 'truck_driver_id');
    }

    public function transfer_tasks()
    {
        return $this->hasMany(TransferTask::class, 'truck_driver_id');
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
}
