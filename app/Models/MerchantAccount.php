<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantAccount extends Model
{
    protected $fillable = [
        "merchant_id",
        "balance"
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function transactions()
    {
        return MerchantTransaction::forMerchant($this->merchant_id)->completed()->get();
    }

    /**
     * @deprecated Use unified MerchantTransaction instead
     */
    public function from_transactions()
    {
        return $this->morphMany(Transaction::class, 'from_account');
    }

    /**
     * @deprecated Use unified MerchantTransaction instead
     */
    public function to_transactions()
    {
        return $this->morphMany(Transaction::class, 'to_account');
    }
}
