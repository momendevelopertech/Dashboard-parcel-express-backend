<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FeeAllocation extends Model
{
    protected $fillable = [
        'shipment_id',
        'amount',
        'recipient_name',
        'recipient_id',
        'recipient_type'
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
