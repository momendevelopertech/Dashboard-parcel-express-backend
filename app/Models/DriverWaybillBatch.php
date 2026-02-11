<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverWaybillBatch extends Model
{
    protected $fillable = ['driver_id', 'created_by', 'quantity'];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function waybills(): HasMany
    {
        return $this->hasMany(DriverWaybill::class, 'batch_id');
    }
}
