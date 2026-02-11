<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;
    protected $fillable = [
        "state_id",
        "name"
    ];

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function shippers()
    {
        return $this->hasMany(Shipper::class, 'city_id');
    }

    public function consignees()
    {
        return $this->hasMany(Consignee::class, 'state_id');
    }
}
