<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReversePickupRequestShipment extends Model
{
    protected $fillable = [
        'reverse_pickup_request_id',
        'reverse_shipment_ids',
        'want_receive_at',
        'is_hub_receive',
    ];

    protected $casts = [
        'reverse_shipment_ids' => 'array', // auto JSON encode/decode
        'want_receive_at' => 'datetime',
        'is_hub_receive' => 'boolean',
    ];

    public function reversePickupRequest()
    {
        return $this->belongsTo(ReversePickupRequest::class);
    }
}
