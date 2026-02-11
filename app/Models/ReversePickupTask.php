<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReversePickupTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'manifest_id',
        'ref',
        'merchant_id',
        'driver_id',
        'no_of_shipments',
        'picked_shipments_no',
        'note',
        'status',
        'reverse_pickup_request_id',
        'owner_id',
        'owner_type',
        'scheduled_at',
        'completed_at',
        'assigned_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'registered_shipments_no',
        'total_shipments_no',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            if (!$task->manifest_id) {
                $date = now()->format('dmy');
                $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $task->manifest_id = 'REV-' . $date . $random;
            }
            if (!$task->ref) {
                $task->ref = static::generateRef();
            }
        });
    }

    /**
     * Generate unique reference number
     */
    public static function generateRef(): string
    {
        do {
            $ref = 'REV-TASK-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('ref', $ref)->exists());

        return $ref;
    }

    /**
     * Accessors
     */
    public function getRegisteredShipmentsNoAttribute(): int
    {
        return $this->shipments()->count();
    }

    public function getTotalShipmentsNoAttribute(): int
    {
        return (int) $this->no_of_shipments;
    }

    /**
     * Relationships
     */
    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function reversePickupRequest()
    {
        return $this->belongsTo(ReversePickupRequest::class, 'reverse_pickup_request_id');
    }

    public function shipments()
    {
        return $this->hasMany(ReversePickupShipment::class, 'reverse_pickup_task_id');
    }

    public function pickedShipments()
    {
        return $this->hasMany(ReversePickupShipment::class, 'reverse_pickup_task_id')->where('status', 'picked');
    }

    public function toPickupShipments()
    {
        return $this->hasMany(ReversePickupShipment::class, 'reverse_pickup_task_id')->where('status', 'to_pickup');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function owner()
    {
        return $this->morphTo();
    }
}
