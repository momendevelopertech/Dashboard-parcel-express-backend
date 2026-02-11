<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferTaskShipment extends Model
{
    protected $fillable = [
        'transfer_shipment_id',
        'transfer_task_id',
        'transfer_destination_id',
        'shipment_tracking_no',
        'truck_barcode',
        'status',
        'loaded_at'
    ];

    public function task()
    {
        return $this->belongsTo(TransferTask::class, 'transfer_task_id');
    }

    public function destination()
    {
        return $this->belongsTo(TransferDestination::class, 'transfer_destination_id');
    }

    public function transfer_shipment()
    {
        return $this->belongsTo(TransferShipment::class, 'transfer_shipment_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class, 'truck_barcode', 'barcode');
    }

    public function shipment_finance()
    {
        return $this->hasOne(ShipmentFinance::class, 'transfer_task_shipment_id');
    }
}
