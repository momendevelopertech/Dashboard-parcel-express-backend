<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverRunsheetSubmission extends Model
{
    protected $fillable = [
        "driver_runsheet_id",
        "invoice_id",
        "total_amount",
        "paid_by_cash",
        "paid_by_bank",
        "paid_amount",
        "received_by",
        "notes",
    ];

    public function runsheet()
    {
        return $this->belongsTo(DriverRunsheet::class, 'driver_runsheet_id', 'id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function received_by()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
