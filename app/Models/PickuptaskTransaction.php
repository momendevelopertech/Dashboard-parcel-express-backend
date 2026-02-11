<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickuptaskTransaction extends Model
{
    protected $table = 'pickuptask_transactions';
    protected $guarded=[];


    public function pickuptask(){
        return $this->belongsTo(MerchantPickupTask::class,"pickuptask_id");
    }
}
