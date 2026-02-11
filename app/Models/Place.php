<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    use HasFactory;
    protected $fillable = [
        "state_id",
        "en_name",
        "ar_name",
        "isActive",
        "lat",
        "lng",
    ];

    protected $casts = [
        'isActive' => 'boolean',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    /**
     * Merchants that are located in this place.
     */
    public function merchants()
    {
        return $this->hasMany(Merchant::class, 'place_id');
    }

    /**
     * Consignees that are located in this place.
     */
    public function consignees()
    {
        return $this->hasMany(Consignee::class, 'place_id');
    }
}
