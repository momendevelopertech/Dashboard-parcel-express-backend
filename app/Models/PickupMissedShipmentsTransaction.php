<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PickupMissedShipmentsTransaction extends Model
{
    use HasFactory;

    protected $table = 'pickup_missed_shipments_transaction';

    protected $fillable = [
        'shipment_id',
        'pickup_task_id',
        'merchant_id',
        'driver_id',
        'pickup_request_id',
        'note',
        'proof_path',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function pickupTask()
    {
        return $this->belongsTo(MerchantPickupTask::class, 'pickup_task_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}


