<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MapsUnshortenController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $data['url'];

        $host = parse_url($url, PHP_URL_HOST);
        $allowedHosts = [
            'maps.app.goo.gl',
            'goo.gl',
            'www.google.com',
            'google.com',
            'maps.google.com',
        ];

        if (! $host || ! in_array($host, $allowedHosts, true)) {
            return response()->json([
                'message' => 'Invalid URL host',
            ], 422);
        }

        try {
            $response = Http::withOptions([
                'allow_redirects' => true,
            ])->timeout(10)->get($url);

            if (! $response->successful()) {
                return response()->json([
                    'message' => 'Failed to resolve URL',
                ], 502);
            }

            $effectiveUrl = (string) $response->effectiveUri();

            // If redirected to Google consent page, extract the actual URL from 'continue' parameter
            $parsedUrl = parse_url($effectiveUrl);
            if (isset($parsedUrl['host']) && str_contains($parsedUrl['host'], 'consent.google.com')) {
                parse_str($parsedUrl['query'] ?? '', $queryParams);
                if (isset($queryParams['continue'])) {
                    $effectiveUrl = $queryParams['continue'];
                }
            }

            // Extract coordinates and build standard Google Maps URL
            $coordinates = $this->extractCoordinates($effectiveUrl);

            if ($coordinates) {
                [$lat, $lng] = $coordinates;
                $finalUrl = $this->buildMapsUrl($lat, $lng);
            } else {
                // Fallback to original URL if coordinates cannot be extracted
                $finalUrl = $effectiveUrl;
            }

            return response()->json([
                'finalUrl' => $finalUrl,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to resolve URL',
            ], 502);
        }
    }

    /**
     * Extract lat/lng coordinates from Google Maps URL.
     * Returns [lat, lng] or null if extraction fails.
     */
    protected function extractCoordinates(string $url): ?array
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        // We expect something like: /maps/search/23.609084,+58.034884
        if (str_starts_with($path, '/maps/search/')) {
            $coordPart = substr($path, strlen('/maps/search/')); // "23.609084,+58.034884"

            // URL decode and clean the coordinate part
            $coordPart = urldecode($coordPart);
            $coordPart = trim($coordPart);

            // Remove spaces and plus signs
            $coordPart = str_replace([' ', '+'], '', $coordPart);

            // Split by comma to get lat and lng separately
            $coords = explode(',', $coordPart);

            if (count($coords) === 2) {
                $lat = trim($coords[0]);
                $lng = trim($coords[1]);

                // Validate that both are numeric values
                if (is_numeric($lat) && is_numeric($lng)) {
                    return [$lat, $lng];
                }
            }
        }

        return null;
    }

    /**
     * Build standard Google Maps URL format: https://www.google.com/maps/search/?api=1&query=<lat>,<lng>
     */
    protected function buildMapsUrl(string $lat, string $lng): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . $lat . ',' . $lng;
    }
}

