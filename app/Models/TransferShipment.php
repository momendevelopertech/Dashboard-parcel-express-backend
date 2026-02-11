<?php

namespace App\Models;

use App\Observers\TransferShipmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([TransferShipmentObserver::class])]
class TransferShipment extends Model
{
    protected $fillable = [
        "status",
        "ownership_id",
        "ownership_type",
        "owner_id",
        "owner_type",
        "shipment_tracking_no"
    ];

    public function ownership()
    {
        return $this->morphTo();
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function transfer_task_shipment()
    {
        return $this->hasOne(TransferTaskShipment::class, foreignKey: 'transfer_shipment_id');
    }

    public function scopeByOwner($query)
    {
        $facility = facility();

        if ($facility) {
            $query->where('ownership_type', $facility->type)
                ->where('ownership_id', $facility->id);
        }

        return $query;
    }

    public function scopePendingUnassigned($query)
    {
        return $query->where('status', 'pending')
                    ->whereDoesntHave('transfer_task_shipment');
    }

    public function adminReads()
    {
        return $this->hasMany(AdminNotificationRead::class, 'readable_id')->where('read_type', 'transfer_shipment');
    }
}
