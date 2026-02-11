<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOutTaskShipment extends Model
{
    protected $fillable = [
        'stock_out_task_id',
        'shipment_tracking_no',
        'shelf_barcode',
        'status'
    ];

    public function task()
    {
        return $this->belongsTo(StockOutTask::class, 'stock_out_task_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function aots_shipment()
    {
        return $this->belongsTo(AssignShipmentToShelf::class, 'shipment_tracking_no', 'tracking_no');
    }


    public function shelf()
    {
        return $this->belongsTo(Shelf::class, 'shelf_barcode', 'barcode');
    }
}
