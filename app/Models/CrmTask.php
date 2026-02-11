<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CrmTask extends Model
{
    /** @use HasFactory<\Database\Factories\CrmTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'title',
        'status',
        'note',
        'updated_by',

    ];


    public function owner()
    {
        return $this->morphTo();
    }


    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function scopeByOwner($query)
    {
        $user = Auth::user();
        $selectedWorkspaceId = request('selected_workspace');

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
