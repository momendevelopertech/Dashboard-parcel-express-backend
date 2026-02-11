<?php

namespace App\Models;

use App\Observers\MerchantPickupTaskObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


#[ObservedBy([MerchantPickupTaskObserver::class])]
class MerchantPickupTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'driver_id',
        'timezone',
        'scheduled_timezone',
        'no_of_shipments',
        'picked_shipments_no',
        'extra_shipments_no',
        'note',
        'status',
        'pickup_request_id',
        'owner_id',
        'owner_type',
        'cached_shipment_step',
        'ref',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            if (!$task->manifest_id) {
                $date = now()->format('dmy');
                $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $task->manifest_id = $date . $random;
            }
            if (!$task->ref) {
                $task->ref = static::generateRef();
            }
        });
    }

    public static function generateRef(): string
    {
        do {
            $ref = 'REF-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('ref', $ref)->exists());

        return $ref;
    }

    // protected $casts = [
    //     'status' => 'string',
    // ];

    public function shipments()
    {
        return $this->hasMany(MerchantPickupShipment::class, 'pickup_task_id');
    }
    protected $appends = [
        'registered_shipments_no',
        'total_shipments_no',
        'picked_shipments_no',
    ];
    public function getRegisteredShipmentsNoAttribute(): int
    {
        // عدد الشحنات اللي دخلت جوه الـ task
        return $this->shipments()->count();
    }

    public function getTotalShipmentsNoAttribute(): int
    {
        return (int) $this->no_of_shipments;
    }

    public function getPickedShipmentsNoAttribute(): int
    {
        // Everything is now in merchant_pickup_shipments with status 'picked'
        return $this->picked_shipments()->count();
    }

    public function to_pickup_shipments()
    {
        return $this->hasMany(MerchantPickupShipment::class, 'pickup_task_id')->where('status', 'to_pickup');
    }

    public function picked_shipments()
    {
        return $this->hasMany(MerchantPickupShipment::class, 'pickup_task_id')->where('status', 'picked');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class);
    }

    public function merchantProfile()
    {
        return $this->hasOneThrough(
            Merchant::class,
            User::class,
            'id',           // Foreign key on users table
            'user_id',      // Foreign key on merchants table
            'merchant_id',  // Local key on merchant_pickup_tasks table
            'id'            // Local key on users table
        );
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }


    public function pickupMissedShipmentsTransactions()
    {
        return $this->hasMany(PickupMissedShipmentsTransaction::class, 'pickup_task_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }
    public function pickupRequest()
    {
        return $this->belongsTo(PickupRequest::class, 'pickup_request_id');
    }
    /**
     * Scope query to current workspace
     * Filters pickup tasks by merchant's workspace (through Merchant model)
     */
    public function scopeByOwner($query)
    {
        $user = Auth::user();
        $workspaceId = request('selected_workspace');
        $workspaceType = request('selected_workspace_type');

        // If no workspace selected, return unfiltered
        if (!$workspaceId || !$workspaceType) {
            return $query;
        }

        // Filter by checking the Merchant model's workspace through User
        return $query->whereHas('merchant.merchant', function ($q) use ($workspaceId, $workspaceType) {
            $q->where('owner_id', $workspaceId)
                ->where('owner_type', $workspaceType);
        });
    }
}
