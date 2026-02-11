<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RouteOptimizationService
{
    public function optimize(array $waypoints, array $options = [])
    {
        // Validate waypoints
        foreach ($waypoints as $index => $point) {
            if (!isset($point['latitude']) || !isset($point['longitude'])) {
                throw new \Exception("Invalid waypoint coordinates at index {$index}");
            }
            if (!is_numeric($point['latitude']) || !is_numeric($point['longitude'])) {
                throw new \Exception("Invalid numeric coordinates at index {$index}");
            }
        }
        Log::info('Route Optimization Process', [
            'waypoints_count' => count($waypoints),
            'waypoints_details' => collect($waypoints)->map(function ($point, $index) {
                return [
                    'index' => $index + 1,
                    'shipment_id' => $point['shipment_id'],
                    'coordinates' => $point['latitude'] . ', ' . $point['longitude'],
                    'address' => $point['address']
                ];
            })->toArray(),
            'options' => $options
        ]);

        // Log API key (for debugging)
        Log::info('Google Maps API Configuration', [
            'api_key_set' => config('services.google.maps_api_key') !== null,
            'api_key_length' => strlen(config('services.google.maps_api_key') ?? '')
        ]);

        // Prepare waypoints string
        $waypointsString = collect($waypoints)
            ->map(function ($point) {
                return "{$point['latitude']},{$point['longitude']}";
            })
            ->implode('|');

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin'      => $options['origin'] ?? $waypoints[0]['latitude'] . "," . $waypoints[0]['longitude'],
                'destination' => $options['destination'] ?? $waypoints[count($waypoints) - 1]['latitude'] . "," . $waypoints[count($waypoints) - 1]['longitude'],
                'waypoints'   => 'optimize:true|' . $waypointsString,
                'key'         => config('services.google.maps_api_key'),
            ]);

            $data = $response->json();
            Log::info('Google Maps API Response', [
                'status' => $data['status'],
                'error_message' => $data['error_message'] ?? null,
                'routes_count' => isset($data['routes']) ? count($data['routes']) : 0
            ]);

            if ($data['status'] !== 'OK') {
                throw new \Exception(
                    "Failed to optimize route: " . $data['status'] .
                        (isset($data['error_message']) ? " - " . $data['error_message'] : '')
                );
            }
        } catch (\Exception $e) {
            Log::error('Google Maps API Error', [
                'error' => $e->getMessage(),
                'response' => $response?->json() ?? null
            ]);
            throw $e;
        }
        $shipmentedStops = collect($data['routes'][0]['legs'])
            ->flatMap(function ($leg) {
                return $leg['steps'];
            })
            ->map(function ($step) {
                return [
                    'shipment_id' => $step['shipment_id'] ?? null,
                    'latitude' => $step['end_location']['lat'],
                    'longitude' => $step['end_location']['lng'],
                    'address' => $step['html_instructions'] ?? null
                ];
            })
            ->toArray();
        $temp = [
            'shipmented_stops' => $shipmentedStops,
            'total_distance' => $data['routes'][0]['legs'][0]['distance']['value'] / 1000, // Convert to km
            'total_duration' => $data['routes'][0]['legs'][0]['duration']['value'] / 60, // Convert to minutes
            'polyline' => $data['routes'][0]['overview_polyline']['points'] ?? null,
        ];
        Log::info('Route Optimization Process', [
            'temp' => $temp
        ]);
        return $temp;
    }
}
