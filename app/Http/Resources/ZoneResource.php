<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class ZoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    // public function toArray(Request $request): array
    // {
    //     return parent::toArray($request) + [
    //         'coordinates' => $this->coordinates_geojson,
    //         'governorates' => $this->governorates(),
    //         'selected_states' => $this->selectedStates->map(fn($s) => [
    //             'id' => $s->id,
    //             'en_name' => $s->en_name,
    //             'ar_name' => $s->ar_name,

    //         ]),
    //         'places' => $this->assignedPlaces->map(function ($place) {
    //             return [
    //                 'id' => $place->id,
    //                 'en_name' => $place->en_name,
    //                 'ar_name' => $place->ar_name,
    //             ];
    //         }),
    //     ];
    // }
    // App\Http\Resources\ZoneResource.php
    public function toArray($request): array
    {
        $geo = $this->coordinates_geojson ?? null;

        if (!$geo && $this->coordinates) {
            $row = \DB::selectOne("SELECT ST_AsGeoJSON(coordinates) AS g FROM zones WHERE id = ?", [$this->id]);
            $geo = $row?->g ?? null;
        }

        if (is_string($geo)) {
            $geo = json_decode($geo, true);
        } elseif (!is_array($geo)) {
            $geo = null;
        }

        $latLngRing = [];
        if (is_array($geo) && isset($geo['type'], $geo['coordinates'])) {
            $ring = $geo['type'] === 'Polygon'
                ? ($geo['coordinates'][0] ?? [])
                : ($geo['type'] === 'MultiPolygon' ? ($geo['coordinates'][0][0] ?? []) : []);
            $latLngRing = array_map(fn($p) => ['lat' => $p[1], 'lng' => $p[0]], $ring);
        }

        return parent::toArray($request) + [
            'coordinates' => $geo,         
            'coordinates_latlng' => $latLngRing,  
            'governorates' => $this->governorates(),
            'selected_states' => $this->selectedStates->map(fn($s) => [
                'id' => $s->id,
                'en_name' => $s->en_name,
                'ar_name' => $s->ar_name,
            ]),
            'places' => $this->assignedPlaces->map(fn($p) => [
                'id' => $p->id,
                'en_name' => $p->en_name,
                'ar_name' => $p->ar_name,
            ]),
        ];
    }


}
