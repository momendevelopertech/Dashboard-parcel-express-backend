<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverInvoice extends Model
{
    protected $guarded=[];

    public function driver(){
        return $this->belongsTo(User::class,"driver_id");
    }

        public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
