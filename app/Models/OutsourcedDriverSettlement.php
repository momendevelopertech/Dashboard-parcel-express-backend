<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OutsourcedDriverSettlement extends Model
{
    protected $fillable = [
        'driver_id',
        'name',
        'phone',
        'amount',
        'deductions',
        'invoice_ref',
    ];

    protected static function booted()
    {
        static::creating(function ($settlement) {
            if (empty($settlement->invoice_ref)) {
                $settlement->invoice_ref = 'SET-' . strtoupper(Str::random(8)) . '-' . time();
            }
        });
    }
}
