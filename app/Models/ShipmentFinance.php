<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentFinance extends Model
{
    protected $fillable = [
        "shipment_tracking_no",
        'shipment_pre_id',
        "pickup_shipment_id",
        "runsheet_shipment_id",
        "invoice_shipment_id",
        "transfer_task_shipment_id",
        "driver_delivery_bonus",
        "driver_delivery_fee",
        "merchant_balance",
        "status",
        "delivery_fee_paid_by_customer",
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function pickup_shipment()
    {
        return $this->belongsTo(MerchantPickupShipment::class, 'pickup_shipment_id');
    }

    public function runsheet_shipment()
    {
        return $this->belongsTo(DriverRunsheetShipment::class, 'runsheet_shipment_id');
    }

    public function invoice_shipment()
    {
        return $this->hasOne(InvoiceShipment::class, 'invoice_shipment_id');
    }

    public function transfer_task_shipment()
    {
        return $this->belongsTo(TransferTaskShipment::class, 'transfer_task_shipment_id');
    }
}
