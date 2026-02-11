<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceShipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        "invoice_id",
        "shipment_tracking_no",
        "status",
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    // public function shipment_finance()
    // {
    //     return $this->belongsTo(ShipmentFinance::class, 'invoice_shipment_id');
    // }

    public function shipment_finance()
    {
        return $this->hasOne(ShipmentFinance::class, 'shipment_tracking_no', 'shipment_tracking_no');
    }
}
