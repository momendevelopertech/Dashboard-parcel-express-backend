<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantWaybillBatch extends Model
{
    protected $fillable = ['merchant_id', 'created_by', 'quantity'];

    public function waybills()
    {
        return $this->hasMany(MerchantWaybill::class, 'batch_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
