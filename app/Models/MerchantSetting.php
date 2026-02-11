<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantSetting extends Model
{
    protected $fillable = [
        'merchant_id',
        'notifications',
        'created_shipment_notification',
    ];

    protected $casts = [
        'notifications' => 'array',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
