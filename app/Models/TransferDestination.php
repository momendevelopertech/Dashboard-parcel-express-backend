<?php

namespace App\Models;

use App\Observers\TransferDestinationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([TransferDestinationObserver::class])]
class TransferDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'owner_type',
        'transfer_task_id',
        'origin_id',
        'origin_type',
        'destination_id',
        'destination_type',
        'status',
        'loaded_at'
    ];

    public function task()
    {
        return $this->belongsTo(TransferTask::class, 'transfer_task_id');
    }

    public function origin()
    {
        return $this->morphTo();
    }

    public function destination()
    {
        return $this->morphTo();
    }

    public function countDestinationShipments()
    {
        return $this->destinationShipments()->count();
    }


    public function destinationShipments()
    {
        return $this->hasMany(TransferTaskShipment::class, 'transfer_destination_id');
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
