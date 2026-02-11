<?php
namespace App\Services\Geocoding;

use GuzzleHttp\Merchant;
use OpenLocationCode\OpenLocationCode; // من bogdaan/open-location-code

class ReverseGeocodingService
{
    // 1) استخرج الـ Plus Code من أي نص (قصير أو كامل)
    public function extractPlusCode(string $text): ?string
    {
        // حروف OLC المسموحة + وجود علامة +
        if (preg_match('/([23456789CFGHJMPQRVWX]{2,8}\+[23456789CFGHJMPQRVWX]{2,3})/i', $text, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    public function isPlusCode(string $text): bool
    {
        return (bool) $this->extractPlusCode($text);
    }

    // 2) فكّ الكود “الكامل” فقط -> مركز الخلية (لاحظ: decode بيرجع Array)
    public function latLngFromPlusCode(string $plus): ?array
    {
        try {
            $area = OpenLocationCode::decode($plus); // Array: latitudeLo/latitudeHi/longitudeLo/longitudeHi
            $lat = ($area['latitudeLo'] + $area['latitudeHi']) / 2;
            $lng = ($area['longitudeLo'] + $area['longitudeHi']) / 2;
            return ['lat' => $lat, 'lng' => $lng];
        } catch (\Throwable $e) {
            return null;
        }
    }

    // 3) Google forward geocoding للنص كله (أفضل حل للـ short plus code: "H9GM+56 Muscat, Oman")
    protected function googleGeocodeAddress(string $address): ?array
    {
        $key = env('GOOGLE_MAPS_API_KEY');
        if (!$key)
            return null;

        $merchant = new Merchant(['base_uri' => 'https://maps.googleapis.com/maps/api/geocode/']);
        $resp = $merchant->get('json', [
            'query' => [
                'address' => $address,
                'key' => $key,
                'language' => 'en',
            ]
        ]);
        $data = json_decode($resp->getBody()->getContents(), true);
        if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0]))
            return null;

        $r = $data['results'][0];
        $loc = $r['geometry']['location'];
        $cmp = $r['address_components'];

        $pick = function (array $types) use ($cmp) {
            foreach ($cmp as $c) {
                if (count(array_intersect($c['types'] ?? [], $types)) > 0) {
                    return $c['long_name'] ?? $c['short_name'] ?? null;
                }
            }
            return null;
        };

        return [
            'lat' => $loc['lat'],
            'lng' => $loc['lng'],
            'city' => $pick(['locality', 'sublocality', 'postal_town', 'administrative_area_level_2']),
            'state' => $pick(['administrative_area_level_1']),
            'country' => $pick(['country']),
        ];
    }

    // 4) Reverse (جوجل أو Nominatim)
    public function reverse(float $lat, float $lng): ?array
    {
        $provider = env('GEOCODING_PROVIDER', 'nominatim');
        if ($provider === 'google' && ($key = env('GOOGLE_MAPS_API_KEY'))) {
            $merchant = new Merchant(['base_uri' => 'https://maps.googleapis.com/maps/api/geocode/']);
            $resp = $merchant->get('json', [
                'query' => [
                    'latlng' => "{$lat},{$lng}",
                    'key' => $key,
                    'language' => 'en',
                ]
            ]);
            $data = json_decode($resp->getBody()->getContents(), true);
            if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0]))
                return null;

            $cmp = $data['results'][0]['address_components'] ?? [];
            $pick = function (array $types) use ($cmp) {
                foreach ($cmp as $c) {
                    if (count(array_intersect($c['types'] ?? [], $types)) > 0) {
                        return $c['long_name'] ?? $c['short_name'] ?? null;
                    }
                }
                return null;
            };

            return [
                'city' => $pick(['locality', 'sublocality', 'postal_town', 'administrative_area_level_2']),
                'state' => $pick(['administrative_area_level_1']),
                'country' => $pick(['country']),
            ];
        }

        // Nominatim
        $merchant = new Merchant(['base_uri' => 'https://nominatim.openstreetmap.org', 'timeout' => 15]);
        $resp = $merchant->get('/reverse', [
            'query' => [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'json',
                'zoom' => 10,
                'addressdetails' => 1,
                'email' => env('NOMINATIM_EMAIL', ''),
            ],
            'headers' => ['User-Agent' => 'Laravel-Reverse-Geocoder/1.0 (+contact: ' . env('NOMINATIM_EMAIL', '') . ')']
        ]);
        $d = json_decode($resp->getBody()->getContents(), true);
        if (empty($d['address']))
            return null;

        $a = $d['address'];
        return [
            'city' => $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['suburb'] ?? null,
            'state' => $a['state'] ?? $a['region'] ?? $a['county'] ?? null,
            'country' => $a['country'] ?? null,
        ];
    }

    // 5) قلب الخدمة: اشتقّ state/city من street_address
    public function deriveFromStreetAddress(string $streetAddress, ?string $countryBias = null): ?array
    {
        $streetAddress = trim($streetAddress);
        if ($streetAddress === '')
            return null;

        // (أ) لو فيه Google Key: خلّي جوجل يحل العنوان كله (أفضل للـ short code)
        if (env('GEOCODING_PROVIDER') === 'google' && env('GOOGLE_MAPS_API_KEY')) {
            if ($g = $this->googleGeocodeAddress($streetAddress)) {
                return [
                    'state' => $g['state'] ?? null,
                    'city' => $g['city'] ?? null,
                    'lat' => $g['lat'] ?? null,
                    'lng' => $g['lng'] ?? null,
                    'plus_code' => $this->extractPlusCode($streetAddress),
                ];
            }
        }

        // (ب) بدون Google: نفكّ بس الكود “الكامل” (short غير مدعوم هنا)
        if ($plus = $this->extractPlusCode($streetAddress)) {
            if (strlen($plus) >= 10) { // full code مثل 7JVW52GR+2V
                if ($coords = $this->latLngFromPlusCode($plus)) {
                    if ($rev = $this->reverse($coords['lat'], $coords['lng'])) {
                        return [
                            'state' => $rev['state'] ?? null,
                            'city' => $rev['city'] ?? null,
                            'lat' => $coords['lat'],
                            'lng' => $coords['lng'],
                            'plus_code' => $plus,
                        ];
                    }
                }
            }
        }

        return null;
    }
}
