<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverRelative extends Model
{
    protected $fillable = [
        "driver_id",
        "name",
        "phone",
        "relation",
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
