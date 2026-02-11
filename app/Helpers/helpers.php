<?php

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Hub;
use App\Models\Shipment;
use App\Models\Branch;
use App\Models\MerchantWaybill;
use App\Models\DriverWaybill;
use App\Models\LoginHistory;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Station;
use App\Models\State;
use App\Models\ShipmentHistory;
use App\Models\Scopes\ShipmentScope;
use App\Models\Shipper;
use App\Models\ShipperCommission;
use App\Models\DriverBonusesTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Illuminate\Support\Str;
use App\Models\Notification as AppNotification;
use Illuminate\Support\Facades\DB;
use App\Services\Geocoding\AiGeocodingService;
use App\Enums\ShipmentStatusEnum;
use App\Services\DriverService;
use App\Services\MerchantTransactionService;
use App\Services\WarehouseNetBalanceService;
use App\Services\WhatsAppService;

function helper()
{
    return "I am helper";
}

function sendResponse($message, $data, $success = true, $errors = [], $status = 200, $extras = [])
{
    return response()->json(
        [
            'message' => $message,
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
        ],
        $status,
        $extras
    );
}



function localDayToUtcRange(string $localYmd, string $tz): array
{
    // Create the date directly in the local timezone (not parse in UTC then convert)
    $local = Carbon::createFromFormat('Y-m-d', $localYmd, $tz)->startOfDay();
    $startUtc = $local->copy()->utc();
    $endUtc = $local->copy()->endOfDay()->utc();
    return [$startUtc, $endUtc];
}

/**
 * Get country codes that use leading zeros in local phone number format
 * This list includes countries where mobile/landline numbers typically start with 0 locally
 */
function getCountriesWithLeadingZero()
{
    return [
        '+20',   // Egypt
        '+27',   // South Africa
        '+30',   // Greece
        '+31',   // Netherlands
        '+32',   // Belgium
        '+33',   // France
        '+34',   // Spain
        '+36',   // Hungary
        '+39',   // Italy
        '+40',   // Romania
        '+41',   // Switzerland
        '+43',   // Austria
        '+44',   // UK
        '+45',   // Denmark
        '+46',   // Sweden
        '+47',   // Norway
        '+48',   // Poland
        '+49',   // Germany
        '+51',   // Peru
        '+52',   // Mexico
        '+54',   // Argentina
        '+55',   // Brazil
        '+60',   // Malaysia
        '+61',   // Australia
        '+62',   // Indonesia
        '+63',   // Philippines
        '+64',   // New Zealand
        '+65',   // Singapore
        '+66',   // Thailand
        '+81',   // Japan
        '+82',   // South Korea
        '+84',   // Vietnam
        '+86',   // China
        '+90',   // Turkey
        '+91',   // India
        '+92',   // Pakistan
        '+93',   // Afghanistan
        '+94',   // Sri Lanka
        '+95',   // Myanmar
        '+212',  // Morocco
        '+213',  // Algeria
        '+216',  // Tunisia
        '+218',  // Libya
        '+220',  // Gambia
        '+221',  // Senegal
        '+222',  // Mauritania
        '+223',  // Mali
        '+224',  // Guinea
        '+225',  // Ivory Coast
        '+226',  // Burkina Faso
        '+227',  // Niger
        '+228',  // Togo
        '+229',  // Benin
        '+230',  // Mauritius
        '+231',  // Liberia
        '+232',  // Sierra Leone
        '+233',  // Ghana
        '+234',  // Nigeria
        '+235',  // Chad
        '+236',  // Central African Republic
        '+237',  // Cameroon
        '+238',  // Cape Verde
        '+239',  // São Tomé and Príncipe
        '+240',  // Equatorial Guinea
        '+241',  // Gabon
        '+242',  // Republic of the Congo
        '+243',  // Democratic Republic of the Congo
        '+244',  // Angola
        '+245',  // Guinea-Bissau
        '+246',  // British Indian Ocean Territory
        '+247',  // Ascension Island
        '+248',  // Seychelles
        '+249',  // Sudan
        '+250',  // Rwanda
        '+251',  // Ethiopia
        '+252',  // Somalia
        '+253',  // Djibouti
        '+254',  // Kenya
        '+255',  // Tanzania
        '+256',  // Uganda
        '+257',  // Burundi
        '+258',  // Mozambique
        '+260',  // Zambia
        '+261',  // Madagascar
        '+262',  // Réunion
        '+263',  // Zimbabwe
        '+264',  // Namibia
        '+265',  // Malawi
        '+266',  // Lesotho
        '+267',  // Botswana
        '+268',  // Eswatini
        '+269',  // Comoros
        '+290',  // Saint Helena
        '+291',  // Eritrea
        '+297',  // Aruba
        '+298',  // Faroe Islands
        '+299',  // Greenland
        '+350',  // Gibraltar
        '+351',  // Portugal
        '+352',  // Luxembourg
        '+353',  // Ireland
        '+354',  // Iceland
        '+355',  // Albania
        '+356',  // Malta
        '+357',  // Cyprus
        '+358',  // Finland
        '+359',  // Bulgaria
        '+370',  // Lithuania
        '+371',  // Latvia
        '+372',  // Estonia
        '+373',  // Moldova
        '+374',  // Armenia
        '+375',  // Belarus
        '+376',  // Andorra
        '+377',  // Monaco
        '+378',  // San Marino
        '+380',  // Ukraine
        '+381',  // Serbia
        '+382',  // Montenegro
        '+383',  // Kosovo
        '+385',  // Croatia
        '+386',  // Slovenia
        '+387',  // Bosnia and Herzegovina
        '+389',  // North Macedonia
        '+420',  // Czech Republic
        '+421',  // Slovakia
        '+423',  // Liechtenstein
        '+500',  // Falkland Islands
        '+501',  // Belize
        '+502',  // Guatemala
        '+503',  // El Salvador
        '+504',  // Honduras
        '+505',  // Nicaragua
        '+506',  // Costa Rica
        '+507',  // Panama
        '+508',  // Saint Pierre and Miquelon
        '+509',  // Haiti
        '+590',  // Guadeloupe
        '+591',  // Bolivia
        '+592',  // Guyana
        '+593',  // Ecuador
        '+594',  // French Guiana
        '+595',  // Paraguay
        '+596',  // Martinique
        '+597',  // Suriname
        '+598',  // Uruguay
        '+599',  // Netherlands Antilles
        '+670',  // East Timor
        '+672',  // Australian External Territories
        '+673',  // Brunei
        '+674',  // Nauru
        '+675',  // Papua New Guinea
        '+676',  // Tonga
        '+677',  // Solomon Islands
        '+678',  // Vanuatu
        '+679',  // Fiji
        '+680',  // Palau
        '+681',  // Wallis and Futuna
        '+682',  // Cook Islands
        '+683',  // Niue
        '+685',  // Samoa
        '+686',  // Kiribati
        '+687',  // New Caledonia
        '+688',  // Tuvalu
        '+689',  // French Polynesia
        '+690',  // Tokelau
        '+691',  // Micronesia
        '+692',  // Marshall Islands
        '+850',  // North Korea
        '+852',  // Hong Kong
        '+853',  // Macau
        '+855',  // Cambodia
        '+856',  // Laos
        '+880',  // Bangladesh
        '+886',  // Taiwan
        '+960',  // Maldives
        '+961',  // Lebanon
        '+962',  // Jordan
        '+963',  // Syria
        '+964',  // Iraq
        '+965',  // Kuwait
        '+966',  // Saudi Arabia
        '+967',  // Yemen
        // '+968',  // Oman
        '+970',  // Palestine
        '+971',  // UAE
        '+972',  // Israel
        '+973',  // Bahrain
        '+974',  // Qatar
        '+975',  // Bhutan
        '+976',  // Mongolia
        '+977',  // Nepal
        '+992',  // Tajikistan
        '+993',  // Turkmenistan
        '+994',  // Azerbaijan
        '+995',  // Georgia
        '+996',  // Kyrgyzstan
        '+998',  // Uzbekistan
    ];
}

function splitPhoneNumber($phoneNumber)
{
    if (!$phoneNumber) {
        return [
            'country_code' => null,
            'national_number' => null,
            'full_number' => null
        ];
    }

    // Remove all non-numeric characters except for the leading '+'
    $cleanedNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

    // If the number doesn't start with '+', assume a default country code, e.g., for Oman (+968)
    if (strpos($cleanedNumber, '+') !== 0) {
        return [
            'country_code' => '+968',
            'national_number' => $cleanedNumber,
            'full_number' => '+968' . $cleanedNumber
        ];
    }

    // Get list of countries with leading zeros
    $countriesWithLeadingZero = getCountriesWithLeadingZero();

    // Build comprehensive list of country codes for matching (includes all known codes)
    // Start with countries that use leading zeros, then add other common codes
    $allCountryCodes = array_merge($countriesWithLeadingZero, [
        '+1',
        '+7',
        '+86',
        '+351',
        '+352',
        '+353',
        '+354',
        '+355',
        '+356',
        '+357',
        '+358',
        '+359',
        '+370',
        '+371',
        '+372',
        '+373',
        '+374',
        '+375',
        '+376',
        '+377',
        '+378',
        '+380',
        '+381',
        '+382',
        '+383',
        '+385',
        '+386',
        '+387',
        '+389',
        '+420',
        '+421',
        '+423',
        '+500',
        '+501',
        '+502',
        '+503',
        '+504',
        '+505',
        '+506',
        '+507',
        '+508',
        '+509',
        '+590',
        '+591',
        '+592',
        '+593',
        '+594',
        '+595',
        '+596',
        '+597',
        '+598',
        '+599',
        '+670',
        '+672',
        '+673',
        '+674',
        '+675',
        '+676',
        '+677',
        '+678',
        '+679',
        '+680',
        '+681',
        '+682',
        '+683',
        '+685',
        '+686',
        '+687',
        '+688',
        '+689',
        '+690',
        '+691',
        '+692',
        '+850',
        '+852',
        '+853',
        '+855',
        '+856',
        '+880',
        '+886',
        '+967',
        '+970'
    ]);

    // Remove duplicates and sort by length descending (longest first to avoid partial matches)
    $allCountryCodes = array_unique($allCountryCodes);
    usort($allCountryCodes, function ($a, $b) {
        return strlen($b) - strlen($a);
    });

    // Try to match against known country codes
    foreach ($allCountryCodes as $countryCode) {
        $codeWithoutPlus = ltrim($countryCode, '+');
        if (str_starts_with($cleanedNumber, $countryCode)) {
            $nationalNumber = substr($cleanedNumber, strlen($countryCode));

            // Preserve leading zero for countries that use it in local format
            if (in_array($countryCode, $countriesWithLeadingZero) && !str_starts_with($nationalNumber, '0')) {
                // Add leading zero if missing for countries that use it
                $nationalNumber = '0' . $nationalNumber;
            }

            return [
                'country_code' => $countryCode,
                'national_number' => $nationalNumber,
                'full_number' => $cleanedNumber
            ];
        }
    }

    // Fallback: Use regex for unknown country codes (1-3 digits)
    // The pattern looks for:
    // ^\+           - a plus sign at the start
    // (\d{1,3})     - followed by a group of 1 to 3 digits (country code)
    // (.+)          - followed by any remaining characters (national number)
    if (preg_match('/^\+(\d{1,3})(.+)$/', $cleanedNumber, $matches)) {
        $countryCode = '+' . $matches[1];
        $nationalNumber = $matches[2];

        // Preserve leading zero for countries that use it in local format
        if (in_array($countryCode, $countriesWithLeadingZero) && !str_starts_with($nationalNumber, '0')) {
            // Add leading zero if missing for countries that use it
            $nationalNumber = '0' . $nationalNumber;
        }

        return [
            'country_code' => $countryCode,
            'national_number' => $nationalNumber,
            'full_number' => $cleanedNumber
        ];
    }

    // If the regex doesn't match a standard international number format
    return [
        'country_code' => null,
        'national_number' => $cleanedNumber,
        'full_number' => $cleanedNumber
    ];
}

// function uploadFile(UploadedFile $file = null, $path = 'uploads')
// {
//     $fileName = null;
//     if ($file) {
//         $fileName = $path . '/' . time() . '_' . $file->getMerchantOriginalName();
//         $file->move(public_path($path), $fileName);
//     }
//     return $fileName;
// }




function uploadFile(?UploadedFile $file = null, string $path = 'uploads'): ?string
{
    if (!$file) {
        Log::warning("[uploadFileToS3] No file provided.");
        return null;
    }

    $disk = 's3'; // force S3
    $originalName = method_exists($file, 'getMerchantOriginalName')
        ? $file->getMerchantOriginalName()
        : $file->getClientOriginalName();

    $originalName = str_replace(' ', '_', $originalName); // optional cleanup
    $fileName = time() . '_' . $originalName; // unique filename

    try {
        // Save file to S3
        $pathInDisk = Storage::disk($disk)->putFileAs($path, $file, $fileName);

        // Return full URL
        $url = Storage::disk($disk)->url($pathInDisk);

        Log::info("[uploadFileToS3] Upload successful", [
            'path' => $path,
            'file_name' => $fileName,
            'path_in_disk' => $pathInDisk,
            'url' => $url,
            'file_size' => $file->getSize(),
        ]);

        // Return full URL for storage in database
        return $url;
    } catch (\Throwable $e) {
        Log::error("[uploadFileToS3] Upload failed", [
            'path' => $path,
            'file_name' => $fileName,
            'file_size' => $file->getSize(),
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);
        return null;
    }
}


function uploadFiles($files = null, string $path = 'uploads'): ?array
{
    if (!$files) {
        Log::warning('[uploadFilesToS3] No files provided.');
        return null;
    }

    // Normalize to array
    $files = is_array($files) ? $files : [$files];

    $disk = 's3';
    $uploadedUrls = [];

    foreach ($files as $file) {
        if (!$file instanceof UploadedFile) {
            continue;
        }

        $originalName = method_exists($file, 'getMerchantOriginalName')
            ? $file->getMerchantOriginalName()
            : $file->getClientOriginalName();

        $originalName = str_replace(' ', '_', $originalName);
        $fileName = time() . '_' . uniqid() . '_' . $originalName;

        try {
            $pathInDisk = Storage::disk($disk)->putFileAs($path, $file, $fileName);
            $url = Storage::disk($disk)->url($pathInDisk);

            $uploadedUrls[] = $url;

            Log::info('[uploadFilesToS3] Upload successful', [
                'file_name' => $fileName,
                'url' => $url,
                'file_size' => $file->getSize(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[uploadFilesToS3] Upload failed', [
                'file_name' => $fileName,
                'message' => $e->getMessage(),
            ]);
        }
    }

    return $uploadedUrls ?: null;
}




function downloadFromS3($fileUrl)
{
    try {
        Log::info("[downloadFromS3] Downloading file from S3", ["fileUrl" => $fileUrl]);
        $parsedUrl = parse_url($fileUrl);
        $filePath = ltrim($parsedUrl['path'], '/');
        $fileContent = Storage::disk('s3')->get($filePath);
        $tempPath = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tempPath, $fileContent);
        Log::info("[downloadFromS3] File downloaded successfully", ["tempPath" => $tempPath]);
        return $tempPath;
    } catch (\Exception $e) {
        Log::error('[downloadFromS3] Error downloading from S3: ' . $e->getMessage());
        throw $e;
    }
}
function generateCategoryfBarcode(): string
{
    return 'PE' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function generateShelfBarcode(): string
{
    return 'PE' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function updateShipmentStatus($shipmentId, $status)
{
    try {
        $shipment = Shipment::find($shipmentId);
        if ($shipment) {
            return $shipment->update(['status' => $status]);
        }
        return false;
    } catch (\Exception $e) {
        return false;
    }
}

function sorter_hub_timezone(?User $user = null): ?string
{
    $user ??= Auth::user();

    if (!$user) {
        return null;
    }

    $resolvedHub = null;
    $owner = $user->relationLoaded('owner') ? $user->getRelation('owner') : null;
    $ownerType = $user->owner_type;

    if ($ownerType === Hub::class) {
        $resolvedHub = $owner instanceof Hub ? $owner : Hub::find($user->owner_id);
    } elseif ($ownerType === Station::class) {
        $station = $owner instanceof Station ? $owner : Station::find($user->owner_id);
        $resolvedHub = $station?->hub;
    } elseif ($ownerType === Branch::class) {
        $branch = $owner instanceof Branch ? $owner : Branch::find($user->owner_id);
        $resolvedHub = $branch?->hub ?: $branch?->station?->hub;
    }

    if (!$resolvedHub) {
        $hubUserRelation = $user->relationLoaded('hub_user') ? $user->getRelation('hub_user') : $user->hub_user;
        if ($hubUserRelation) {
            $resolvedHub = $hubUserRelation->relationLoaded('hub')
                ? $hubUserRelation->getRelation('hub')
                : $hubUserRelation->hub;
        }
    }

    if (!$resolvedHub && method_exists($user, 'hubs')) {
        $resolvedHub = $user->hubs()->first();
    }

    return $resolvedHub?->timezone;
}

function operation_now(?User $user = null): Carbon
{
    $service = app(\App\Services\TimezoneService::class);
    return $service->now($user);
}

/**
 * Get current operation time with timezone metadata
 *
 * @param User|null $user
 * @return array ['timestamp' => Carbon, 'timezone' => string, 'utc_timestamp' => Carbon]
 */
if (!function_exists('operation_now_with_tz')) {
    function operation_now_with_tz(?User $user = null): array
    {
        $service = app(\App\Services\TimezoneService::class);
        return $service->createTimestamp($user);
    }
}


function shipmentHistory(array $data)
{
    try {
        $scope = getUserIdByScope();

        // Ensure operationHub and operationHubType are scalar values, not arrays
        $operationHub = is_array($scope['owner_id']) ? ($scope['owner_id'][0] ?? $scope['owner_id']['id'] ?? null) : $scope['owner_id'];
        $operationHubType = is_array($scope['owner_type']) ? ($scope['owner_type'][0] ?? $scope['owner_type']['type'] ?? null) : $scope['owner_type'];

        // Ensure status is a string, not an array
        $statusValue = $data['status'] ?? null;
        if (is_array($statusValue)) {
            $statusValue = $statusValue['label'] ?? $statusValue['name'] ?? null;
        }

        // Get timezone-aware timestamp if not provided
        $tzData = isset($data['time']) ? null : operation_now_with_tz();
        $time = $data['time'] ?? $tzData['timestamp'];
        $timezone = $data['timezone'] ?? ($tzData['timezone'] ?? config('app.timezone'));

        return ShipmentHistory::create([
            'trackNode' => null,
            'operatorInfo' => $data['operatorInfo'] ?? history_name() ?? 'System', // Use provided operatorInfo or default to history_name()
            'operationHub' => $operationHub,
            'operationHubType' => $operationHubType,
            'originActionName' => isset($statusValue) ? str_replace("_", " ", $statusValue) : null,
            'name' => $statusValue,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? null,
            'timezone' => $timezone,  // NEW: Store timezone
            'fromPkgId' => $data['fromPkgId'] ?? null,
            'time' => $time,
            'operatorId' => $data['operatorId'] ?? Auth::id(), // Use provided operatorId or default to Auth::id()
            'shipment_id' => isset($data['shipment_id']) ? $data['shipment_id'] : null,
            'proof' => !empty($data['proof'])
                ? $data['proof']
                : (!empty($data['pickup_proof']) ? $data['pickup_proof'] : null),
            'data' => $data['data'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
            'created_at' => $data['created_at'] ?? null
        ]);
    } catch (Exception $e) {
        Log::info("Exception in shipmentHistory: " . $e->getMessage());
        throw $e;
    }
}


function system_delivery_exceptions(): array
{
    return [
        'NO_ANSWER' => [
            'name' => 'NO_ANSWER',
            'label' => 'No Answer',
            'description' => "When the driver don't repond to the calls of the driver. then the parcel is taken back to the warehouse.",
            "proof_required" => false,
            "move_to_crm" => true,
        ],
        'FUTURE_DELIVERY' => [
            'name' => 'FUTURE_DELIVERY',
            'label' => 'Future Delivery',
            'description' => 'The customer has told that he will receive the parcel in a future date.',
            "proof_required" => true,
            "move_to_crm" => false,
        ],
        'WRONG_CITY' => [
            'name' => 'WRONG_CITY',
            'label' => 'Wrong City',
            'description' => 'The city in the address of shipment was wrong.',
            "proof_required" => true,
            "move_to_crm" => true,
        ],
        'WRONG_NUMBER' => [
            'name' => 'WRONG_NUMBER',
            'label' => 'Wrong Number',
            'description' => 'The city in the address of shipment was wrong.',
            "proof_required" => false,
            "move_to_crm" => true,
        ],
        'CANCELLED' => [
            'name' => 'CANCELLED',
            'label' => 'CANCELLED',
            'description' => 'The parcel has been CANCELLED.',
            "proof_required" => true,
            "move_to_crm" => true,
        ],
        'DELIVER_LATER_TODAY' => [
            'name' => 'DELIVER_LATER_TODAY',
            'label' => 'DELIVER LATER TODAY',
            'description' => 'The customer has told that he will receive the parcel in a DELIVER LATER TODAY.',
            "proof_required" => true,
            "move_to_crm" => false,
        ],
        'TOMORROW' => [
            'name' => 'TOMORROW',
            'label' => 'TOMORROW',
            'description' => 'The customer has told that he will receive the parcel in a tomorrow.',
            "proof_required" => true,
            "move_to_crm" => false,
        ],
    ];
}

function exception_status(string $key): ?array
{
    $statuses = system_delivery_exceptions();
    return $statuses[$key] ?? null;
}

if (!function_exists('statuses')) {
    /**
     * Get the list of statuses with descriptions.
     *
     * @return array
     */
    function statuses(): array
    {
        return [
            'ORDER_COLLECTED' => [
                'label' => 'COLLECTED',
                'description' => 'The shipment has been successfully collected.'
            ],
            'ORDER_CREATED' => [
                'label' => 'CREATED',
                'description' => 'The shipment has been created.'
            ],
            'ORDER_SORTED' => [
                'label' => 'SORT',
                'description' => 'The shipment has been sorted'
            ],
            'ORDER_RECEIVED' => [
                'name' => 'ORDER_RECEIVED',
                'label' => 'Shipment Received',
                'description' => 'The shipment has been successfully received from the e-commerce platform.'
            ],

            'ORDER_ASSIGNED' => [
                'label' => 'ASSIGNED',
                'description' => 'The shipment has been ASSIGNED'
            ],
            'LOCAL_ORDER_ASSIGNED' => [
                'label' => 'LOCAL_ORDER_ASSIGNED',
                'description' => 'The shipment has been ASSIGNED'
            ],
            'SIGNED' => [
                'label' => 'SIGNED',
                'description' => 'The shipment has been SIGNED'
            ],
            'CONFIRMED' => [
                'label' => 'Confirmed',
                'description' => 'The shipment has confirmed.'
            ],
            'PAYMENT_VERIFIED' => [
                'label' => 'Payment Verified',
                'description' => 'The payment for the shipment has been verified.'
            ],
            'ORDER_CONFIRMED' => [
                'label' => 'Shipment Confirmed',
                'description' => 'The seller has confirmed the shipment for processing.'
            ],
            'READY_FOR_DISPATCH' => [
                'label' => 'Ready for Dispatch',
                'description' => 'The parcel is packed and ready for pickup by the courier.'
            ],
            'MOVE_TO_DISPATCH' => [
                'label' => 'MOVE_TO_DISPATCH',
                'description' => 'The parcel has returned.'
            ],
            'FUTURE_DELIVERY' => [
                'label' => 'FUTURE_DELIVERY',
                'description' => 'The customer has told that he will receive the parcel in a future date.'
            ],
            'NO_ANSWER' => [
                'label' => 'NO_ANSWER',
                'description' => "No answer from the recipient"
            ],
            'WRONG_CITY' => [
                'label' => 'WRONG_CITY',
                'description' => 'The city in the address of shipment was wrong.'
            ],
            'MOVE_TO_SUPERVISOR' => [
                'label' => 'MOVE_TO_SUPERVISOR',
                'description' => 'The parce has been moved to the supervisor.'
            ],

            'PICKUP_SCHEDULED' => [
                'label' => 'Pickup Scheduled',
                'description' => 'The courier has been scheduled to pick up the parcel.'
            ],
            'COURIER_ASSIGNED' => [
                'label' => 'Courier Assigned',
                'description' => 'A delivery agent has been assigned to pick up the parcel.'
            ],
            'PARCEL_PICKED_UP' => [
                'label' => 'Parcel Picked Up',
                'description' => 'The courier has successfully picked up the parcel.'
            ],
            'IN_TRANSIT_TO_SORTING_HUB' => [
                'label' => 'In Transit to Sorting Hub',
                'description' => 'The parcel is on its way to the first sorting facility.'
            ],
            'SORTING_COMPLETED' => [
                'label' => 'Sorting Completed',
                'description' => 'The parcel has been sorted for its destination.'
            ],
            'IN_TRANSIT_TO_DESTINATION_HUB' => [
                'label' => 'In Transit to Destination Hub',
                'description' => 'The parcel is en route to the destination hub.'
            ],
            'ARRIVED_AT_DESTINATION_HUB' => [
                'label' => 'Arrived at Destination Hub',
                'description' => 'The parcel has reached the hub nearest to the recipient.'
            ],
            'CUSTOMS_CLEARANCE_IN_PROGRESS' => [
                'label' => 'Customs Clearance in Progress',
                'description' => 'The parcel is undergoing customs clearance for international delivery.'
            ],
            'CUSTOMS_CLEARED' => [
                'label' => 'Customs Cleared',
                'description' => 'The parcel has been cleared by customs.'
            ],
            'OUT_FOR_DELIVERY' => [
                'label' => 'OFD',
                'description' => 'The parcel is with a delivery agent and on its way to the recipient.'
            ],
            'DELIVERY_IN_PROGRESS' => [
                'label' => 'Delivery in Progress',
                'description' => 'The delivery agent is actively en route to the recipient.'
            ],
            'DELIVERY_ATTEMPTED' => [
                'label' => 'Delivery Attempted',
                'description' => 'A delivery attempt was made, but it was unsuccessful.'
            ],
            'DELIVERED' => [
                'label' => 'DELIVERED',
                'description' => 'The parcel has been successfully delivered to the recipient.'
            ],
            'RETURNED' => [
                'label' => 'RETURNED',
                'description' => 'The parcel has been returned by the driver to the warehouse due to an issue.'
            ],
            'CANCELLED' => [
                'label' => 'CANCELLED',
                'description' => 'The parcel has been CANCELLED.'
            ],
            'RTC' => [
                'label' => 'RTC',
                'description' => 'The parcel needs to be return to merchant.'
            ],
            'RESCHEDULE' => [
                'label' => 'RESCHEDULE',
                'description' => 'The parcel needs to be RESCHEDULE.'
            ],


            'DELIVERED_TO_ALTERNATE_LOCATION' => [
                'label' => 'Delivered to Alternate Location',
                'description' => 'Delivered to a different location as per recipient request.'
            ],
            'RETURN_INITIATED' => [
                'label' => 'Return Initiated',
                'description' => 'The recipient has requested a return of the parcel.'
            ],
            'RETURN_PICKUP_SCHEDULED' => [
                'label' => 'Return Pickup Scheduled',
                'description' => 'A pickup for the return parcel has been scheduled.'
            ],
            'RETURN_PICKED_UP' => [
                'label' => 'Return Picked Up',
                'description' => 'The parcel for return has been picked up from the recipient.'
            ],
            'RETURN_IN_TRANSIT' => [
                'label' => 'Return in Transit',
                'description' => 'The returned parcel is on its way back to the seller.'
            ],
            'RETURN_DELIVERED_TO_SENDER' => [
                'label' => 'Return Delivered to Sender',
                'description' => 'The returned parcel has been delivered back to the seller.'
            ],
            'FAILED_DELIVERY' => [
                'label' => 'Failed Delivery',
                'description' => 'The delivery could not be completed after multiple attempts.'
            ],
            'LOST' => [
                'label' => 'LOST ',
                'description' => 'The parcel has been reported as lost.'
            ],
            'DAMAGED_IN_TRANSIT' => [
                'label' => 'Damaged in Transit',
                'description' => 'The parcel was damaged during the delivery process.'
            ],
            'HELD_FOR_FURTHER_INSTRUCTIONS' => [
                'label' => 'Held for Further Instructions',
                'description' => 'The parcel is on hold due to missing or unclear recipient details.'
            ],
            'REDIRECTED_TO_NEW_ADDRESS' => [
                'label' => 'Redirected to New Address',
                'description' => 'The parcel is being redirected to a new delivery address as per recipient request.'
            ],

            // CUSTOM
            'MOVE_TO_SHELF' => [
                'label' => 'MOVE_TO_SHELF',
                'description' => 'Move this shipment to Shelf.'
            ],
            'ASSIGNED_TO_SHELF' => [
                'label' => 'ASSIGNED_TO_SHELF',
                'description' => 'Assigned to Shelf.'
            ],
            'UPDATED' => [
                'label' => 'Updated',
                'description' => 'Some Data of the Shipment has been changed.'
            ],
            'TEST' => [
                'label' => 'test',
                'description' => 'test 123'
            ],
            'ORDER_INBOUNDED' => [
                'label' => 'ORDER_INBOUNDED',
                'description' => 'Shipment Inbound to warehouse.'
            ],
            'ORDER_LOADED' => [
                'label' => 'LOADED',
                'description' => 'The shipment has been Loaded'
            ],
            'ORDER_UNLOADED' => [
                'label' => 'UNLOADED',
                'description' => 'The shipment has been Unloaded'
            ],
            'ASSIGNED_FOR_PICKUP' => [
                'label' => 'ASSIGNED_FOR_PICKUP',
                'description' => "Shipment Assigned to Driver for Pickup"
            ],
            'DISPATCH' => [
                'label' => 'DISPATCH',
                'description' => "Shipment is dispatched"
            ],

            // PICKUP

            'PICKUP_COMPLETED' => [
                'name' => 'PICKUP_COMPLETED',
                'label' => 'Pickup Completed',
                'description' => "Shipment is arrived at facility."
            ],
            'PICKED' => [
                'name' => 'PICKED',
                'label' => 'Picked',
                'description' => "Shipment is picked from the merchant."
            ],

            //RTO
            'RTO`' => [
                'name' => 'RTO',
                'label' => 'RTO',
                'description' => "RTO Shipment."
            ],
            'ADDRESS_UPDATE_APPROVED`' => [
                'name' => 'ADDRESS_UPDATE_APPROVED',
                'label' => 'ADDRESS_UPDATE_APPROVED',
                'description' => "Address update approved has been approved by the admin."
            ],
        ];
    }
}

if (!function_exists('create_notification')) {
    function create_notification(
        $notifiable,
        string $title,
        string $content,
        array $data = [],
        ?string $type = null,
        bool $markAsRead = false
    ): ?Notification {
        if (!$notifiable) {
            $notifiable = Auth::user();
        }

        if (!$notifiable) {
            Log::warning('create_notification: No notifiable entity provided');
            return null;
        }
        $duplicateCheckKey = 'notification_duplicate_' . md5($title . $content . $notifiable->getKey() . $type);
        if (Cache::has($duplicateCheckKey)) {
            Log::warning('Duplicate notification prevented', [
                'title' => $title,
                'notifiable_id' => $notifiable->getKey()
            ]);
            return null;
        }
        Cache::put($duplicateCheckKey, true, 5);
        $payload = array_merge(['title' => $title, 'content' => $content], $data);
        try {
            $notification = Notification::create([
                'id' => (string) Str::uuid(),
                'notifiable_id' => $notifiable->getKey(),
                'notifiable_type' => get_class($notifiable),
                'type' => $type ?? 'general',
                'title' => $title,
                'content' => $content,
                'data' => $payload,
                'read_at' => $markAsRead ? now() : null,
            ]);

            Log::info('Notification created successfully', [
                'notification_id' => $notification->id,
                'title' => $title
            ]);

            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to create notification', [
                'error' => $e->getMessage(),
                'title' => $title
            ]);
            return null;
        }
    }
}

if (!function_exists('notify_workspace_users')) {
    /**
     * Send notification to users who share workspace with the source
     *
     * @param int $workspaceId The workspace ID (hub_id, station_id, or branch_id)
     * @param string $workspaceType The workspace type (App\Models\Hub, App\Models\Station, App\Models\Branch)
     * @param array $permissions Array of permission names to filter users (e.g., ['Pickup Task access', 'Address Updates access'])
     * @param string $title Notification title
     * @param string $content Notification content
     * @param array $data Additional notification data
     * @param string|null $type Notification type
     * @param bool $sendPush Whether to send push notification
     * @return int Number of notifications sent
     */
    function notify_workspace_users(
        int $workspaceId,
        string $workspaceType,
        array $permissions,
        string $title,
        string $content,
        array $data = [],
        ?string $type = null,
        bool $sendPush = false
    ): int {
        if (!$workspaceId || !$workspaceType) {
            Log::warning('notify_workspace_users: Invalid workspace parameters', [
                'workspace_id' => $workspaceId,
                'workspace_type' => $workspaceType
            ]);
            return 0;
        }

        // Build query to find users with specified permissions through their roles
        $usersQuery = User::query()
            ->whereHas('roles', function ($q) use ($workspaceId, $workspaceType, $permissions) {
                $q->where('roleable_id', $workspaceId)
                    ->where('roleable_type', $workspaceType)
                    ->whereHas('permissions', function ($permQuery) use ($permissions) {
                        $permQuery->whereIn('name', $permissions);
                    });
            });

        // Filter users who have this workspace assigned
        // Users can have multiple workspaces, so we check if they have at least one matching
        $usersQuery->where(function ($q) use ($workspaceId, $workspaceType) {
            switch ($workspaceType) {
                case 'App\\Models\\Hub':
                case Hub::class:
                    $q->whereHas('hub_users', function ($query) use ($workspaceId) {
                        $query->where('hub_id', $workspaceId);
                    });
                    break;

                case 'App\\Models\\Station':
                case Station::class:
                    $q->whereHas('station_users', function ($query) use ($workspaceId) {
                        $query->where('station_id', $workspaceId);
                    });
                    break;

                case 'App\\Models\\Branch':
                case Branch::class:
                    $q->whereHas('branch_users', function ($query) use ($workspaceId) {
                        $query->where('branch_id', $workspaceId);
                    });
                    break;

                default:
                    Log::warning('notify_workspace_users: Unknown workspace type', [
                        'workspace_type' => $workspaceType
                    ]);
                    // Return empty query
                    $q->whereRaw('1 = 0');
            }
        });

        Log::info('notify_workspace_users: Query parameters', [
            'workspace_id' => $workspaceId,
            'workspace_type' => $workspaceType,
            'permissions' => $permissions,
            'sql' => $usersQuery->toSql(),
            'bindings' => $usersQuery->getBindings()
        ]);

        $recipients = $usersQuery->get();

        Log::info('notify_workspace_users: Recipients found', [
            'count' => $recipients->count(),
            'users' => $recipients->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name')->toArray(),
                    'permissions' => $u->permissions->pluck('name')->toArray()
                ];
            })->toArray()
        ]);

        $count = 0;
        foreach ($recipients as $recipient) {
            $phoneNumber = $recipient->country_code . $recipient->phone;
            whatsappNotify($phoneNumber, $data, $content);
            $notification = create_notification(
                $recipient,
                $title,
                $content,
                $data,
                $type,
                false
            );

            if ($notification) {
                $count++;
            }

            // TODO: Implement push notification if $sendPush is true
            // if ($sendPush && $recipient->deviceTokens()->exists()) {
            //     send_push_notification($recipient, $title, $content);
            // }
        }

        Log::info('Workspace notifications sent', [
            'workspace_id' => $workspaceId,
            'workspace_type' => $workspaceType,
            'permissions' => $permissions,
            'recipients_count' => $count
        ]);

        return $count;
    }
}

function whatsappNotify($phoneNumber, $data, $content)
{
    $merchantName = $data['merchant_name'] ?? 'N/A';
    $facilityName = $data['facility_name'] ?? 'N/A';
    $shipmentsCount = $data['shipments_count'] ?? 0;

    $message = "🏠 *New Pickup Request Notification*

📦 {$content}
👤 Merchant: {$merchantName}
🏢 Facility: {$facilityName}
📊 Shipments Count: {$shipmentsCount}

Please open the app to view details";

    $whatsappService = new WhatsAppService();
    $whatsappService->sendMessage($phoneNumber, $message);
}


if (!function_exists('status')) {
    /**
     * Get a specific status by key.
     *
     * @param string $key
     * @return array|null
     */
    function status(string $key)
    {
        $statuses = statuses();

        // Check if the key exists
        if (isset($statuses[$key])) {
            return $statuses[$key];
        }

        // If not found, search by label
        foreach ($statuses as $statusKey => $statusData) {
            if (strtoupper($statusData['label']) === strtoupper($key)) {
                return $statusData;
            }
        }

        // Default response if status is not found
        return ["label" => $key, "description" => "Unknown status"];
    }
}

function getUserIdByScope()
{

    // $user = Auth::user();

    // if ($user) {
    //     if ($user->branch_user && $branch_id = $user->branch_user->branch_id) {
    //         return [
    //             'owner_id' => $branch_id,
    //             'owner_type' => Branch::class,
    //         ];
    //     }

    //     if ($user->station_user && $station_id = $user->station_user->station_id) {
    //         return [
    //             'owner_id' => $station_id,
    //             'owner_type' => Station::class,
    //         ];
    //     }

    //     if ($user->hub_user && $hub_id = $user->hub_user->hub_id) {
    //         return [
    //             'owner_id' => $hub_id,
    //             'owner_type' => Hub::class,
    //         ];
    //     }
    // }

    if (facility()) {
        return [
            'owner_id' => facility("id"),
            'owner_type' => facility("type"),
        ];
    } else {
        return [
            'owner_id' => null,
            'owner_type' => null,
        ];
    }
}

function setting($key)
{
    $setting = Setting::where("key", $key)->first();
    return $setting ? $setting->value : "";
}

function history_name()
{

    if ($user = Auth::user()) {
        if ($user->driver) {
            // return the number of the driver as well.
            return $user->id . " - " . $user->name . " - " . $user->roles[0]->name;
        } else if ($user->hasRole("Sorter")) {
            return $user->id . " - " . $user->name . " - " . $user->roles[0]->name;
        } else {
            return $user->name . " - " . $user->roles[0]->name;
        }
    } else {
        return "System";
    }
}

function generate_tracking_no()
{
    do {

        $datePrefix = date('dmy');
        $randomNumber = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $trackingNumber = "PE" . $datePrefix . $randomNumber;
    } while (Shipment::where('tracking_no', $trackingNumber)->exists());

    return $trackingNumber;
}
if (!function_exists('generate_pre_id')) {
    function generate_pre_id(): string
    {
        $n = (int) DB::table('shipments')->lockForUpdate()->max('id') + 1;
        return 'PRE-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}

function generate_merchant_tracking_no()
{
    do {
        $datePrefix = date('dmy');
        $randomNumber = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $trackingNumber = "ME" . $datePrefix . $randomNumber;
    } while (MerchantWaybill::where('tracking_no', $trackingNumber)->exists());

    return $trackingNumber;
}

function generate_driver_tracking_no()
{
    do {
        $datePrefix = date('dmy');
        $randomNumber = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $trackingNumber = "DR" . $datePrefix . $randomNumber;
    } while (DriverWaybill::where('tracking_no', $trackingNumber)->exists());

    return $trackingNumber;
}


// handle zone governorate and state

function getStateNamesFromPolygons($polygon)
{
    $apiKey = env("GOOGLE_MAPS_API_KEY");
    $states = [];
    $cacheDuration = 3600;

    $cacheKey = "polygon_states_" . md5($polygon);

    $cachedStates = Cache::get($cacheKey);
    if ($cachedStates) {
        return $cachedStates;
    }

    preg_match_all('/\(([^)]+)\)/', $polygon, $matches);
    $coordinatesString = $matches[1][0];
    $coordinates = explode(',', $coordinatesString);

    foreach ($coordinates as $coordinate) {
        list($longitude, $latitude) = explode(' ', trim($coordinate));

        // Cache the response for each coordinate to avoid multiple requests for the same coordinate
        $coordinateCacheKey = "geocode_{$latitude}_{$longitude}";
        $response = Cache::remember($coordinateCacheKey, $cacheDuration, function () use ($latitude, $longitude, $apiKey) {
            $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latitude},{$longitude}&key={$apiKey}";
            return Http::get($url)->json();
        });

        if (!empty($response['results'])) {
            foreach ($response['results'] as $result) {
                foreach ($result['address_components'] as $component) {
                    if (in_array('administrative_area_level_1', $component['types'])) {
                        $states[] = $component['long_name'];
                    }
                }
            }
        }
    }

    $uniqueStates = array_values(array_unique($states));
    Cache::put($cacheKey, $uniqueStates, $cacheDuration);

    return $uniqueStates;
}


function parseWktPolygon(string $wkt): array
{
    // Remove the "POLYGON((" prefix and "))" suffix
    $wkt = str_replace(['POLYGON((', '))'], '', $wkt);

    // Split the coordinates by comma
    $coordinates = explode(',', $wkt);

    // Convert to an array of lat/lng pairs
    $polygon = [];
    foreach ($coordinates as $coordinate) {
        [$lng, $lat] = explode(' ', trim($coordinate));
        $polygon[] = ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    return $polygon;
}


function shipment_create_handle_tracking_no($tracking_no)
{
    $user = Auth::user();
    if ($user->merchant) {
        if (substr($tracking_no, 0, 2) !== "PE") {
            return "PE$tracking_no";
        }

        MerchantWaybill::where('tracking_no', $tracking_no)->update(['used' => true]);

        return $tracking_no;
    } else {
        return generate_tracking_no();
    }
}

function facility_types()
{
    return [
        ['label' => 'Branch', 'value' => 'App\\Models\\Branch'],
        ['label' => 'Station', 'value' => 'App\\Models\\Station'],
        ['label' => 'Hub', 'value' => 'App\\Models\\Hub'],
    ];
}
if (!function_exists('receipt_is_required')) {
    function receipt_is_required(string $type): bool
    {
        return in_array($type, config('money.receipt_required_types', []), true);
    }
}
function facilities()
{
    $branches = Branch::select('id', 'name')->get()->map(function ($branch) {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'type' => 'App\\Models\\Branch'
        ];
    })->toArray();

    $stations = Station::select('id', 'name')->get()->map(function ($station) {
        return [
            'id' => $station->id,
            'name' => $station->name,
            'type' => 'App\\Models\\Station'
        ];
    })->toArray();

    $hubs = Hub::select('id', 'name')->get()->map(function ($hub) {
        return [
            'id' => $hub->id,
            'name' => $hub->name,
            'type' => 'App\\Models\\Hub'
        ];
    })->toArray();

    return [
        'branches' => $branches,
        'stations' => $stations,
        'hubs' => $hubs,
    ];
}

if (!function_exists('accountables')) {
    function accountables(?string $type = null): string|array|null
    {
        $map = [
            // Users
            'user' => User::class,
            'merchant' => User::class,
            'driver' => User::class,

            // Stations / Warehouses
            'station' => Station::class,
            'warehouse' => Station::class,

            // Hubs / Branches
            'hub' => Hub::class,
            'hubs' => Hub::class,
            'branch' => Hub::class,
            'branches' => Hub::class,
        ];
        if ($type === null) {
            return null;
        }
        $key = strtolower(trim($type));
        if ($key === 'all') {
            return array_values($map);
        }
        return $map[$key] ?? null;
    }
}

if (!function_exists('getAccountableFriendlyName')) {
    function getAccountableFriendlyName(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }
        if ($class === Branch::class)
            return 'Branch';
        if ($class === Station::class)
            return 'Station';
        if ($class === Hub::class)
            return 'Hub';
        if ($class === User::class)
            return 'User';
        return class_basename($class);
    }
}

function auth_first_role()
{
    return Auth::user()->roles[0];
}


function first_role($user)
{
    return $user->roles[0];
}

function setting_select_options()
{
    return [
        ['label' => 'Move To SuperVisor', 'value' => 'move_to_supervisor', 'status' => 'MOVE_TO_SUPERVISOR'],
        ['label' => 'Move to Shelf', 'value' => 'move_to_shelf', 'status' => 'MOVE_TO_SHELF'],
        ['label' => 'Move to Dispatch', 'value' => 'move_to_dispatch', 'status' => 'MOVE_TO_DISPATCH'],
        ['label' => 'Load', 'value' => 'load', 'status' => 'LOAD'],
        ['label' => 'RTC', 'value' => 'rtc', 'status' => 'RTC'],
    ];
}

function owner()
{
    $user = Auth::user();
    $selectedWorkspaceId = request('selected_workspace'); // coming from middleware

    if (!$user || !$selectedWorkspaceId) {
        return null;
    }

    if ($user->branch_user) {
        return [
            'id' => $selectedWorkspaceId,
            'type' => Branch::class,
        ];
    }

    if ($user->station_user) {
        return [
            'id' => $selectedWorkspaceId,
            'type' => Station::class,
        ];
    }

    if ($user->hub_user) {
        return [
            'id' => $selectedWorkspaceId,
            'type' => Hub::class,
        ];
    }

    return null;
}


function setTemplate()
{
    $user = Auth::user();
    if (!$user) {
        return null;
    }

    $ownerInstance = $user->owner;

    return [
        'id' => $user->owner_id,
        'type' => $ownerInstance ? get_class($ownerInstance) : null,
    ];
}


if (!function_exists('whatsAppTemplate')) {
    function whatsAppTemplate($parcel)
    {
        $customerName = $parcel->consignee->name ?? 'Customer';
        $tracking = $parcel->tracking_no ?? 'N/A';

        $message = "Hello {$customerName},\n\n"
            . "Your parcel with tracking number {$tracking} is with parcel express, kindly share your exact location .\n"
            . "Thank you for choosing our service!";

        return $message;
    }
}

function pickup_exceptions(): array
{
    return [
        'PICKUP_LOST' => [
            'name' => 'PICKUP_LOST',
            'label' => 'Parcel Lost',
            'description' => "Parcel Lost some where"
        ],
    ];
}

function pickup_status(string $key): ?array
{
    $statuses = pickup_exceptions();
    return $statuses[$key] ?? null;
}


function humanize($str)
{
    $words = explode('_', $str);
    $humanizedWords = array_map('ucfirst', $words);
    return implode(' ', $humanizedWords);
}

function transact($sender, $receiver, $amount, $shipment, $action = 'add', $type = 'cod')
{
    $request = new Request();

    $user = Auth::user();

    $senderAccount = Account::where('accountable_id', $sender->id)
        ->where('accountable_type', get_class($sender))
        ->first();


    if (!$senderAccount) {
        throw new Exception('Facility account not found.');
    }

    $recieverAccount = Account::where('accountable_id', $receiver->id)
        ->where('accountable_type', get_class($receiver))
        ->first();
    if ($type == 'cod') {
        $senderAccount->parcel_value -= $amount;
        $recieverAccount->parcel_value += $amount;
    } else {
        $senderAccount->cash_balance -= $amount;
        $recieverAccount->cash_balance += $amount;
    }


    $senderAccount->save();
    $recieverAccount->save();


    Transaction::create([
        'from_id' => $user->owner_id,
        'from_type' => $user->owner_type,
        'to_id' => $recieverAccount->accountable_id,
        'to_type' => User::class,
        'shipment_id' => $shipment->id,
        'amount' => $amount,
        'type' => 'assignment',
    ]);
}

function is_rto(string $trackingNo)
{
    $shipment = Shipment::where('tracking_no', $trackingNo)->first();
    if (!$shipment) {
        return false;
    }

    $statusName = strtoupper($shipment->coreStatus()->name ?? '');

    $rtoStates = ['RTO', 'RTO_LOADED', 'RTO_PICKED'];

    return in_array($statusName, $rtoStates, true);
}

function facility(?string $field = null)
{
    $workspaceKey = request()->header('X-Workspace-Key');
    $workspaceType = request()->header('X-Workspace-Type');

    if (empty($workspaceKey) || empty($workspaceType)) {
        return null;
    }

    try {
        $id = Crypt::decryptString($workspaceKey);
    } catch (\Exception $e) {
        return null;
    }

    $data = [
        'id' => $id,
        'type' => $workspaceType
    ];

    return match ($field) {
        'id' => $data['id'],
        'type' => $data['type'],
        default => (object) $data
    };
}

function facilityModel()
{
    $workspace = facility();
    if (!$workspace || !isset($workspace->id, $workspace->type)) {
        return null;
    }
    if (!class_exists($workspace->type)) {
        return null;
    }

    $modelClass = $workspace->type;
    $model = $modelClass::find($workspace->id);


    if (!$model) {
        return null;
    }

    $model->id = Crypt::encrypt($model->id);

    return $model;
}

function authFacilityModel()
{
    $user = auth()->user();
    if (!$user || !isset($user->owner_id, $user->owner_type)) {
        return null;
    }
    if (!class_exists($user->owner_type)) {
        return null;
    }
    

    $modelClass = $user->owner_type;
    $model = $modelClass::find($user->owner_id);


    if (!$model) {
        return null;
    }

    $model->id = Crypt::encrypt($model->id);

    return $model;
}

function shipper($shipment)
{
    return $shipment->shipper ?? Shipper::where('id', 1)->where('email', 'pe@gmail.com')->firstOrFail();
}

function is_filled($val)
{
    return !in_array($val, [null, '', 'undefined'], true);
}
;


function generate_otp()
{
    // $randomNumber = 111111;
    $randomNumber = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    return $randomNumber;
}


function activityLog($action, $description)
{
    ActivityLog::create([
        'user_id' => Auth::id() ?? 0,
        'action' => $action,
        'url' => request()->fullUrl(),
        'description' => $description,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
}


function has_role($role)
{
    return Auth::user()->hasAnyRole($role);
}

function user()
{
    return Auth::user();
}

function default_shipper_commission($state_id)
{
    return ShipperCommission::where('shipper_id', 1)->where('state_id', $state_id)->first();
}


function pinfo($data, $message = "message")
{
    info($message, [$message => $data]);
}
if (!function_exists('get_app_setting')) {
    function get_app_setting(string $key): ?array
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting && $setting->type === "json" && $setting->value) {
            $value = json_decode($setting->value, true);
            return is_array($value) ? $value : null;
        }
        return null;
    }
}

if (!function_exists('getCurrency')) {
    function getCurrency(string $lang = 'en'): string
    {
        $currencySettings = get_app_setting('currency');
        if ($currencySettings && array_key_exists($lang, $currencySettings)) {
            return $currencySettings[$lang];
        }
        return $lang === 'ar' ? 'ر.ع' : 'OMR';
    }
}


if (!function_exists('getShipmentLatLng')) {
    function getShipmentLatLng($streetAddress, $stateId)
    {

        // Get Zone using shipment street address and selected state by AiGeocodingService
        if (isset($streetAddress) && isset($stateId)) {
            $stateName = State::find($stateId);
            $svc = app(AiGeocodingService::class);
            $rev = $svc->deriveFromStreetAddress($streetAddress, "Oman", $stateName->en_name);
            $lat = $rev['lat'] ?? null;
            $lng = $rev['lng'] ?? null;
            $compoundCode = $rev['raw']['plus_code']['compound_code'] ?? null;

            return [
                "lat" => $lat,
                "lng" => $lng,
                "compoundCode" => $compoundCode,
            ];
        }
    }
}

if (!function_exists('merchantSettleShipments')) {
    function merchantSettleShipments($merchantInvoice)
    {
        // 1️⃣ Get shipments first
        $shipments = Shipment::where('merchant_id', $merchantInvoice->merchant_id)
            ->where('status', 'DELIVERED')
            ->whereNull('merchant_invoice_id')
            ->lockForUpdate() // 🔒 prevents race conditions
            ->get();


        // 2️⃣ Update them
        Shipment::whereIn('id', $shipments->pluck('id'))
            ->update([
                'merchant_invoice_id' => $merchantInvoice->id,
            ]);

        // 3️⃣ Return shipments
        return $shipments;
    }
}

if (!function_exists('driverDeliverSettleShipments')) {
    function driverDeliverSettleShipments($driverInvoice, $from, $to)
    {
        // 1️⃣ Get unpaid delivery bonus transactions
        $bonusTransactions = DriverBonusesTransaction::where('driver_id', $driverInvoice->driver_id)
            ->when($from, function ($q) use ($from) {
                $q->where('created_at', '>=', $from);
            })
            ->when($to, function ($q) use ($to) {
                $q->where('created_at', '<=', $to);
            })
            ->where('action', 'delivery')
            ->where('status', ShipmentStatusEnum::DELIVERED)
            ->where('isPaid', 0)
            ->lockForUpdate() // 🔒 prevent double settlement
            ->get();



        if ($bonusTransactions->isEmpty()) {
            return collect();
        }

        $shipmentIds = $bonusTransactions->pluck('shipment_id')->unique();

        // 2️⃣ Update shipments → link to invoice
        Shipment::whereIn('id', $shipmentIds)
            ->update([
                'driver_invoice_deliver_id' => $driverInvoice->id,
            ]);

        // 3️⃣ Mark bonus transactions as PAID
        DriverBonusesTransaction::whereIn('id', $bonusTransactions->pluck('id'))
            ->update([
                'isPaid' => 1,
            ]);

        // 4️⃣ Return settled shipments
        return Shipment::whereIn('id', $shipmentIds)->get();
    }
}


if (!function_exists('driverPickupSettleShipments')) {
    function driverPickupSettleShipments($driverInvoice, $from, $to)
    {
        // 1️⃣ Get unpaid pickup bonus transactions
        $bonusTransactions = DriverBonusesTransaction::where('driver_id', $driverInvoice->driver_id)
            ->when($from, function ($q) use ($from) {
                $q->where('created_at', '>=', $from);
            })
            ->when($to, function ($q) use ($to) {
                $q->where('created_at', '<=', $to);
            })
            ->where('action', 'pickup')
            ->where('isPaid', 0)
            ->where('status', ShipmentStatusEnum::DELIVERED)
            ->lockForUpdate() // 🔒 prevent double settlement
            ->get();


        if ($bonusTransactions->isEmpty()) {
            return collect();
        }

        $shipmentIds = $bonusTransactions->pluck('shipment_id')->unique();

        // 2️⃣ Update shipments → link to pickup invoice
        Shipment::whereIn('id', $shipmentIds)
            ->update([
                'driver_invoice_pickup_id' => $driverInvoice->id,
            ]);

        // 3️⃣ Mark pickup bonus transactions as PAID
        DriverBonusesTransaction::whereIn('id', $bonusTransactions->pluck('id'))
            ->update([
                'isPaid' => 1,
            ]);

        // 4️⃣ Return settled shipments
        return Shipment::whereIn('id', $shipmentIds)->get();
    }
}
if (!function_exists('handelMessage')) {
    /**
     * @param string $message
     * @param array $data
     * @return string
     */
    function handelMessage($message, array $data = []): string
    {
        return preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function ($matches) use ($data) {
            $key = trim($matches[1]);
            return $data[$key] ?? '';
        }, $message);
    }
}
if (!function_exists('totalFinancialRequest')) {

    function totalFinancialRequest($ids = [], $type = "branch_settlement")
    {
        $total = 0;
        if ($type == "driver_salary") {
            $driverService = app(DriverService::class);
            $drivers = User::whereIn("id", $ids)->get();
            $isPaid = 0;
            foreach ($drivers as $driver)
                $total += $driverService->calculateSettlementDue($driver, null, null, $isPaid);
        } else if ($type == "merchant_settlement") {
            $merchantService = app(MerchantTransactionService::class);
            $merchants = User::whereIn("id", $ids)->get();
            foreach ($merchants as $merchant)
                $total += $merchantService->getBalance($merchant->id);
        } else if ($type == "branch_settlement") {
            $net = app(WarehouseNetBalanceService::class)
                ->calculate(facility()->type, facility()->id);
            $total = $net;
        }

        return $total;
    }
}
if (!function_exists('getDriverBonus')) {

    function getDriverBonus($driver_id, $shipment_id, $action)
    {
        $bonus = DriverBonusesTransaction::where("driver_id", $driver_id)->where("shipment_id", $shipment_id)->where("action", $action)->first();

        return $bonus->bonus_amount;
    }
}
if (!function_exists('parseInputToUtcRange')) {
    function parseInputToUtcRange(?string $date, string $timezone): ?array
    {
        if (!$date)
            return null;

        $service = app(\App\Services\TimezoneService::class);
        return $service->parseToUtcRange($date, $timezone);
    }
}



if (!function_exists('getUserHub')) {
    function getUserHub($user = null)
    {
        // Use workspace headers first (your current system)
        $facility = facility(); // stdClass { id, type }

        if (!$facility) {
            return null;
        }

        // Case 1: Facility is already a Hub
        if ($facility->type === Hub::class) {
            return Hub::find($facility->id);
        }

        // Case 2: Facility is a Station → get its Hub
        if ($facility->type === Station::class) {
            $station = Station::with('hub')->find($facility->id);
            return $station?->hub;
        }

        // Case 3: Facility is a Branch → Station → Hub
        if ($facility->type === Branch::class) {
            $branch = Branch::with('station.hub')->find($facility->id);
            return $branch?->station?->hub;
        }

        return null;
    }
}
