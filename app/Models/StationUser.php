<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StationUser extends Model
{
    protected $fillable = [
        "user_id",
        "station_id",
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }

    public function stations()
    {
        return $this->belongsToMany(Station::class, 'station_users', 'user_id', 'station_id');
    }
}
