<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverFile extends Model
{
    protected $fillable = [
        "driver_id",
        "name",
        "file",
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
