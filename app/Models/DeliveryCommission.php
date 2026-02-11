<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        "driver_id",
        "state_id",
        "amount",
    ];

    public function state()
    {
        return $this->belongsTo(State::class, 'zone_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
