<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityAccount extends Model
{
    protected $fillable = [
        "owner_id",
        "owner_type",
        "balance"
    ];
    public $timestamps = true;
    public function owner()
    {
        return $this->morphTo();
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
