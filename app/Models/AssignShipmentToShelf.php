<?php

namespace App\Models;

use App\Observers\AssignShipmentToShelfObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AssignShipmentToShelfObserver::class])]
class AssignShipmentToShelf extends Model
{
    use HasFactory;

    protected $fillable = [
        "owner_type",
        "owner_id",
        "assigned_by",
        "tracking_no",
        "barcode",
        "status",
    ];


    public function scopeByOwner($query)
    {
        $facility = facility();

        if ($facility) {
            $query->where('owner_type', $facility->type)
                ->where('owner_id', $facility->id);
        }

        return $query;
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'tracking_no', 'tracking_no');
    }

    public function shelf()
    {
        return $this->belongsTo(Shelf::class, 'barcode', 'barcode');
    }

    public function assigned_by()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeSearchByTrackingNo($query, $search)
    {
        return $query->when($search, function ($q) use ($search) {
            $q->where('tracking_no', $search);
        });
    }

    public function stock_out_task_shipments()
    {
        return $this->hasOne(StockOutTaskShipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function scopeWithoutClosedCrmTasks($query)
    {
        return $query->whereDoesntHave(
            'shipment.crm_task',
            fn($q) =>
            $q->where('status', 'closed')
        );
    }
}
