<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantWaybill extends Model
{
    use HasFactory;

    protected $fillable = [
        "merchant_id",
        "tracking_no",
        "used",
        "batch_id"
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class, 'tracking_no', 'tracking_no');
    }

    public function scopeUnused($q)
    {
        return $q->where('used', false);
    }

    public function scopeSearchTracking($q, $term)
    {
        $term = strtolower($term);
        return $q->whereRaw('LOWER(tracking_no) LIKE ?', ["%{$term}%"]);
    }
    public function batch()
    {
        return $this->belongsTo(MerchantWaybillBatch::class, 'batch_id');
    }
}
