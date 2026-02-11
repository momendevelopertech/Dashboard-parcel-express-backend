<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverAccount extends Model
{
    protected $fillable = [
        "driver_id",
        "balance"
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function getTransactionsAttribute()
    {
        return $this->from_transactions->merge($this->to_transactions);
    }

    public function from_transactions()
    {
        return $this->morphMany(Transaction::class, 'from_account');
    }

    public function to_transactions()
    {
        return $this->morphMany(Transaction::class, 'to_account');
    }
}
