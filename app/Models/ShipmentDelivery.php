<?php

namespace App\Models;

use App\Events\OfdCountChanged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'driver_id',
        'assignment_id',
        'driver_call_count',
        'proof',
        'ofd_count',
        'future_delivery_date',
        'payment_cash',
        'payment_bank_transfer',
        'delivery_lat',
        'delivery_lng',
        'correct_address_link',
        'deliver_later_until',
        'deliver_later_reason',

        'delivery_otp',
        'otp_generated_at',
        'otp_attempts',
        'otp_verified_address_for',
        'otp_verified_address',
        'otp_verified',
    ];

    protected $casts = [
        'future_delivery_date' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function assignment()
    {
        return $this->belongsTo(DriverShipmentAssignment::class, 'assignment_id');
    }

    public function addOFDCount()
    {
        $this->ofd_count++;
        $this->save();
    }
    protected static function booted()
    {
        static::updated(function ($shipmentDelivery) {
            if ($shipmentDelivery->wasChanged('ofd_count')) {
                event(new OfdCountChanged(
                    $shipmentDelivery,
                    $shipmentDelivery->getOriginal('ofd_count'),
                    $shipmentDelivery->ofd_count,
                ));
            }
        });
    }
}
