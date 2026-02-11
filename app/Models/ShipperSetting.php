<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipperSetting extends Model
{
    protected $fillable = [
        "shipper_id",
        "failed_ofd_count",
        "rto_days",
    ];

    public function setting()
    {
        return $this->belongsTo(Shipper::class, 'shipper_id');
    }
}
