<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Governorate extends Model
{
    use HasFactory;

    protected $fillable = [
        'en_name',
        'ar_name',
        'polygon',
        'isActive',
        'country_id'
    ];

    protected $hidden = [
        'polygon',
    ];

    protected $casts = [
        'isActive' => 'boolean',
    ];

    // protected $appends = ['polygon_geojson'];

    public function zones()
    {
        return Zone::whereRaw(
            'ST_Intersects(zones.coordinates, (SELECT polygon FROM governorates WHERE id = ?))',
            [$this->id]
        )->get();
    }

    public function polygon_geojson()
    {
        $geojson = DB::selectOne('SELECT ST_AsGeoJSON(polygon) AS geojson FROM governorates WHERE id = ?', [$this->id]);
        return $geojson && $geojson->geojson ? json_decode($geojson->geojson, true) : null;
    }

    public function getPolygonAttribute($value)
    {
        return null;
        // if (!$value) return null;

        // $raw = DB::selectOne(
        //     'SELECT ST_AsGeoJSON(polygon) AS geojson 
        //      FROM governorates WHERE id = ?',
        //     [$this->id]
        // );

        // return $raw && $raw->geojson
        //     ? json_decode($raw->geojson, true)
        //     : null;
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function states()
    {
        return $this->hasMany(State::class, 'governorate_id');
    }

    public function merchants()
    {
        return $this->hasMany(Merchant::class, 'governorate_id');
    }

    public function consignees()
    {
        return $this->hasMany(Consignee::class, 'governorate_id');
    }

    public function shippers()
    {
        return $this->hasMany(Shipper::class, 'governorate_id');
    }

    public function channels()
    {
        return $this->hasMany(GovernorateChannel::class, 'internal_governorate_id');
    }
}
