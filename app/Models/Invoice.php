<?php

namespace App\Models;

use App\Observers\InvoiceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([InvoiceObserver::class])]
class Invoice extends Model
{
    protected $fillable = [
        "owner_type",
        "owner_id",
        "invoiceable_id",
        "invoiceable_type",
        "driver_runsheet_id",
        "invoice_no",
        "status",
        "amount",
        "payment_voucher",
        "driver_payment_proof",
        "paid_to_driver",
        "notes",
    ];

    protected $appends = [
        'total_paid_amount',
        'driver_total_commission'
    ];

    public function getTotalPaidAmountAttribute()
    {
        if ($this->runsheet && $this->runsheet->submissions) {
            return $this->runsheet->submissions->sum('paid_amount');
        }
        return 0;
    }

    public function getDriverTotalCommissionAttribute()
    {
        return $this->invoice_shipments()
            ->with('shipment_finance')
            ->get()
            ->sum(function ($invoiceShipment) {
                return $invoiceShipment->shipment_finance->driver_delivery_fee ?? 0;
            });
    }


    public function getPaymentVoucherUrlAttribute()
    {
        return $this->payment_voucher
            ? Storage::url($this->payment_voucher)
            : null;
    }

    public function invoiceable()
    {
        return $this->morphTo();
    }

    public function invoice_shipments()
    {
        return $this->hasMany(InvoiceShipment::class, 'invoice_id');
    }

    public function runsheet()
    {
        return $this->belongsTo(DriverRunsheet::class, 'driver_runsheet_id');
    }

    public function submission()
    {
        return $this->belongsTo(DriverRunsheetSubmission::class, 'invoice_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }
}
