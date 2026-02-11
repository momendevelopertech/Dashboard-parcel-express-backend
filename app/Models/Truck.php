<?php

namespace App\Models;

use App\Observers\TruckObserver;
use App\Traits\LogsUserActions;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([TruckObserver::class])]
class Truck extends Model
{
    use LogsUserActions;

    protected $fillable = [
        'owner_id',
        'owner_type',
        "barcode",
        "number_plate",
        "color",
        "company",
        "notes",
        "truck_driver_id",
        "type",
        "status",
    ];

    public function name()
    {
        return $this->number_plate . " / " . $this->company;
    }

    public function truck_driver()
    {
        return $this->belongsTo(User::class, 'truck_driver_id');
    }

    public function transfer_tasks()
    {
        return $this->hasMany(TransferTask::class, 'truck_id');
    }

    public function transfer_task_shipments()
    {
        return $this->hasMany(TransferTaskShipment::class, 'truck_barcode', 'barcode');
    }

    public function scopeByOwner($query)
    {
        $facility = facility();

        if ($facility) {
            $query->where('owner_type', $facility->type)
                ->where('owner_id', $facility->id);
        }

        return $query;
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function truck_status()
    {
        return $this->hasOne(TruckStatus::class, 'truck_id');
    }

    public function maintenance_schedules()
    {
        return $this->hasMany(MaintenanceSchedule::class, 'truck_id');
    }
}
