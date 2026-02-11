<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GoogleMapsService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_key') ?? env('GOOGLE_MAPS_API_KEY');
    }

    public function geocode(string $freeText): array
    {
        $resp = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $freeText,
            'key' => $this->apiKey,
            'language' => config('app.locale', 'en'),
        ]);

        if (!$resp->ok())
            return [];

        $data = $resp->json();
        if (Arr::get($data, 'status') !== 'OK')
            return [];

        $best = Arr::get($data, 'results.0', []);
        return $this->extractFromResult($best);
    }

    public function reverse(float $lat, float $lng): array
    {
        $resp = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => "{$lat},{$lng}",
            'key' => $this->apiKey,
            'language' => config('app.locale', 'en'),
        ]);

        if (!$resp->ok())
            return [];

        $data = $resp->json();
        if (Arr::get($data, 'status') !== 'OK')
            return [];

        $best = Arr::get($data, 'results.0', []);
        return $this->extractFromResult($best);
    }

    protected function extractFromResult(array $result): array
    {
        $components = collect(Arr::get($result, 'address_components', []));
        $get = fn(string $type) =>
            optional($components->first(fn($c) => in_array($type, $c['types'] ?? [])))['long_name'] ?? null;

        $admin1 = $get('administrative_area_level_1');
        $admin2 = $get('administrative_area_level_2');
        $country = $get('country');

        return array_filter([
            'formatted_address' => Arr::get($result, 'formatted_address'),
            'latitude' => Arr::get($result, 'geometry.location.lat'),
            'longitude' => Arr::get($result, 'geometry.location.lng'),
            'state_name' => $admin1,
            'governorate_name' => $admin2,
            'country_name' => $country,
        ], fn($v) => !is_null($v) && $v !== '');
    }
}
