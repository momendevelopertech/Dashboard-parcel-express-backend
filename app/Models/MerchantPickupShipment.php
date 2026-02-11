<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use App\Models\AdminNotificationRead;

class MerchantPickupShipment extends Model
{
    protected $fillable = [
        "pickup_task_id",
        "driver_id",
        "merchant_id",
        "pickup_request_id",
        "shipment_id",
        "shipment_tracking_no",
        "pre_id",
        "status",
        "pickup_proof",
    ];

    public function pickup_task()
    {
        return $this->belongsTo(MerchantPickupTask::class, 'pickup_task_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no')
            ->select(['id', 'tracking_no', 'pre_id', 'status',"created_source"]);
    }

    public function shipment_finance()
    {
        return $this->hasOne(ShipmentFinance::class, 'pickup_shipment_id');
    }

    public function reads()
    {
        return $this->hasMany(AdminNotificationRead::class, 'readable_id');
    }


    // public function owner()
    // {
    //     return $this->morphTo();
    // }
    // public function scopeByOwner($query)
    // {
    //     $user = Auth::user();
    //     $selectedWorkspaceId = request('selected_workspace');

    //     // Allow Super Admins to bypass workspace restrictions
    //     if ($user && $user->isSuperAdmin()) {
    //         return $query;
    //     }

    //     if ($user && $selectedWorkspaceId) {
    //         $query->where(function ($query) use ($selectedWorkspaceId) {
    //             $query->where('owner_type', Branch::class)
    //                 ->where('owner_id', $selectedWorkspaceId)
    //                 ->orWhere(function ($query) use ($selectedWorkspaceId) {
    //                     $query->where('owner_type', Station::class)
    //                         ->where('owner_id', $selectedWorkspaceId);
    //                 })
    //                 ->orWhere(function ($query) use ($selectedWorkspaceId) {
    //                     $query->where('owner_type', Hub::class)
    //                         ->where('owner_id', $selectedWorkspaceId);
    //                 });
    //         });
    //     }

    //     return $query;
    // }

    public function scopeUnregistered($query)
    {
        return $query->whereNull('shipment_id')->whereNotNull('pre_id');
    }

    public function scopeTrackingOnly($query)
    {
        return $query->whereNotNull('shipment_tracking_no');
    }

    public function scopeForPickupTask($query, $pickupTaskId)
    {
        return $query->where('pickup_task_id', $pickupTaskId);
    }

    public function getProofUrlAttribute()
    {
        if (!$this->pickup_proof) {
            return null;
        }

        if (str_starts_with($this->pickup_proof, 'http://') || str_starts_with($this->pickup_proof, 'https://')) {
            return $this->pickup_proof;
        }

        // Use standard Storage::url which generates the correct URL based on the default disk (S3)
        // This matches ShipmentHistory logic exactly
        return Storage::url($this->pickup_proof);
    }
}
