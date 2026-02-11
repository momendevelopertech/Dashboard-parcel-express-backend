<?php

namespace App\Services\Geocoding;

use GuzzleHttp\Client;
use OpenLocationCode\OpenLocationCode;
use Illuminate\Support\Str;
use App\Models\GeocodeCache;
use Exception;

class AiGeocodingService
{
    protected Client $http;
    protected string $gmApiKey;
    protected string $geminiKey;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 15.0]);
        $this->gmApiKey = env('GOOGLE_MAPS_API_KEY');
        $this->geminiKey = env('GEMINI_API_KEY');
    }

    public function looksLikePlusCode(string $s): bool
    {
        $s = trim($s);
        return (bool) preg_match('/[A-Za-z0-9]{2,}\+[A-Za-z0-9]{2,}/', $s);
    }
    protected function decodePlusCode(string $plus, ?string $stateVal = null, ?string $countryBias = 'Oman'): ?array
    {
        try {
            $code = trim($plus);
            if (strlen($code) < 10 && $stateVal) {
                $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
                    'address' => $stateVal . ', ' . $countryBias,
                    'key' => $this->gmApiKey
                ]);

                $r = $this->http->get($url);
                $body = json_decode((string) $r->getBody(), true);
                if (($body['status'] ?? '') === 'OK' && !empty($body['results'])) {
                    $loc = $body['results'][0]['geometry']['location'];
                    $referenceLat = $loc['lat'];
                    $referenceLng = $loc['lng'];
                    $full = OpenLocationCode::recoverNearest($code, $referenceLat, $referenceLng);
                    $decoded = OpenLocationCode::decode($full);
                    $lat = ($decoded['latitudeLo'] + $decoded['latitudeHi']) / 2.0;
                    $lng = ($decoded['longitudeLo'] + $decoded['longitudeHi']) / 2.0;

                    return [
                        'lat' => $lat,
                        'lng' => $lng,
                        'plus_code' => $full,
                        'confidence' => 0.99,
                        'raw' => $decoded
                    ];
                }
            }
            $decoded = OpenLocationCode::decode($code);
            $lat = ($decoded['latitudeLo'] + $decoded['latitudeHi']) / 2.0;
            $lng = ($decoded['longitudeLo'] + $decoded['longitudeHi']) / 2.0;

            return [
                'lat' => $lat,
                'lng' => $lng,
                'plus_code' => $code,
                'confidence' => 0.99,
                'raw' => $decoded
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function callGeminiParse(string $address, string $countryBias = 'Oman', $stateVal): ?array
    {
        if (empty($this->geminiKey))
            return null;
        $prompt = <<<PROMPT
            You are a JSON extractor for postal addresses (Arabic & English). 
            Input: "$address"
            Country bias: "$countryBias"
            State bias: "$stateVal"
            Output only a single JSON object (no extra text) with keys:
            {"plus_code":null,"country":null,"governorate":null,"city":null,"street":null,"landmark":null,"confidence":null}
            Set fields to null if unknown. confidence = 0..1.
            PROMPT;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $this->geminiKey;
        try {
            $resp = $this->http->post($url, [
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    'temperature' => 0.0
                ],
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
            $body = json_decode((string) $resp->getBody(), true);
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!$text)
                return null;
            if (preg_match('/\{.*\}/s', $text, $m)) {
                $json = $m[0];
                $data = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE)
                    return $data;
            }
            $d = json_decode($text, true);
            return json_last_error() === JSON_ERROR_NONE ? $d : null;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function googleGeocode(string $query, string $countryIso = 'OM', ?array $bounds = null): ?array
    {
        $params = [
            'address' => $query,
            'key' => $this->gmApiKey,
            'language' => 'ar',
            'components' => "country:{$countryIso}"
        ];
        if ($bounds) {
            $params['bounds'] = $bounds;
        }
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query($params);
        try {
            $r = $this->http->get($url);
            $body = json_decode((string) $r->getBody(), true);
            if (($body['status'] ?? '') === 'OK' && !empty($body['results'])) {
                $top = $body['results'][0];
                return [
                    'lat' => $top['geometry']['location']['lat'],
                    'lng' => $top['geometry']['location']['lng'],
                    'place_type' => $top['types'] ?? [],
                    'raw' => $top,
                    'confidence' => 0.8
                ];
            }
        } catch (\Throwable $e) {
            //
        }
        return null;
    }
    public function deriveFromStreetAddress(string $street, string $countryBias = 'Oman', string $stateVal): ?array
    {
        $normalized = trim($street);
        if ($this->looksLikePlusCode($normalized)) {
            $decoded = $this->decodePlusCode($normalized, $stateVal, $countryBias);
            if ($decoded)
                return $decoded;
        }
        $cacheKey = Str::lower(preg_replace('/\s+/', ' ', $normalized));
        $cached = GeocodeCache::where('query', $cacheKey)->first();
        if ($cached) {
            return [
                'lat' => (float) $cached->lat,
                'lng' => (float) $cached->lng,
                'raw' => json_decode($cached->raw_response ?? '{}', true),
                'confidence' => (float) $cached->confidence
            ];
        }
        $parsed = $this->callGeminiParse($normalized, $countryBias, $stateVal);
        $candidates = [];
        if (!empty($parsed['plus_code']))
            $candidates[] = $parsed['plus_code'];
        $pieces = [];
        foreach (['street', 'city', 'governorate', 'country', 'landmark'] as $k) {
            if (!empty($parsed[$k]))
                $pieces[] = $parsed[$k];
        }
        if (!empty($pieces))
            $candidates[] = implode(', ', $pieces);
        $candidates[] = $normalized;
        foreach ($candidates as $q) {
            $res = $this->googleGeocode($q, 'OM');
            if ($res) {
                GeocodeCache::updateOrCreate(
                    ['query' => $cacheKey],
                    ['lat' => $res['lat'], 'lng' => $res['lng'], 'raw_response' => json_encode($res['raw']), 'confidence' => $res['confidence']]
                );
                return $res;
            }
        }
        return null;
    }
}