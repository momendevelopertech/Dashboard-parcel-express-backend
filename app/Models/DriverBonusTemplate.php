<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverBonusTemplate extends Model
{
    protected $fillable = [
        'owner_id',
        'owner_type',
        'state_id',
        'delivery_bonus',
        'pickup_bonus',
    ];

    public function owner()
    {
        return $this->morphTo();
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
