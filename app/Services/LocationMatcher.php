<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LocationMatcher
{
    protected string $statesTable = 'states';
    protected string $governoratesTable = 'governorates';

    /**
     * Match state_id by name (and optionally by proximity if lat/lng available in table).
     */
    public function matchStateId(?string $stateName, ?float $lat = null, ?float $lng = null): ?int
    {
        if ($stateName) {
            $id = $this->matchByName($this->statesTable, $stateName);
            if ($id)
                return $id;
        }

        if (!is_null($lat) && !is_null($lng) && $this->hasLatLng($this->statesTable)) {
            $id = $this->nearestByLatLng($this->statesTable, $lat, $lng);
            if ($id)
                return $id;
        }

        return null;
    }

    /**
     * Match governorate_id by name with optional parent state_id and proximity fallback.
     */
    public function matchGovernorateId(?string $govName, ?int $stateId = null, ?float $lat = null, ?float $lng = null): ?int
    {
        if ($govName) {
            $id = $this->matchByName($this->governoratesTable, $govName, $stateId);
            if ($id)
                return $id;
        }

        if (!is_null($lat) && !is_null($lng) && $this->hasLatLng($this->governoratesTable)) {
            $id = $this->nearestByLatLng($this->governoratesTable, $lat, $lng, $stateId);
            if ($id)
                return $id;
        }

        return null;
    }

    protected function norm(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('~\b(governorate|province|state|wilayat|district|mintaqah)\b~i', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return Str::lower(Str::slug($name));
    }

    protected function matchByName(string $table, string $name, ?int $parentStateId = null): ?int
    {
        $needle = $this->norm($name);

        $query = DB::table($table)->select('id', 'name');

        if ($parentStateId && DB::getSchemaBuilder()->hasColumn($table, 'state_id')) {
            $query->where('state_id', $parentStateId);
        }

        $rows = $query->get()->map(function ($r) {
            $r->slug = Str::lower(Str::slug($r->name));
            return $r;
        });

        $hit = $rows->first(fn($r) => $r->slug === $needle);
        if ($hit)
            return $hit->id;

        $hit = $rows->first(fn($r) => Str::contains($r->slug, $needle) || Str::contains($needle, $r->slug));
        if ($hit)
            return $hit->id;

        if (DB::getSchemaBuilder()->hasColumn($table, 'alt_names')) {
            $altRows = DB::table($table)->select('id', 'alt_names')->when($parentStateId && DB::getSchemaBuilder()->hasColumn($table, 'state_id'), function ($q) use ($parentStateId) {
                $q->where('state_id', $parentStateId);
            })->get();

            foreach ($altRows as $row) {
                $alts = collect(explode(',', (string) $row->alt_names))->map(fn($s) => Str::lower(Str::slug(trim($s))));
                if ($alts->contains($needle))
                    return $row->id;
            }
        }

        return null;
    }

    protected function hasLatLng(string $table): bool
    {
        $schema = DB::getSchemaBuilder();
        return $schema->hasColumn($table, 'center_lat') && $schema->hasColumn($table, 'center_lng');
    }

    protected function nearestByLatLng(string $table, float $lat, float $lng, ?int $parentStateId = null): ?int
    {
        $query = DB::table($table)->select('id')
            ->when($parentStateId && DB::getSchemaBuilder()->hasColumn($table, 'state_id'), function ($q) use ($parentStateId) {
                $q->where('state_id', $parentStateId);
            })
            ->orderByRaw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(center_lat)) * cos(radians(center_lng) - radians(?))
                    + sin(radians(?)) * sin(radians(center_lat))
                )) asc
            ", [$lat, $lng, $lat])
            ->limit(1);

        $row = $query->first();
        return $row->id ?? null;
    }
}
