<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Builder;
class CommissionTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'owner_type',
        'country_id',
        'state_id',
        'base_delivery_fee',
        'base_return_fee',
        'delivery_discount_amount',
        'return_discount_amount',
        'delivery_fee',
        'return_fee',
    ];
    protected static function booted()
    {
        // نطاق المالك
        static::addGlobalScope('scopeByOwner', function (Builder $q) {
            try {
                $oid = facility('id');     // نفس الهيلبر اللي عندك
                $otype = facility('type');
                if ($oid && $otype) {
                    $q->where('owner_id', $oid)->where('owner_type', $otype);
                }
            } catch (\Throwable $e) {
            }
        });

        static::creating(function (self $model) {
            if (empty($model->owner_id) || empty($model->owner_type)) {
                $oid = facility('id');
                $otype = facility('type');
                $model->owner_id = $model->owner_id ?: $oid;
                $model->owner_type = $model->owner_type ?: $otype;
            }
        });
    }
    public function state()
    {
        return $this->belongsTo(\App\Models\State::class);
    }
    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class);
    }
}
