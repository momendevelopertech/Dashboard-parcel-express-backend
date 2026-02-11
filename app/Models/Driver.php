<?php

namespace App\Models;

use App\Observers\DriverObserver;
use App\Traits\LogsUserActions;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use App\Models\Scopes\ExcludeGuestDriversScope;
use Illuminate\Database\Eloquent\SoftDeletes;


#[ObservedBy(DriverObserver::class)]
class Driver extends Model
{
    use LogsUserActions,SoftDeletes;
    protected $fillable = [
        "owner_id",
        "owner_type",
        "user_id",
        "company_id",
        "phone",
        "status",
        "id_card",
        "license",
        "car_ownership_id",
        "is_guest",
        "profile_image",
        "company_name",
        'is_on_hold',
        'hold_reason',
        'hold_by',
        'hold_at',
    ];
    protected $casts = [
        'is_on_hold' => 'bool',
        'hold_at' => 'datetime',
    ];

    // سكوب سريع نستخدمه في أي استعلامات
    public function scopeNotOnHold($q)
    {
        return $q->where('is_on_hold', false);
    }
    public function employee()
    {
        return $this->morphOne(Employee::class, 'employable');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function settings()
    {
        return $this->hasOne(DriverSetting::class, 'driver_id');
    }

    public function shipments()
    {
        return $this->hasManyThrough(Shipment::class, DriverShipmentAssignment::class, 'driver_id', 'id', 'id', 'shipment_id');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function feedbacks()
    {
        return $this->hasManyThrough(
            CustomerFeedback::class,
            Delivery::class,
            'driver_id',       // FK in deliveries
            'delivery_id',     // FK in customer_feedback
            'id',              // PK in drivers
            'id'               // PK in deliveries
        );
    }

    public function merchant_pickup_shipments()
    {
        return $this->hasMany(MerchantPickupShipment::class, 'driver_id');
    }

    public function runsheet_shipments()
    {
        return $this->hasMany(DriverRunsheetShipment::class, 'driver_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function notes()
    {
        return $this->hasMany(QuickNote::class, 'driver_id');
    }

    public function shipment_deliveries()
    {
        return $this->hasMany(ShipmentDelivery::class, 'driver_id');
    }

        protected static function booted()
    {
        static::addGlobalScope(new ExcludeGuestDriversScope);
    }

}
