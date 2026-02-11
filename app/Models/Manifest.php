<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    protected $fillable = [
        'manifest_serial',
        'driver_id',
        'start_date',
        'end_date',
        'total_shipments',
        'total_weight',
        'total_value',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class);
    }

    public function shipments()
    {
        return $this->belongsToMany(Shipment::class);
    }
}
