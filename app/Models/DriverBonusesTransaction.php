<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverBonusesTransaction extends Model
{
    protected $table = 'driver_bonuses_transactions';

    protected $fillable = [
        'driver_id',
        'shipment_id',
        'shipment_tracking_no',
        'pre_id',
        'state_id',
        'driver_runsheet_id',
        'bonus_amount',
        'bonus_rate',
        'reference',
        'description',
        'active',
        'isPaid',
        'action',
        'created_by',
        'status',
    ];

    protected $casts = [
        'bonus_amount' => 'decimal:2',
        'bonus_rate' => 'decimal:2',
        'active' => 'boolean',
        'isPaid' => 'boolean',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function driverRunsheet()
    {
        return $this->belongsTo(DriverRunsheet::class, 'driver_runsheet_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get total active bonuses for a driver
     *
     * @param int $driverId
     * @return float
     */
    public static function driverBonuses(int $driverId): float
    {
        return (float) static::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->sum('bonus_amount');
    }
}
