<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State_zone extends Model
{
    protected $guarded = [];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
