<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'internal_country_id',
        'internal_country_name',
        'external_country_id',
        'external_country_name',
    ];

    public function shipper()
    {
        return $this->belongsTo(Shipper::class, 'shipper_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'internal_country_id');
    }
}
