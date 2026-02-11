<?php

namespace App\Models;

use App\Observers\ZoneObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\State;
use PDO;

#[ObservedBy(ZoneObserver::class)]
class Zone extends Model
{
  use HasFactory;

  protected $fillable = [
    "name",
    "coordinates",
    'owner_type',
    'owner_id',
  ];

  protected $hidden = [
    'coordinates', // Hide the raw geometry column
  ];

  protected $appends = [
    'coordinates_geojson',
  ];

  public function getCoordinatesAsGeoJSON()
  {
    return DB::raw('ST_AsGeoJSON(coordinates)');
  }


  public function getCoordinatesGeojsonAttribute()
  {
    // Always return the geometry as GeoJSON, decoded
    $geojson = DB::selectOne('SELECT ST_AsGeoJSON(coordinates) AS geojson FROM zones WHERE id = ?', [$this->id]);
    return $geojson && $geojson->geojson ? json_decode($geojson->geojson, true) : null;
  }
  public function governorates()
  {
    // 1) Fast & reliable: derive from selected states (pivot: state_zones)
    $byStates = DB::table('governorates as g')
      ->join('states as s', 's.governorate_id', '=', 'g.id')
      ->join('state_zones as sz', 'sz.state_id', '=', 's.id')
      ->where('sz.zone_id', $this->id)
      ->distinct()
      ->orderBy('g.en_name')
      ->get(['g.id', 'g.en_name', 'g.ar_name']);

    if ($byStates->isNotEmpty()) {
      return $byStates;  // ✅ works even if you don’t have governorate polygons
    }

    // 2) Fallback to your spatial approach (kept, but simplified & safer)
    try {
      // if zone has no geometry -> nothing to do
      $meta = DB::selectOne("
            SELECT ST_AsBinary(coordinates) wkb, COALESCE(NULLIF(ST_SRID(coordinates),0), 4326) srid,
                   ST_GeometryType(coordinates) t
            FROM zones WHERE id = ?
        ", [$this->id]);

      if (!$meta || !$meta->wkb) {
        return collect();
      }

      // normalize zone geometry to 4326 (if you store everything in 4326 this avoids mismatches)
      $zoneWkb = $meta->wkb;
      $zone = DB::selectOne("
            SELECT ST_AsBinary(
                     CASE
                       WHEN ST_SRID(z.coordinates)=0 THEN ST_SRID(z.coordinates,4326)
                       WHEN ST_SRID(z.coordinates)<>4326 THEN ST_SRID(z.coordinates,4326)
                       ELSE z.coordinates
                     END
                   ) wkb
            FROM zones z WHERE z.id=?
        ", [$this->id]);

      if ($zone && $zone->wkb) {
        $zoneWkb = $zone->wkb;
      }

      // intersect/shipment by area in 4326 (safe if your gov polygons are also in 4326)
      return Governorate::query()
        ->whereNotNull('polygon')
        ->whereRaw("
                MBRIntersects(
                  ST_Buffer(
                    CASE
                      WHEN ST_SRID(polygon)=0 THEN ST_SRID(polygon,4326)
                      WHEN ST_SRID(polygon)<>4326 THEN ST_SRID(polygon,4326)
                      ELSE polygon
                    END, 0
                  ),
                  ST_GeomFromWKB(?, 4326)
                )
            ", [$zoneWkb])
        ->whereRaw("
                ST_Intersects(
                  ST_Buffer(
                    CASE
                      WHEN ST_SRID(polygon)=0 THEN ST_SRID(polygon,4326)
                      WHEN ST_SRID(polygon)<>4326 THEN ST_SRID(polygon,4326)
                      ELSE polygon
                    END, 0
                  ),
                  ST_GeomFromWKB(?, 4326)
                )
            ", [$zoneWkb])
        ->orderByRaw("
                ST_Area(
                  ST_Intersection(
                    ST_Buffer(
                      CASE
                        WHEN ST_SRID(polygon)=0 THEN ST_SRID(polygon,4326)
                        WHEN ST_SRID(polygon)<>4326 THEN ST_SRID(polygon,4326)
                        ELSE polygon
                      END, 0
                    ),
                    ST_GeomFromWKB(?, 4326)
                  )
                ) DESC
            ", [$zoneWkb])
        ->limit(1)
        ->get(['id', 'en_name', 'ar_name']);
    } catch (\Throwable $e) {
      \Log::info('governorates() spatial fallback failed', ['zone_id' => $this->id, 'err' => $e->getMessage()]);
      return collect();
    }
  }
  public function selectedStates()
  {
    return $this->belongsToMany(
      State::class,
      'state_zones',
      'zone_id',
      'state_id'
    )->withPivot(['zone_id', 'state_id'])->withTimestamps();
  }

  public function statesMulti(float $minOverlapPct = 0.03)
  {
    $sql = "
        SELECT s.*
        FROM states s
        CROSS JOIN (
            SELECT CASE
                     WHEN ST_SRID(coordinates)=0 THEN ST_SRID(coordinates,4326)
                     ELSE coordinates
                   END AS g
            FROM zones WHERE id = ? LIMIT 1
        ) z
        WHERE
          (
            s.polygon IS NOT NULL
            AND ST_Intersects(
                CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END,
                z.g
            )
            AND (
                -- مساحة التقاطع (0 لو مش بوليجون/مولتي)
                CASE
                  WHEN ST_IsEmpty(
                         ST_Intersection(
                           CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END, z.g
                         )
                       )
                  THEN 0
                  WHEN ST_GeometryType(
                         ST_Intersection(
                           CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END, z.g
                         )
                       ) IN ('POLYGON','MULTIPOLYGON')
                  THEN ST_Area(
                         ST_Intersection(
                           CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END, z.g
                         )
                       )
                  ELSE 0
                END
                /
                NULLIF(
                  ST_Area(
                    CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END
                  ),
                  0
                ) >= ?
            )
          )
          OR (
            s.polygon IS NULL AND s.lng IS NOT NULL AND s.lat IS NOT NULL
            AND ST_Contains(z.g, ST_SRID(Point(s.lng, s.lat), 4326))
          )
        ORDER BY s.id
    ";

    $rows = DB::select($sql, [$this->id, $minOverlapPct]);
    return State::hydrate($rows);
  }




  public function states(int $tolerance_m = 150, bool $limitOne = false)
  {
    try {
      // هنشتغل في 3857 (متر) ثم نستخدم النتائج مباشرة بدون رجوع لِـ 4326
      $sql = "
            SELECT
                s.*,

                -- أولوية الإرجاع
                CASE
                  WHEN s.polygon IS NOT NULL AND ST_Within(
                        ST_Transform(CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END, 3857),
                        z.g3857
                  ) THEN 1
                  WHEN s.polygon IS NOT NULL AND ST_Intersects(
                        ST_Transform(CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END, 3857),
                        z.g3857
                  ) THEN 2
                  WHEN s.polygon IS NULL AND s.lng IS NOT NULL AND s.lat IS NOT NULL AND (
                        ST_Contains(z.g3857, ST_Transform(ST_SRID(Point(s.lng, s.lat),4326), 3857))
                        OR ST_Distance(ST_Transform(ST_SRID(Point(s.lng, s.lat),4326), 3857), z.g3857) <= ?
                  ) THEN 3
                  ELSE 9
                END AS prio,

                -- مساحة التقاطع (للفرز – بوحدات 3857 تقريبًا m²)
                CASE
                  WHEN s.polygon IS NOT NULL THEN ST_Area(
                    ST_Intersection(
                      ST_Transform(CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END, 3857),
                      z.g3857
                    )
                  )
                  ELSE 0
                END AS inter_area,

                -- مسافة لفضّ التعادل (متر): 0 لو متقاطع، وإلا أقرب مسافة
                CASE
                  WHEN s.polygon IS NOT NULL THEN
                    ST_Distance(
                      ST_Transform(CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END, 3857),
                      z.g3857
                    )
                  ELSE
                    ST_Distance(
                      ST_Transform(ST_SRID(Point(s.lng, s.lat),4326), 3857),
                      z.g3857
                    )
                END AS d

            FROM states s
            CROSS JOIN (
                SELECT
                  ST_Transform(
                    CASE WHEN ST_SRID(coordinates)=0 THEN ST_SRID(coordinates,4326) ELSE coordinates END,
                    3857
                  ) AS g3857
                FROM zones
                WHERE id = ? LIMIT 1
            ) z

            WHERE
              (
                (s.polygon IS NOT NULL AND ST_Intersects(
                   ST_Transform(CASE WHEN ST_SRID(s.polygon)=0 THEN ST_SRID(s.polygon,4326) ELSE s.polygon END, 3857),
                   z.g3857
                 ))
                OR
                (s.polygon IS NULL AND s.lng IS NOT NULL AND s.lat IS NOT NULL AND
                   (
                     ST_Contains(z.g3857, ST_Transform(ST_SRID(Point(s.lng, s.lat),4326), 3857))
                     OR ST_Distance(ST_Transform(ST_SRID(Point(s.lng, s.lat),4326), 3857), z.g3857) <= ?
                   )
                )
              )

            ORDER BY prio ASC, inter_area DESC, d ASC
        ";

      $params = [$tolerance_m, $this->id, $tolerance_m];

      if ($limitOne) {
        $rows = DB::select($sql . " LIMIT 1", $params);
        return empty($rows) ? collect() : State::hydrate($rows);
      }

      $rows = DB::select($sql, $params);
      return State::hydrate($rows);

    } catch (\Throwable $th) {
      Log::error('Zone states error', ['error' => $th->getMessage(), 'zone_id' => $this->id]);
      return collect();
    }
  }



  public function places()
  {
    try {
      $lngCol = 'places.lng';
      $latCol = 'places.lat';

      $pointWKT = "CONCAT('POINT(', $lngCol, ' ', $latCol, ')')";
      $pointExpr = "ST_GeomFromText($pointWKT, 4326)";
      $zoneGeom = "ST_Buffer((SELECT coordinates FROM zones WHERE id = ? AND coordinates IS NOT NULL), 0)";

      return Place::whereRaw(
        "ST_Contains($zoneGeom, $pointExpr)",
        [$this->id]
      )->get();
    } catch (\Throwable $th) {
      info('places_error', ['err' => $th->getMessage()]);
      return collect();
    }
  }



  // public function places()
  // {
  //     try {
  //         $engine = DB::selectOne("
  //                 SELECT CASE
  //                         WHEN @@version_comment LIKE '%MariaDB%'  THEN 'mariadb'
  //                         WHEN @@version_comment LIKE '%Percona%'  THEN 'percona'
  //                         ELSE 'mysql'
  //                     END AS engine_name
  //             ")->engine_name;
  //         info("engine", ["engine" => $engine]);
  //         if ($engine === 'mariadb') {
  //             $lat = 'places.lng';
  //             $lng = 'places.lat';
  //         } else {
  //             $lat = 'places.lat';
  //             $lng = 'places.lng';
  //         }
  //         return Place::whereRaw(
  //             'ST_Contains((SELECT coordinates FROM zones WHERE id = ?), ST_GeomFromText(CONCAT("POINT(", ' . $lat . ', " ", ' . $lng . ', ")"), 4326))',
  //             [$this->id]
  //         )->get();
  //     } catch (\Throwable $th) {
  //         info("error", ["error" => $th]);
  //     }
  // }
  public function assignedPlaces()
  {
    return $this->belongsToMany(Place::class, 'place_zone');
  }

  // public function governorates()
  // {
  //     $governorates = DB::table('governorates AS g')
  //         ->join('zones AS z', 'z.id', '=', DB::raw($this->id))
  //         ->whereRaw('ST_Contains(z.coordinates, ST_GeomFromText(CONCAT("POINT(", g.lng, " ", g.lat, ")"), 4326))')
  //         ->select('g.*')
  //         ->get();

  //     return $governorates;
  // }

  // public function states()
  // {
  //     return DB::table('states AS s')
  //         ->join('zones AS z', 'z.id', '=', DB::raw($this->id))
  //         ->whereRaw('ST_Contains(z.coordinates, ST_GeomFromText(CONCAT("POINT(", s.lng, " ", s.lat, ")"), 4326))')
  //         ->select('s.*')
  //         ->get();
  // }

  // public function places()
  // {
  //     return DB::table('places AS p')
  //         ->join('zones AS z', 'z.id', '=', DB::raw($this->id))
  //         ->whereRaw('ST_Contains(z.coordinates, ST_GeomFromText(CONCAT("POINT(", p.lng, " ", p.lat, ")"), 4326))')
  //         ->select('p.*')
  //         ->get();
  // }

  // public function getRawCoordinates()
  // {
  //     return $this->getRawOriginal('coordinates');
  // }

  // public function getCoordinatesAttribute($value)
  // {
  //     $id = $this->id;
  //     $result = DB::selectOne(
  //         'SELECT ST_AsGeoJSON(coordinates) AS geojson 
  //          FROM zones 
  //          WHERE id = ?',
  //         [$id]
  //     );

  //     return optional($result)->geojson ? json_decode($result->geojson, true) : null;
  // }

  public function owner()
  {
    return $this->morphTo();
  }

  public function delivery_commissions()
  {
    return $this->hasMany(DeliveryCommission::class, 'zone_id');
  }

  public function scopeByOwner($query)
  {
    $facility = facility();

    if ($facility) {
      $query->where('owner_type', $facility->type)
        ->where('owner_id', $facility->id);
    }
    // if ($user && $selectedWorkspaceId) {
    //     $query->where(function ($query) use ($user, $selectedWorkspaceId) {
    //         if ($user->branch_user) {
    //             $query->where('owner_type', Branch::class)
    //                 ->where('owner_id', $selectedWorkspaceId);
    //         }

    //         if ($user->station_user) {
    //             $query->orWhere('owner_type', Station::class)
    //                 ->where('owner_id', $selectedWorkspaceId);
    //         }

    //         if ($user->hub_user) {
    //             $query->orWhere('owner_type', Hub::class)
    //                 ->where('owner_id', $selectedWorkspaceId);
    //         }
    //     });
    // }

    return $query;
  }


  public function shipments()
  {
    return $this->hasManyThrough(
      Shipment::class,
      ZoneShipment::class,
      'zone_id',          // Foreign key on zone_shipments referencing zones.id
      'tracking_no',      // Foreign key on shipments matching zone_shipments.shipment_tracking_no
      'id',               // Local key on zones
      'shipment_tracking_no' // Local key on zone_shipments
    );
  }


  /**
   * Merge zone coordinates with a state polygon using ST_Union
   * 
   * @param State $state The state to merge with
   * @return bool True if successful, false otherwise
   */
  public function mergeWithState(State $state): bool
  {
    try {
      // Get zone coordinates as WKT
      $zoneWkt = DB::selectOne("SELECT ST_AsText(coordinates) as wkt FROM zones WHERE id = ?", [$this->id])->wkt;
      if (!$zoneWkt) {
        throw new \Exception('Failed to get zone coordinates as WKT');
      }

      // Get state polygon as WKT
      $stateWkt = DB::selectOne("SELECT ST_AsText(polygon) as wkt FROM states WHERE id = ?", [$state->id])->wkt;
      if (!$stateWkt) {
        throw new \Exception('Failed to get state polygon as WKT');
      }

      // Perform ST_Union between zone coordinates and state polygon
      $unionQuery = "SELECT ST_AsText(ST_Union(ST_GeomFromText(?), ST_GeomFromText(?))) as merged";
      $unionResult = DB::selectOne($unionQuery, [$zoneWkt, $stateWkt]);

      if (!$unionResult || !$unionResult->merged) {
        throw new \Exception('Failed to merge zone coordinates and state polygon');
      }

      // Convert the merged geometry to GeoJSON
      $geojsonResult = DB::selectOne("SELECT ST_AsGeoJSON(ST_GeomFromText(?)) as geojson", [$unionResult->merged]);

      if (!$geojsonResult || !$geojsonResult->geojson) {
        throw new \Exception('Failed to convert merged geometry to GeoJSON');
      }

      $geojson = $geojsonResult->geojson;

      // Update the zone with the merged geometry
      $this->update([
        'name' => $this->name . ' + ' . $state->en_name,
        'coordinates' => DB::raw("ST_GeomFromGeoJSON('" . addslashes($geojson) . "')"),
      ]);

      return true;
    } catch (\Exception $e) {
      Log::error('Failed to merge zone with state: ' . $e->getMessage());
      return false;
    }
  }
}
