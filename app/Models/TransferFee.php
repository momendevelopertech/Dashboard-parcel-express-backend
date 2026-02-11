<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferFee extends Model
{
    protected $fillable = [
        'warehouse',
        'amount',
    ];

    public static function getAmount(string $warehouse): float
    {
        return (float) optional(static::query()->where('warehouse', $warehouse)->first())->amount ?? 0.0;
    }
}
