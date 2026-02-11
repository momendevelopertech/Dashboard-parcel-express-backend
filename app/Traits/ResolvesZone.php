<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait ResolvesZone
{
    protected function matchPointToZone(float $lng, float $lat)
    {

        return DB::table('zones as z')
            ->join('zone_polygons as zp', 'zp.zone_id', '=', 'z.id')
            ->whereRaw("ST_Contains(zp.polygon, ST_GeomFromText(?))", ["POINT($lng $lat)"])
            ->select('z.id', 'z.name', 'z.owner_id', 'z.owner_type')
            ->first();
    }

    protected function matchPolygonToZone(string $table, int $id, string $polygonColumn = 'polygon')
    {
        return DB::table('zones as z')
            ->join('zone_polygons as zp', 'zp.zone_id', '=', 'z.id')
            ->join($table . ' as t', 't.id', '=', DB::raw((int) $id))
            ->whereRaw("ST_Intersects(zp.polygon, t.$polygonColumn)")
            ->select('z.id', 'z.name', 'z.owner_id', 'z.owner_type')
            ->first();
    }
}
