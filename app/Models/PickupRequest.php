<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\{Hub, Station, Branch};

class PickupRequest extends Model
{
    protected $fillable = [
        'merchant_user_id',
        'merchant_pickup_task_id',
        'shipments_count',
        'scheduled_at',
        'status',
        'actual_count',
        'counted_by',
        'counted_at',
        'discrepancy',
        'discrepancy_notified',
        'ref',
    ];

    /**
     * Scope query to current workspace
     * Filters pickup requests by merchant's workspace
     */
    public function scopeByOwner($query)
    {
        $user = Auth::user();

        // Super Admins see everything (consistent with other models)
        // @phpstan-ignore-next-line - isSuperAdmin() is defined in User model
        if ($user && $user->isSuperAdmin()) {
            return $query;
        }

        $workspaceId = request('selected_workspace');
        $workspaceType = request('selected_workspace_type');

        // If no workspace selected, return empty result for non-admins
        if (!$workspaceId || !$workspaceType) {
            return $query;
        }

        // Filter by merchant's workspace through relationship
        return $query->whereHas('merchant', function ($q) use ($workspaceId, $workspaceType) {
            $q->where('owner_id', $workspaceId)
              ->where('owner_type', $workspaceType);
        });
    }
    public static function generateRef(): string
    {
        do {
            $ref = 'REF-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('ref', $ref)->exists());

        return $ref;
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'pickup_request_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }

    public function merchantPickupTask()
    {
        return $this->belongsTo(MerchantPickupTask::class, 'merchant_pickup_task_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function pickupMissedShipmentsTransactions()
    {
        return $this->hasMany(PickupMissedShipmentsTransaction::class, 'pickup_request_id');
    }

    public function getMerchantPickupTaskMissedShipmentsAttribute()
    {
        if (!$this->merchant_pickup_task_id) {
            return collect();
        }
        return \App\Models\PickupMissedShipmentsTransaction::where('pickup_task_id', $this->merchant_pickup_task_id)->get();
    }
}
