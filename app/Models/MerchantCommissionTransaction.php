<?php

namespace App\Models;

use App\Models\User;
use App\Models\State;
use App\Models\Country;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MerchantCommissionTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'merchant_id',
        'country_id',
        'state_id',
        'base_delivery_fee',
        'base_return_fee',
        'delivery_discount_amount',
        'return_discount_amount',
        'delivery_fee',
        'return_fee',
        'shipment_gross_amount',
        'shipment_net_amount',
        'fee_payer',
    ];

    protected $casts = [
        'base_delivery_fee' => 'decimal:3',
        'base_return_fee' => 'decimal:3',
        'delivery_discount_amount' => 'decimal:3',
        'return_discount_amount' => 'decimal:3',
        'delivery_fee' => 'decimal:3',
        'shipment_gross_amount' => 'decimal:3',
        'shipment_net_amount' => 'decimal:3',
        'return_fee' => 'decimal:3',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function merchant()
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


