<?php

namespace App\Models;


use Illuminate\Support\Facades\DB as DB;
use App\Observers\ConsigneeObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Notifications\Notifiable;

#[ObservedBy([ConsigneeObserver::class])]
class Consignee extends Model
{
    use HasFactory, Notifiable;

    public function routeNotificationForWhatsapp()
    {
        return $this->cellphone;
    }

    public function routeNotificationForMail()
    {
        return $this->email ?? "mailtests@parcelexpress.om";
    }

    protected $fillable = array(
        'name',
        'email',
        'country_key_cellphone',
        'cellphone',
        'country_key_alternatePhone',
        'alternatePhone',
        'district',
        'identify',
        'taxNumber',
        'location_url',
        'location',
        'address_update_url',
        'update_token',
        'token_expires_at',
        'address_confirmed',
        'is_guest',
        'owner_type',
        'owner_id',
        'address_update_otp',
        'address_update_otp_expires_at',
        'address_update_verified_at',
        'current_address_id',
        'country_id',
        'governorate_id',
        'state_id',
        'place_id',
        'city_id',
        'zipcode',
        'streetAddress',
        'longitude',
        'latitude',
    );

    protected $hidden = ["token_expires_at"];
    protected $casts = ["token_expires_at" => "datetime"];
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function currentAddress()
    {
        return $this->belongsTo(Address::class, 'current_address_id');
    }
    public function approvedAddresses()
    {
        return $this->addresses()
            ->where('approved', true)
            ->where('is_active', true)
            ->orderByDesc('last_used_at');
    }
    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'consignee_id');
    }
    public function notifications()
    {
        return $this->morphMany(\App\Models\Notification::class, 'notifiable')->latest('created_at');
    }
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function place()
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function zone()
    {
        if ($this->state_id) {
            $mapped = DB::table('state_zones as sz')
                ->join('zones as z', 'z.id', '=', 'sz.zone_id')
                ->where('sz.state_id', $this->state_id)
                ->select('z.id', 'z.name', 'z.owner_id', 'z.owner_type')
                ->first();

            if ($mapped) {
                return $mapped;
            }
        }

        if ($this->latitude && $this->longitude) {
            if ($z = $this->matchPointToZone((float) $this->longitude, (float) $this->latitude)) {
                return $z;
            }
        }

        if ($this->place && $this->place->lng && $this->place->lat) {
            if ($z = $this->matchPointToZone((float) $this->place->lng, (float) $this->place->lat)) {
                return $z;
            }
        }

        if ($this->state) {
            if ($z = $this->matchPolygonToZone('states', $this->state->id, 'polygon'))
                return $z;
            if ($this->state->lng && $this->state->lat) {
                if ($z = $this->matchPointToZone((float) $this->state->lng, (float) $this->state->lat))
                    return $z;
            }
        }

        if ($this->governorate) {
            if ($z = $this->matchPolygonToZone('governorates', $this->governorate->id, 'polygon'))
                return $z;
            if ($this->governorate->lng && $this->governorate->lat) {
                if ($z = $this->matchPointToZone((float) $this->governorate->lng, (float) $this->governorate->lat))
                    return $z;
            }
        }

        return null;
    }

    // ==============================
    // Zone match helpers
    // ==============================
    protected function matchPointToZone(float $lng, float $lat)
    {
        try {
            $pointJson = json_encode([
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ]);

            return DB::table('zones AS z')
                ->whereRaw(
                    "ST_Contains(z.coordinates, ST_GeomFromGeoJSON(?))",
                    [$pointJson]
                )
                ->select('z.id', 'z.name', 'z.owner_id', 'z.owner_type')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function matchPolygonToZone(string $table, int $id, string $column = 'polygon')
    {
        try {
            $poly = DB::table($table)->where('id', $id)->value($column);
            if (!$poly)
                return null;

            $wkbRow = DB::selectOne("SELECT ST_AsWKB(CASE WHEN ST_SRID(?)=0 THEN ST_SRID(?,4326) ELSE ? END) AS wkb", [$poly, $poly, $poly]);
            if (!$wkbRow || empty($wkbRow->wkb))
                return null;

            return DB::table('zones AS z')
                ->whereRaw(
                    "ST_Intersects(z.coordinates, ST_GeomFromWKB(?, 4326))",
                    [$wkbRow->wkb]
                )
                ->orderByRaw(
                    "ST_Area(ST_Intersection(z.coordinates, ST_GeomFromWKB(?, 4326))) DESC",
                    [$wkbRow->wkb]
                )
                ->select('z.id', 'z.name', 'z.owner_id', 'z.owner_type')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function zoneByGovernorate()
    {
        if (!$this->governorate) {
            return null;
        }

        $pointJson = json_encode([
            'type' => 'Point',
            'coordinates' => [(float) $this->governorate->lng, (float) $this->governorate->lat],
        ]);

        return DB::table('zones AS z')
            ->whereRaw(
                "ST_Contains(z.coordinates, ST_GeomFromGeoJSON(?))",
                [$pointJson]
            )
            ->first();
    }

    public function zoneByState()
    {
        if (!$this->state) {
            return null;
        }

        $pointJson = json_encode([
            'type' => 'Point',
            'coordinates' => [(float) $this->state->lng, (float) $this->state->lat],
        ]);

        return DB::table('zones AS z')
            ->whereRaw(
                "ST_Contains(z.coordinates, ST_GeomFromGeoJSON(?))",
                [$pointJson]
            )
            ->first();
    }

    public function zoneByPlace()
    {
        if (!$this->place) {
            return null;
        }

        $pointJson = json_encode([
            'type' => 'Point',
            'coordinates' => [(float) $this->place->lng, (float) $this->place->lat],
        ]);

        return DB::table('zones AS z')
            ->whereRaw(
                "ST_Contains(z.coordinates, ST_GeomFromGeoJSON(?))",
                [$pointJson]
            )
            ->first();
    }

    public function scopeByOwner($query)
    {
        $user = Auth::user();
        $selectedWorkspaceId = request('selected_workspace');

        // Bypass if user appears to be super admin
        try {
            if ($user && isset($user->roles) && $user->roles->contains('name', 'Super Admin')) {
                return $query;
            }
        } catch (\Throwable $ignored) {
        }

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                $query->where('owner_type', Branch::class)
                    ->where('owner_id', $selectedWorkspaceId);
            }

            if ($user->station_user) {
                $query->orWhere(function ($query) use ($selectedWorkspaceId) {
                    $query->where('owner_type', Station::class)
                        ->where('owner_id', $selectedWorkspaceId);
                });
            }

            if ($user->hub_user) {
                $query->orWhere(function ($query) use ($selectedWorkspaceId) {
                    $query->where('owner_type', Hub::class)
                        ->where('owner_id', $selectedWorkspaceId);
                });
            }
        }

        return $query;
    }

    public function old_address()
    {
        return $this->hasOne(OldAddress::class, 'consignee_id')->oldest();
    }

    public function old_addresses()
    {
        return $this->hasMany(OldAddress::class, 'consignee_id');
    }
}
