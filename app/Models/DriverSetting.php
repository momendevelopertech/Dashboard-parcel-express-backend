<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverSetting extends Model
{
    protected $fillable = [
        "driver_id",
        "edit_proof",
        "delivery_confirmation_method"
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
