<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        "merchant_id",
        "country_id",
        "state_id",
        "base_delivery_fee",
        "base_return_fee",
        "delivery_discount_amount",
        "return_discount_amount",
        "delivery_fee",
        "return_fee",

        
    ];
    protected $casts = [
        'base_delivery_fee' => 'decimal:3',
        'base_return_fee' => 'decimal:3',
        'delivery_discount_amount' => 'decimal:3',
        'return_discount_amount' => 'decimal:3',
        'delivery_fee' => 'decimal:3',
        'return_fee' => 'decimal:3',
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }
}
