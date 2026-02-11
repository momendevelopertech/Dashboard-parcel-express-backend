<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StateChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'internal_state_id',
        'internal_state_name',
        'external_state_id',
        'external_state_name',
    ];

    public function shipper()
    {
        return $this->belongsTo(Shipper::class, 'shipper_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'internal_state_id');
    }
}
