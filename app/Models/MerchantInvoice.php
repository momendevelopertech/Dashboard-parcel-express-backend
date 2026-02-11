<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantInvoice extends Model
{
    protected $guarded=[];

    public function merchant(){
        return $this->belongsTo(User::class,"merchant_id");
    }

        public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
