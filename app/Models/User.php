<?php

namespace App\Models;

use ALajusticia\Logins\Traits\HasLogins;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;


#[ObservedBy(UserObserver::class)]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasLogins, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'owner_type',
        'name',
        'username',
        'email',
        'password',
        'country_code',
        'phone',
        'phone_verified_at',
        'verification_code',
        'verification_code_expires_at',
        'status',
        'unassigned_last_seen_at',
        'unregistered_last_seen_at',
        'address_revision_last_seen_at',
        'firebase_uid',
        'deleted_at',
        'deleted_by'
    ];

    public function isSuperAdmin()
    {
        return $this->hasRole('Super Admin');
    }
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // 'owner_id',
        // 'owner_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'unassigned_last_seen_at' => 'datetime',
            'unregistered_last_seen_at' => 'datetime',
            'address_revision_last_seen_at' => 'datetime',
        ];
    }
    // ... Existing traits and properties ...

    public function newFromBuilder($attributes = [], $connection = null)
    {
        $model = parent::newFromBuilder($attributes, $connection);

        if (empty($model->owner_id) && request()->hasHeader('X-Workspace-Key')) {
            $model->owner_id = Crypt::decryptString(request()->header('X-Workspace-Key'));
        }
        if (empty($model->owner_type) && request()->hasHeader('X-Workspace-Type')) {
            $model->owner_type = request()->header('X-Workspace-Type');
        }
        return $model;
    }

    // protected static function booted()
    // {
    //     // When an instance is retrieved from the database,
    //     // immediately set owner fields from headers if they are empty.
    //     static::retrieved(function ($user) {
    //     });
    // }

    // public function getOwnerIdAttribute($value)
    // {
    //     // Fallback to headers if value is still empty (e.g., not yet retrieved)
    //     return $value ?? (
    //         request()->hasHeader('X-Workspace-Key')
    //         ? Crypt::decryptString(request()->header('X-Workspace-Key'))
    //         : null
    //     );
    // }

    // public function getOwnerTypeAttribute($value)
    // {
    //     return $value ?? request()->header('X-Workspace-Type');
    // }

    public function   merchant_images()
    {
        return $this->hasMany(MerchantImage::class, 'merchant_id');
    }
    public function owner()
    {
        return $this->morphTo();
    }


    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'merchant_id');
    }

    public function createdShipments()
    {
        return $this->hasMany(Shipment::class, 'created_by');
    }

    public function driver()
    {
        return $this->hasOne(Driver::class)
            ->withoutGlobalScope(\App\Models\Scopes\ExcludeGuestDriversScope::class);
    }

    public function account()
    {
        return $this->morphOne(Account::class, 'accountable');
    }

    public function hub_user()
    {
        return $this->hasOne(HubUser::class, 'user_id');
    }

    public function hub_users()
    {
        return $this->hasMany(HubUser::class, 'user_id');
    }

    public function hubs()
    {
        return $this->belongsToMany(Hub::class, 'hub_users', 'user_id', 'hub_id');
    }


    public function station_user()
    {
        return $this->hasOne(StationUser::class, 'user_id');
    }

    public function station_users()
    {
        return $this->hasMany(StationUser::class, 'user_id');
    }

    public function stations()
    {
        return $this->belongsToMany(Station::class, 'station_users', 'user_id', 'station_id');
    }

    public function branch_user()
    {
        return $this->hasOne(BranchUser::class, 'user_id');
    }

    public function branch_users()
    {
        return $this->hasMany(BranchUser::class, 'user_id');
    }
    /**
     * Get the hub users for the user.
     */
    public function hubUsers(): HasMany
    {
        return $this->hasMany(HubUser::class);
    }

    /**
     * Get the station users for the user.
     */
    public function stationUsers(): HasMany
    {
        return $this->hasMany(StationUser::class);
    }

    /**
     * Get the branch users for the user.
     */
    public function branchUsers(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_users', 'user_id', 'branch_id');
    }


    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'user_id');
    }

    public function merchant_commissions()
    {
        return $this->hasMany(MerchantCommission::class, 'merchant_id');
    }

    public function scopeMerchants($query)
    {
        return $query->whereHas('merchant');
    }

    public function delivery_commissions()
    {
        return $this->hasMany(DeliveryCommission::class, 'driver_id');
    }

    public function assignations()
    {
        return $this->hasMany(DriverShipmentAssignment::class, 'assigned_by');
    }

    public function shelf_assignations()
    {
        return $this->hasMany(AssignShipmentToShelf::class, 'assigned_by');
    }

    public function waybills()
    {
        return $this->hasMany(MerchantWaybill::class, 'merchant_id');
    }
    public function driverWaybillBatches()
    {
        return $this->hasMany(\App\Models\DriverWaybillBatch::class, 'driver_id');
    }
    public function driverWaybills()
    {
        return $this->hasMany(\App\Models\DriverWaybill::class, 'driver_id');
    }

    public function runsheets()
    {
        return $this->hasMany(DriverRunsheet::class, 'driver_id');
    }

    public function runsheet_shipments()
    {
        return $this->hasMany(DriverRunsheetShipment::class, 'driver_id');
    }

    public function driver_notifications()
    {
        return $this->hasMany(DriverNotification::class, 'driver_id');
    }
    public function notifications()
    {
        return $this->morphMany(\App\Models\Notification::class, 'notifiable')->latest('created_at');
    }

    public function fines()
    {
        return $this->hasMany(ShipmentFine::class, 'driver_id');
    }

    public function driverShipmentAssignments()
    {
        return $this->hasMany(DriverShipmentAssignment::class, 'driver_id');
    }

    public function employee()
    {
        return $this->morphOne(Employee::class, 'employable');
    }

    public function scopeMerchantOwner($query)
    {
        $facility = facility();

        if ($facility) {
            $query->where('owner_type', $facility->type)
                ->where('owner_id', $facility->id);
        }

        return $query;
    }


    public function scopeByOwner($query)
    {
        // if(user()->hasRole('Super Admin')){
        //     return $query;
        // }
        $query->where('owner_id', facility('id'))
            ->where('owner_type', facility('type'));
        return $query;
    }

    public function invoiceable()
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }

    public function accountable()
    {
        return $this->morphOne(Account::class, 'accountable');
    }

    public function sent_transactions()
    {
        return $this->morphMany(Transaction::class, 'from');
    }

    public function received_transactions()
    {
        return $this->morphMany(Transaction::class, 'to');
    }

    public function transactionsFrom($from = null, $to = null)
    {
        return $this->morphMany(Transaction::class, 'from')
            ->when($from, fn($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('created_at', '<=', $to));
    }

    public function transactionsTo($from = null, $to = null)
    {
        return $this->morphMany(Transaction::class, 'to')
            ->when($from, fn($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('created_at', '<=', $to));
    }

    public function fines_created()
    {
        return $this->hasMany(ShipmentFine::class, 'created_by');
    }

    public function delivery_shipments()
    {
        return $this->hasMany(Shipment::class, 'driver_id');
    }

    public function driver_runsheet_submission_receivals()
    {
        return $this->hasMany(DriverRunsheetSubmission::class, 'received_by');
    }

    public function login_histories()
    {
        return $this->hasMany(LoginHistory::class, 'user_id');
    }

    public function driver_relatives()
    {
        return $this->hasMany(DriverRelative::class, 'driver_id');
    }

    public function driver_files()
    {
        return $this->hasMany(DriverFile::class, 'driver_id');
    }

    public function activity_logs()
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    public function trucks()
    {
        return $this->hasMany(Truck::class, 'truck_driver_id');
    }

    public function truck_driver()
    {
        return $this->hasOne(TruckDriver::class, 'user_id');
    }

    public function driver_statuses()
    {
        return $this->hasMany(DriverStatus::class, 'driver_id');
    }

    public function current_status()
    {
        return $this->hasOne(DriverStatus::class, 'driver_id')->latest('last_updated');
    }

    public function merchant_tickets()
    {
        return $this->hasMany(MerchantTicket::class, 'merchant_id');
    }

    public function merchant_ticket_messages()
    {
        return $this->hasMany(MerchantTicketMessage::class, 'sender_id')->orderBy('created_at', 'asc');
    }

    public function driver_status()
    {
        return $this->hasOne(DriverStatus::class, 'driver_id');
    }
    public function timezone(): string
    {
        $station = $this->stations()
            ->with('hub')
            ->first();
        if ($station?->hub?->timezone) {
            return $station->hub->timezone;
        }
        $hub = $this->hubs()->first();
        if ($hub?->timezone) {
            return $hub->timezone;
        }

        return config('app.timezone');
    }

    public function reverseMerchantAccount()
    {
        return $this->hasOne(Merchant::class, 'user_id');
    }
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function scopeByOwnerOrFacilityAccess($query)
    {
        $facilityId = facility('id');
        $facilityType = facility('type');

        return $query->where(function ($q) use ($facilityId, $facilityType) {

            $q->where('owner_id', $facilityId)
            ->where('owner_type', $facilityType);

            if ($facilityType === \App\Models\Hub::class) {
                $q->orWhereHas('hubs', fn ($h) => $h->where('hubs.id', $facilityId));
            }

            if ($facilityType === \App\Models\Station::class) {
                $q->orWhereHas('stations', fn ($s) => $s->where('stations.id', $facilityId));
            }
        });
    }


    public function personalAccessTokens()
    {
        return $this->hasMany(PersonalAccessToken::class, 'tokenable_id')
            ->where('tokenable_type', self::class);
    }

    public function lastValidToken()
    {
        return $this->tokens()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->latest('created_at')
            ->first();
    }


    public function getTimezoneAttribute()
    {
        $token = $this->tokens()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->latest('created_at')
            ->first();

        return $token?->timezone;
    }

}
