<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeocodeCache extends Model
{
    protected $table = 'geocode_cache';
    protected $fillable = [
        'query',
        'lat',
        'lng',
        'raw_response',
        'confidence',
    ];
}
