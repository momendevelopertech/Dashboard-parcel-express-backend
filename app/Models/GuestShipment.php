<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Observers\GuestShipmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([GuestShipmentObserver::class])]
class GuestShipment extends Model
{
    protected $fillable = [
        "customer_name",
        "customer_phone",
        "driver_id",
        "tracking_no",
        "governorate_id",
        "state_id",
        "place_id",
        "city_id",
        "zipcode",
        "streetAddress",
        "notes",
        "latitude",
        "longitude",
        "location_url",
        "payment_type"
    ];

    public function driver_shipment_assignment()
    {
        return $this->hasOne(DriverShipmentAssignment::class, 'shipment_tracking_no', 'tracking_no');
    }

    public function driver()
    {
        return $this->belongsTo(User::class);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
