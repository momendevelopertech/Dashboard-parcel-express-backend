<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DriverWaybill extends Model
{
    protected $fillable = ['driver_id', 'batch_id', 'tracking_no', 'used'];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DriverWaybillBatch::class, 'batch_id');
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'tracking_no', 'tracking_no');
    }
}
