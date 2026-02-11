<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'shipment_id',
        'sequence',
        'latitude',
        'longitude'
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
