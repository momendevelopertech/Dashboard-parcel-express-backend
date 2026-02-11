<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        "accountable_id",
        "accountable_type",
        "parcel_value",
        "cash_balance",
        "paid_balance",
    ];

    public function accountable()
    {
        return $this->morphTo();
    }

    public function sent_transactions()
    {
        return $this->morphMany(Transaction::class, 'from');
    }

    public function received_transactions()
    {
        return $this->morphMany(Transaction::class, 'to');
    }

    public function transactions()
    {
        return $this->sentTransactions->merge($this->receivedTransactions);
    }
}