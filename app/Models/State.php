<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class State extends Model
{
    use HasFactory;

    protected $fillable = [
        "station_id",
        "country_id",
        "governorate_id",
        "en_name",
        "ar_name",
        "isActive",
        "polygon",
        "lat",
        "lng",
    ];

    // protected $hidden = [
    //     'polygon', // Hide the raw geometry column
    // ];

    protected $casts = [
        'isActive' => 'boolean',
    ];

    // protected $appends = ['polygon_geojson'];

    public function getPolygonGeojsonAttribute()
    {
        return $this->polygon_geojson();    
    }

    public function polygon_geojson()
    {
        $geojson = DB::selectOne('SELECT ST_AsGeoJSON(polygon) AS geojson FROM states WHERE id = ?', [$this->id]);
        return $geojson && $geojson->geojson ? json_decode($geojson->geojson, true) : null;
    }

    public function zones()
    {
        $zones = Zone::select('id', 'name')
            ->whereRaw(
                'ST_Intersects(zones.coordinates, (SELECT polygon FROM states WHERE id = ?))',
                [$this->id]
            )
            ->get();

        // Attach GeoJSON for each zone
        foreach ($zones as $zone) {
            $zone->coordinates_geojson = $zone->coordinates_geojson;
        }

        return $zones;
    }

    // Deprecated: always use polygon_geojson instead
    public function getPolygonAttribute($value)
    {
        return null;
    }

    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function cities()
    {
        return $this->hasMany(City::class, 'state_id');
    }

    public function shippers()
    {
        return $this->hasMany(Shipper::class, 'state_id');
    }

    public function consignees()
    {
        return $this->hasMany(Consignee::class, 'state_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    public function merchants()
    {
        return $this->hasMany(Merchant::class, 'state_id');
    }

    public function merchant_commissions()
    {
        return $this->hasMany(MerchantCommission::class, 'state_id');
    }

    public function shipper_commission()
    {
        return $this->hasOne(ShipperCommission::class, 'state_id');
    }

    public function company_commissions()
    {
        return $this->hasMany(CompanyCommission::class, 'state_id');
    }

    public function channels()
    {
        return $this->hasMany(StateChannel::class, 'internal_state_id');
    }
}
