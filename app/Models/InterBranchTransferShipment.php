<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterBranchTransferShipment extends Model
{
    protected $fillable = ['transfer_id', 'shipment_tracking_no', 'amount' , 'shipment_id'];
    public function transfer()
    {
        return $this->belongsTo(InterBranchTransfer::class, 'transfer_id');
    }

}
