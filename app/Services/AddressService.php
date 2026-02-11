<?php

namespace App\Services;

use App\Models\Consignee;
use App\Models\Governorate;
use App\Models\Shipment;
use App\Models\State;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class AddressService
{
    /**
     * Generate address update token for consignee
     *
     * @param Shipment $shipment
     * @return array
     * @throws Exception
     */
    public function generateAddressUpdateToken(Shipment $shipment)
    {
        if (!$shipment->consignee) {
            throw new Exception('No consignee associated with this shipment');
        }

        // 1) token (زي ما هو عندك)
        $plainTextToken = Str::random(40);
        $shipment->consignee->update([
            'update_token' => Hash::make($plainTextToken),
            'token_expires_at' => now()->addHours(24),
        ]);

        $otp = (string) random_int(100000, 999999);
        $shipment->consignee->forceFill([
            'address_update_otp' => $otp,
            'address_update_otp_expires_at' => now()->addMinutes(30),
        ])->save();

        // 3) اللينك
        $marketingUrl = rtrim(env("MARKETING_URL", "https://parcelexpress.om"), '/');
        $updateLink = "{$marketingUrl}/update-address/{$shipment->tracking_no}/{$plainTextToken}";

        return [
            'token' => $plainTextToken,
            'otp' => $otp,              // لو محتاجه للرسالة
            'url' => $updateLink,
            'expires_at' => $shipment->consignee->token_expires_at,
        ];
    }

    /**
     * Validate address update token
     *
     * @param string $trackingNo
     * @param string $plainToken
     * @return bool
     */
    public function validateAddressUpdateToken($trackingNo, $plainToken)
    {
        $shipment = Shipment::with('consignee')
            ->where('tracking_no', $trackingNo)
            ->first();

        if (!$shipment || !$shipment->consignee) {
            return false;
        }

        $consignee = $shipment->consignee;

        // Check if token exists and hasn't expired
        if (!$consignee->update_token || !$consignee->token_expires_at) {
            return false;
        }

        if (Carbon::now()->isAfter($consignee->token_expires_at)) {
            return false;
        }

        // Verify token
        return Hash::check($plainToken, $consignee->update_token);
    }

    /**
     * Clear address update token after use
     *
     * @param Consignee $consignee
     */
    public function clearAddressUpdateToken(Consignee $consignee)
    {
        $consignee->update([
            'update_token' => null,
            'token_expires_at' => null
        ]);
    }

    /**
     * Parse address input (plain text or Google Maps link) and return structured data
     *
     * @param string $input  Raw address string or Google Maps URL
     * @return array{
     *     streetAddress:string,
     *     latitude:float|null,
     *     longitude:float|null
     * }
     *
     * @throws Exception When coordinates cannot be extracted from a maps link or geocoding fails
     */
    public function parseInputAddress(string $input): array
    {
        // If the input is not a valid URL treat it as plain address text
        if (!filter_var($input, FILTER_VALIDATE_URL)) {
            return [
                'streetAddress' => $input,
                'latitude' => null,
                'longitude' => null,
            ];
        }

        // Follow redirects to get the final URL (Google sometimes adds redirects)
        $response = Http::withOptions(['verify' => public_path('certs/cacert.pem')])
            ->get($input);
        $finalUrl = (string) $response->effectiveUri();

        // Attempt to extract coordinates from several common patterns
        $coords = null;
        // Pattern 1: /@lat,lng
        if (preg_match('/@([+\-]?\d+\.\d+),([+\-]?\d+\.\d+)/', $finalUrl, $matches)) {
            $coords = ['lat' => $matches[1], 'lng' => $matches[2]];
        }
        // Pattern 2: /search/lat,lng or /place/lat,lng or /dir/lat,lng
        elseif (preg_match('~/(?:search|place|dir)/?([+\-]?\d+\.\d+),([+\-]?\d+\.\d+)~', $finalUrl, $matches)) {
            $coords = ['lat' => $matches[1], 'lng' => $matches[2]];
        }
        // Pattern 3: ?q=lat,lng or ?query=lat,lng or ?center=lat,lng
        elseif ($query = parse_url($finalUrl, PHP_URL_QUERY)) {
            parse_str($query, $params);
            foreach (['q', 'query', 'center'] as $key) {
                if (!empty($params[$key]) && preg_match('/([+\-]?\d+\.\d+),([+\-]?\d+\.\d+)/', $params[$key], $matches)) {
                    $coords = ['lat' => $matches[1], 'lng' => $matches[2]];
                    break;
                }
            }
        }

        // if (!$coords) {
        //     throw new Exception('Unable to extract coordinates from maps link.');
        // }

        if (!$coords) {
            return [
                'streetAddress' => $input,
                'latitude' => null,
                'longitude' => null,
            ];
        }

        // Normalize coordinates (strip leading plus signs)
        $lat = (float) ltrim($coords['lat'], '+');
        $lng = (float) ltrim($coords['lng'], '+');
        // Reverse geocode to get formatted address using Google Maps API
        $googleKey = env('GOOGLE_MAPS_API_KEY', "AIzaSyCIlsv3vrcTW7qXJJb2XkyZjvfxqcrvSss");

        $geoResponse = Http::withOptions(['verify' => public_path('certs/cacert.pem')])->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => "$lat,$lng",
            'key' => $googleKey,
        ]);

        $geoData = $geoResponse->json();
        if (($geoData['status'] ?? '') !== 'OK' || empty($geoData['results'])) {
            $error = $geoData['error_message'] ?? 'Geocoding failed or no results';
            throw new Exception('Google API error: ' . $error);
        }

        $formattedAddress = $geoData['results'][0]['formatted_address'];
        $components = Arr::get($geoData, 'results.0.address_components', []);
        $get = function (array $components, string $type) {
            foreach ($components as $c) {
                if (in_array($type, $c['types'] ?? [])) {
                    return $c['long_name'] ?? null;
                }
            }
            return null;
        };

        // IMPORTANT: في عمان
        $adm1 = $get($components, 'administrative_area_level_1'); // Governorate
        $adm2 = $adm2
            ?? $get($components, 'administrative_area_level_3')
            ?? $get($components, 'locality')              // مدينة/ولاية أحيانًا
            ?? $get($components, 'postal_town')
            ?? $get($components, 'sublocality_level_1')
            ?? $get($components, 'sublocality')
            ?? $get($components, 'neighborhood');

        $normalize = function (?string $s) {
            if (!$s)
                return null;
            $s = trim($s);

            // إزالة كلمات شائعة بالإنجليزي
            $replacements = [
                '/\b(governorate|province|state|wilayat|district|mintaqah)\b/i' => '',
                '/\b(al|el)\b/i' => ' ',         // AL/EL الإنجليزية
                '/\s+/' => ' ',                  // مسافات متكررة
            ];
            foreach ($replacements as $pat => $rep) {
                $s = preg_replace($pat, $rep, $s);
            }

            // تطبيع عربي باستخدام preg_replace_callback (مش preg_replace)
            $s = preg_replace_callback('/\p{Arabic}+/u', function ($m) {
                $x = $m[0];
                $x = str_replace(['محافظة', 'ولاية', 'منطقة'], '', $x);
                $x = preg_replace('/\s+/', ' ', $x);
                $x = str_replace(['أ', 'إ', 'آ'], 'ا', $x);
                $x = str_replace('ة', 'ه', $x);
                return $x;
            }, $s);

            return \Illuminate\Support\Str::lower(trim($s));
        };


        $govNameNorm = $normalize($adm1); // Governorate
        $stateNameNorm = $normalize($adm2); // State/Wilayah

        $stateId = null;
        $govId = null;

        if ($govNameNorm) {
            // حوّل الاسمTokens (مثلاً: ["batinah","north"])
            $tokens = array_values(array_filter(explode(' ', $govNameNorm)));

            $govId = DB::table('governorates')
                ->select('id')
                ->where(function ($q) use ($tokens) {
                    // دوّر على كل token داخل en_name أو ar_name (case-insensitive)
                    foreach ($tokens as $t) {
                        $q->where(function ($qq) use ($t) {
                            $qq->whereRaw('LOWER(en_name) LIKE ?', ['%' . $t . '%'])
                                ->orWhereRaw('LOWER(ar_name) LIKE ?', ['%' . $t . '%']);
                        });
                    }
                })
                ->value('id');

            // Fallback مكاني لو الاسم فشل
            if (!$govId && $lat && $lng) {
                $row = DB::table('governorates')->select('id')
                    ->orderByRaw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?))
                  + sin(radians(?)) * sin(radians(lat))
                )) asc
            ", [$lat, $lng, $lat])
                    ->first();
                $govId = $row->id ?? null;
            }
        }

        Log::info('ADM received', [
            'adm1' => $adm1,
            'adm2' => $adm2,
            'norm_gov' => $govNameNorm,
            'norm_state' => $stateNameNorm,
        ]);
        // 2) طابق الولاية (states) بالإنجليزي ثم العربي — مع ربطها بالمحافظة لو معروف govId
        if ($stateNameNorm) {
            $stateQuery = DB::table('states')->select('id');
            if ($govId && Schema::hasColumn('states', 'governorate_id')) {
                $stateQuery->where('governorate_id', $govId);
            }
            $stateId = $stateQuery->where(function ($q) use ($stateNameNorm) {
                $q->whereRaw('LOWER(en_name) = ?', [$stateNameNorm])
                    ->orWhereRaw('LOWER(ar_name) = ?', [$stateNameNorm])
                    ->orWhere('en_name', 'like', "%{$stateNameNorm}%")
                    ->orWhere('ar_name', 'like', "%{$stateNameNorm}%");
            })->value('id');

            // Fallback مكاني لو الاسم فشل
            if (!$stateId && $lat && $lng) {
                $q = DB::table('states')->select('id')
                    ->orderByRaw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?))
                  + sin(radians(?)) * sin(radians(lat))
                )) asc
            ", [$lat, $lng, $lat]);
                if ($govId && Schema::hasColumn('states', 'governorate_id')) {
                    $q->where('governorate_id', $govId);
                }
                $stateId = optional($q->first())->id;
            }
        }
        // dd($stateId, $govId);

        return [
            'streetAddress' => $formattedAddress,
            'latitude' => $lat,
            'longitude' => $lng,
            'state_id' => $stateId,
            'governorate_id' => $govId,
        ];
    }

    /**
     * Create address update request for a shipment
     *
     * @param Shipment $shipment
     * @param string $newAddressInput Text address or Google Maps link
     * @param string $reason Reason for address update
     * @param string $source Source of the request (e.g., 'driver_app', 'driver_app_wrong_city')
     * @param string|null $proof Path to proof image/document
     * @return array{status: string, revision_id: int}
     * @throws Exception
     */
    public function createAddressUpdateRequest(
        Shipment $shipment,
        string $newAddressInput,
        string $reason = 'Driver requested address change via app',
        string $source = 'driver_app',
        ?string $proof = null
    ): array {
        if (!$shipment->consignee) {
            throw new Exception('No consignee associated with this shipment');
        }
        $consignee = $shipment->consignee;

        // Check for pending address update
        $hasPending = \App\Models\ShipmentAddressRevision::where('shipment_id', $shipment->id)
            ->where('approved', false)
            ->where('rejected', false)
            ->exists();

        if ($hasPending) {
            throw new Exception('There is already a pending address update request for this shipment.');
        }

        // Get current address
        $currentAddress = $shipment->deliveryAddress;
        if (!$currentAddress) {
            $currentAddress = \App\Models\Address::where('consignee_id', $consignee->id)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();
        }
        if (!$currentAddress) {
            $currentAddress = \App\Models\Address::create([
                'consignee_id' => $consignee->id,
                'country_id' => $consignee->country_id,
                'governorate_id' => $consignee->governorate_id,
                'state_id' => $consignee->state_id,
                'place_id' => $consignee->place_id,
                'city_id' => $consignee->city_id,
                'zipcode' => $consignee->zipcode,
                'streetAddress' => $consignee->streetAddress,
                'longitude' => $consignee->longitude,
                'latitude' => $consignee->latitude,
                'location_url' => $consignee->location,
                'approved' => true,
                'is_active' => true,
            ]);
        }

        // Parse the new address
        $parsed = $this->parseInputAddress($newAddressInput);

        // Create new address
        Log::info('AddressService - Creating new Address record', [
            'source' => $source,
            'consignee_id' => $consignee->id,
            'streetAddress' => $parsed['streetAddress'] ?? $currentAddress->streetAddress ?? $consignee->streetAddress,
            'latitude' => $parsed['latitude'] ?? null,
            'longitude' => $parsed['longitude'] ?? null,
        ]);

        $newAddress = \App\Models\Address::create([
            'consignee_id' => $consignee->id,
            'country_id' => $parsed['country_id'] ?? $currentAddress->country_id ?? $consignee->country_id,
            'governorate_id' => $parsed['governorate_id'] ?? $currentAddress->governorate_id ?? $consignee->governorate_id,
            'state_id' => $parsed['state_id'] ?? $currentAddress->state_id ?? $consignee->state_id,
            'place_id' => $parsed['place_id'] ?? $currentAddress->place_id ?? $consignee->place_id,
            'city_id' => $parsed['city_id'] ?? $currentAddress->city_id ?? $consignee->city_id,
            'zipcode' => $parsed['zipcode'] ?? $currentAddress->zipcode ?? $consignee->zipcode,
            'streetAddress' => $parsed['streetAddress'] ?? $currentAddress->streetAddress ?? $consignee->streetAddress,
            'longitude' => $parsed['longitude'] ?? $currentAddress->longitude ?? $consignee->longitude,
            'latitude' => $parsed['latitude'] ?? $currentAddress->latitude ?? $consignee->latitude,
            'location_url' => $parsed['location'] ?? $currentAddress->location_url ?? $consignee->location,
            'approved' => false,
            'is_active' => false,
        ]);

        Log::info('AddressService - New Address record created', [
            'source' => $source,
            'address_id' => $newAddress->id,
            'consignee_id' => $newAddress->consignee_id,
            'streetAddress' => $newAddress->streetAddress,
        ]);

        // Create revision record
        $revision = \App\Models\ShipmentAddressRevision::create([
            'shipment_id' => $shipment->id,
            'old_address_id' => $currentAddress->id,
            'new_address_id' => $newAddress->id,
            'changed_by' => auth()->id(),
            'reason' => $reason,
            'proof' => $proof,
            'approved' => false,
            'rejected' => false,
        ]);

        // Prepare address payloads for history
        $oldAddrPayload = [
            'street' => $currentAddress->streetAddress,
            'latitude' => $currentAddress->latitude,
            'longitude' => $currentAddress->longitude,
            'country_id' => $currentAddress->country_id,
            'state_id' => $currentAddress->state_id,
            'governorate_id' => $currentAddress->governorate_id,
            'place_id' => $currentAddress->place_id,
            'city_id' => $currentAddress->city_id,
            'location' => $currentAddress->location_url,
        ];
        $newAddrPayload = [
            'street' => $newAddress->streetAddress,
            'latitude' => $newAddress->latitude,
            'longitude' => $newAddress->longitude,
            'country_id' => $newAddress->country_id,
            'state_id' => $newAddress->state_id,
            'governorate_id' => $newAddress->governorate_id,
            'place_id' => $newAddress->place_id,
            'city_id' => $newAddress->city_id,
            'location' => $newAddress->location_url,
        ];

        // Determine description based on source
        $description = $source === 'driver_app_wrong_city'
            ? 'Driver reported WRONG_CITY and provided correct address - pending admin approval'
            : 'Driver requested address update - pending admin approval';

        $actionName = $source === 'driver_app_wrong_city'
            ? 'Address Update Request (Driver - WRONG_CITY)'
            : 'Address Update Request (Driver)';

        // Create history record for address update
        DB::table('shipment_histories')->insert([
            'trackNode' => null,
            'operatorInfo' => 'Driver',
            'operationHub' => null,
            'operationHubType' => 'driver',
            'originActionName' => $actionName,
            'name' => 'ADDRESS_UPDATE',
            'description' => $description,
            'type' => 'ADDRESS_UPDATE',
            'fromPkgId' => null,
            'time' => now(),
            'operatorId' => auth()->id(),
            'shipment_id' => $shipment->id,
            'proof' => null,
            'data' => json_encode([
                'old_address' => $oldAddrPayload,
                'new_address' => $newAddrPayload,
                'status' => 'pending_approval',
                'source' => $source,
                'revision_id' => $revision->id,
            ]),
            'c_show' => 1,
            'updated_at' => now(),
            'created_at' => now()
        ]);

        // Notify supervisors across assigned workspaces
        $this->notifySupervisorsAboutAddressUpdate($shipment, $revision, $oldAddrPayload, $newAddrPayload, $source);

        return [
            'status' => 'pending_approval',
            'revision_id' => $revision->id,
            'new_address_id' => $newAddress->id,
            'old_address_id' => $currentAddress->id,
        ];
    }

    /**
     * Notify supervisors about address update request
     *
     * @param Shipment $shipment
     * @param \App\Models\ShipmentAddressRevision $revision
     * @param array $oldAddrPayload
     * @param array $newAddrPayload
     * @param string $source
     * @return void
     */
    protected function notifySupervisorsAboutAddressUpdate(
        Shipment $shipment,
        $revision,
        array $oldAddrPayload,
        array $newAddrPayload,
        string $source = 'driver_app'
    ): void {
        $workspaceId = null;
        $workspaceType = null;

        // Try to get workspace from merchant
        if ($shipment->merchant_id) {
            $merchant = \App\Models\User::find($shipment->merchant_id);
            Log::info('Address Update - Merchant Info', [
                'merchant_id' => $shipment->merchant_id,
                'merchant_found' => $merchant ? true : false,
                'merchant_owner_id' => $merchant->owner_id ?? null,
                'merchant_owner_type' => $merchant->owner_type ?? null,
            ]);

            if ($merchant && $merchant->owner_id && $merchant->owner_type) {
                $workspaceId = $merchant->owner_id;
                $workspaceType = $merchant->owner_type;
            }
        }

        // Fallback to shipment's workspace if merchant workspace not available
        if (!$workspaceId && $shipment->owner_id && $shipment->owner_type) {
            $workspaceId = $shipment->owner_id;
            $workspaceType = $shipment->owner_type;
            Log::info('Address Update - Using Shipment Workspace', [
                'shipment_owner_id' => $shipment->owner_id,
                'shipment_owner_type' => $shipment->owner_type,
            ]);
        }

        Log::info('Address Update - Final Workspace', [
            'workspace_id' => $workspaceId,
            'workspace_type' => $workspaceType,
            'shipment_id' => $shipment->id,
            'tracking_no' => $shipment->tracking_no,
        ]);

        // Send notification to supervisors if workspace is determined
        if ($workspaceId && $workspaceType) {
            $driver = auth()->user();
            $driverName = $driver->name ?? 'Driver';

            // Get facility name and type for badge
            $facilityName = null;
            $facilityType = null;
                $facilityModel = $workspaceType::find($workspaceId);
                if ($facilityModel) {
                    $facilityName = $facilityModel->name;
                    $facilityType = class_basename($workspaceType); // Hub, Station, or Branch
            }

            $title = $source === 'driver_app_wrong_city'
                ? '📍 طلب تحديث عنوان - مدينة خاطئة'
                : '📍 طلب تحديث عنوان جديد';

            $body = $source === 'driver_app_wrong_city'
                ? "أبلغ السائق {$driverName} عن مدينة خاطئة للشحنة رقم {$shipment->tracking_no} وقدم العنوان الصحيح. يرجى مراجعة الطلب والموافقة عليه."
                : "طلب السائق {$driverName} تحديث عنوان للشحنة رقم {$shipment->tracking_no}. يرجى مراجعة الطلب والموافقة عليه.";

            $notificationData = [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'revision_id' => $revision->id,
                'driver_id' => auth()->id(),
                'driver_name' => $driverName,
                'old_address' => $oldAddrPayload,
                'new_address' => $newAddrPayload,
                'status' => 'pending_approval',
                'facility_name' => $facilityName,
                'facility_type' => $facilityType,
                'facility_id' => $workspaceId,
            ];

            if ($source === 'driver_app_wrong_city') {
                $notificationData['exception_type'] = 'WRONG_CITY';
            }

            $notificationCount = notify_workspace_users(
                $workspaceId,
                $workspaceType,
                ['Address Updates access'],
                $title,
                $body,
                $notificationData,
                'address_update_request',
                true // Send push notification
            );

            Log::info('Address Update - Notifications Sent', [
                'notification_count' => $notificationCount,
                'source' => $source,
            ]);
        } else {
            Log::warning('Address Update - No Workspace Found', [
                'shipment_id' => $shipment->id,
                'merchant_id' => $shipment->merchant_id,
                'shipment_owner_id' => $shipment->owner_id,
                'shipment_owner_type' => $shipment->owner_type,
            ]);
        }
    }
}
