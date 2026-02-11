<?php

namespace App\Imports;

use App\Http\Controllers\SorterController;
use App\Models\Address;
use App\Models\Shipper;
use App\Models\Consignee;
use App\Models\CountryChannel;
use App\Models\GovernorateChannel;
use App\Models\StateChannel;
use App\Models\Shipment;
use App\Models\ShipmentDelivery;
use App\Models\ShipmentFinance;
use App\Models\ShipmentInformation;
use App\Models\ShipmentIntegration;
use App\Models\Setting;
use App\Models\ShipperCommission;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;
use App\Notifications\ShipmentCreatedNotification;
use App\Notifications\OutsourcedShipmentCreatedNotification;
use App\Services\AddressUpdateLinkService;
use App\Services\Geocoding\AiGeocodingService;
use App\Services\Geocoding\ReverseGeocodingService;

class ShipmentImport implements ToCollection, WithHeadingRow
{
    private bool $outsourced;
    private $unimportableRows;
    private $importableRows;
    private $commissions;
    private $missingCountries;
    private $missingGovernorates;
    private $missingStates;
    private $importedCount;
    private $skippedCount;
    private $errors;
    private $rowIndex;

    private $marketplacePartnerId;

    public function __construct(bool $outsourced = false, ?int $marketplacePartnerId = null)
    {
        $this->outsourced = $outsourced;
        $this->marketplacePartnerId = $marketplacePartnerId;
        $this->unimportableRows = [];
        $this->importableRows = [];
        $this->commissions = [];
        $this->missingCountries = [];
        $this->missingGovernorates = [];
        $this->missingStates = [];
        $this->importedCount = 0;
        $this->skippedCount = 0;
        $this->errors = [];
        $this->rowIndex = 1;
    }

    protected $requiredColumns = [
        'tracking_no',
        'recipient_country',
        'recipient_state',
        'recipient_city',
        'recipient_name',
        'recipient_cellphone',
        'recipient_street_address',
        // 'cod',
        'payment_type',
    ];

    protected $optionalColumns = [
        'recipient_alternate_phone',
        'recipient_zipcode',
        'declare',
        'weight_g',
        'ofd_times',
    ];

    public function collection(Collection $collection)
    {
        DB::beginTransaction();

        try {
            // Validate headers exist
            if ($collection->isEmpty()) {
                throw new Exception('File is empty or has no data rows');
            }
            $sendWhatsappSetting = Setting::where('key', 'send_whatsapp_after_create_shipment')->value('value');
            $shouldSendWhatsapp = $sendWhatsappSetting === 'yes';

            $firstRow = $collection->first();
            $missingColumns = [];

            foreach ($this->requiredColumns as $column) {
                if (!array_key_exists($column, (array) $firstRow)) {
                    $missingColumns[] = $column;
                }
            }

            if (!empty($missingColumns)) {
                throw new Exception('Missing columns: ' . implode(', ', $missingColumns));
            }

            // Get default shipper
            $gfs = Shipper::where('email', 'gfs@gmail.com')->first();
            $norm = function (?string $s) {
                $s = trim((string) $s);
                // عربي
                $s = preg_replace('/^\s*ولاية\s+/u', '', $s);        // يشيل "ولاية "
                $s = preg_replace('/\s*محافظة\s*/u', ' ', $s);       // يشيل "محافظة"
                // إنجليزي
                $s = preg_replace('/\bGovernorate\b$/i', '', $s);
                $s = preg_replace('/^Wilayat\s+/i', '', $s);
                // عام
                $s = str_replace(['-', '–', '—'], ' ', $s);
                $s = preg_replace('/\s+/', ' ', $s);
                return mb_strtolower($s);
            };

            $alias = function (?string $s) use ($norm) {
                static $map = [
                'مسقط' => 'muscat',
                'مسقط' => 'muscat',
                'الخابورة' => 'al khaburah',
                'شمال الباطنة' => 'north al batinah',
                'muscat governorate' => 'muscat',
                'al khuburah' => 'al khaburah',
                'al khubara' => 'al khaburah',
                ];
                $k = $norm($s);
                return $map[$k] ?? $k;
            };

            $govIndex = \App\Models\GovernorateChannel::where('shipper_id', $gfs->id)->get()
                ->keyBy(function ($c) use ($alias) {
                    return $alias($c->external_governorate_name);
                });

            $stateIndex = \App\Models\StateChannel::where('shipper_id', $gfs->id)->get()
                ->keyBy(function ($c) use ($alias) {
                    return $alias($c->external_state_name);
                });

            $AUTO = filter_var(env('IMPORT_AUTOCREATE_CHANNELS', false), FILTER_VALIDATE_BOOL);

            foreach ($collection as $row) {
                $this->rowIndex++;

                try {
                    $allFieldsEmpty = true;
                    foreach ($row as $value) {
                        if (trim($value) !== '') {
                            $allFieldsEmpty = false;
                            break;
                        }
                    }
                    if ($allFieldsEmpty) {
                        continue;
                    }

                    $firstValue = trim(array_values((array) $row)[0] ?? '');
                    if (str_contains($firstValue, 'Required') || str_contains($firstValue, 'Optional')) {
                        continue;
                    }

                    $rowIdentifier = $this->getRowIdentifier($row);

                    $paymentTypeRaw = (string) data_get($row, 'payment_type', '');
                    $paymentType = strtolower(trim($paymentTypeRaw));

                    $missingRequired = false;
                    $missingFields = [];

                    $street = trim((string) data_get($row, 'recipient_street_address', ''));
                    $stateVal = trim((string) data_get($row, 'recipient_state', ''));
                    $cityVal = trim((string) data_get($row, 'recipient_city', ''));
                    $countryBias = trim((string) data_get($row, 'recipient_country', env('COUNTRY_DEFAULT', 'Oman')));

                    $svc = app(AiGeocodingService::class);
                    $rev = $svc->deriveFromStreetAddress($street, $countryBias, $stateVal);

                    if ($rev) {
                        $row['recipient_state'] = $rev['state'] ?? $row['recipient_state'];
                        $row['recipient_city'] = $rev['city'] ?? $row['recipient_city'];
                        $row['_lat'] = $rev['lat'] ?? null;
                        $row['_lng'] = $rev['lng'] ?? null;
                        $row['_plus_code'] = $rev['plus_code'] ?? null;
                    }
                    foreach ($this->requiredColumns as $column) {
                        if ($column === 'cod' && $paymentType !== 'cod') {
                            continue;
                        }
                        if ($column === 'tracking_no') {
                            continue; // ← أو اشيله من الـrequiredColumns
                        }

                        $value = trim((string) data_get($row, $column, ''));
                        if ($value === '') {
                            $missingRequired = true;
                            $missingFields[] = $column;
                        }
                    }

                    if ($missingRequired) {
                        $this->skippedCount++;
                        $this->errors[] = $rowIdentifier . ' (Missing: ' . implode(', ', $missingFields) . ')';
                        continue;
                    }

                    $trackingNo = trim($row['tracking_no']);
                    $paymentType = strtolower(trim($row['payment_type'] ?? ''));
                    // لو Outsourced امنع PE
                    if ($this->outsourced && strtoupper(substr($trackingNo, 0, 2)) === 'PE') {
                        $this->skippedCount++;
                        $this->errors[] = "$trackingNo (Not allowed for outsourced: starts with PE)";
                        continue;
                    }
                    $recipientCountry = trim((string) data_get($row, 'recipient_country', ''));
                    $recipientState = $alias((string) data_get($row, 'recipient_state', ''));
                    $recipientCity = $alias((string) data_get($row, 'recipient_city', ''));

                    // CountryChannel: خليه case-insensitive
                    $country_channel = \App\Models\CountryChannel::where('shipper_id', $gfs->id)
                        ->whereRaw('LOWER(external_country_name) = ?', [strtolower($recipientCountry)])
                        ->first();

                    // GovernorateChannel & StateChannel من الفهارس
                    $governorate_channel = $govIndex[$recipientState] ?? null;
                    $state_channel = $stateIndex[$recipientCity] ?? null;

                    // Fallback بسيط (تقريبي) لو لسه ملقيناش
                    if (!$governorate_channel) {
                        foreach ($govIndex as $k => $c) {
                            if (strpos($k, $recipientState) !== false || levenshtein($k, $recipientState) <= 2) {
                                $governorate_channel = $c;
                                break;
                            }
                        }
                    }
                    if (!$state_channel) {
                        foreach ($stateIndex as $k => $c) {
                            if (strpos($k, $recipientCity) !== false || levenshtein($k, $recipientCity) <= 2) {
                                $state_channel = $c;
                                break;
                            }
                        }
                    }

                    // (اختياري) إنشاء تلقائي للقنوات لو ناقصة وعندك الجداول الداخلية Governorate/State
                    if ($AUTO) {
                        if (!$governorate_channel && $recipientState) {
                            $govModel = \App\Models\Governorate::whereRaw('LOWER(en_name)=?', [$recipientState])->first();
                            if ($govModel) {
                                $governorate_channel = \App\Models\GovernorateChannel::create([
                                    'shipper_id' => $gfs->id,
                                    'internal_governorate_id' => $govModel->id,
                                    'external_governorate_name' => ucwords($recipientState),
                                ]);
                                $govIndex[$recipientState] = $governorate_channel; // حدّث الفهرس
                            }
                        }
                        if (!$state_channel && $recipientCity) {
                            $stateModel = \App\Models\State::whereRaw('LOWER(en_name)=?', [$recipientCity])->first();
                            if ($stateModel) {
                                $state_channel = \App\Models\StateChannel::create([
                                    'shipper_id' => $gfs->id,
                                    'internal_state_id' => $stateModel->id,
                                    'external_state_name' => ucwords($recipientCity),
                                ]);
                                $stateIndex[$recipientCity] = $state_channel; // حدّث الفهرس
                            }
                        }
                    }
                    if ($this->outsourced && strtoupper(substr($trackingNo, 0, 2)) === 'PE') {
                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (Not allowed for outsourced: starts with PE)';
                        continue;
                    }

                    $trackingNo = strtoupper(trim((string) data_get($row, 'tracking_no', '')));

                    // اكشف التكرار على مستوى الجدول كله (بدون سكوبات)
                    $duplicate = DB::table('shipments')->where('tracking_no', $trackingNo)->exists();
                    // أو:
                    // $duplicate = Shipment::withoutGlobalScopes()->where('tracking_no', $trackingNo)->exists();

                    if ($trackingNo !== '' && $duplicate) {
                        $this->skippedCount++;
                        $this->errors[] = "$trackingNo (Duplicate tracking number)";
                        continue;
                    }

                    // Validate COD amount
                    // $codValue = trim($row['cod']);
                    // $cleanedCodValue = str_replace(['OMR', ' ', ','], '', $codValue);
                    // if (!is_numeric($cleanedCodValue)) {
                    //     $this->skippedCount++;
                    //     $this->errors[] = $trackingNo . ' (Invalid COD amount)';
                    //     continue;
                    // }
                    $normalized = strtolower(trim((string) data_get($row, 'payment_type', '')));
                    $normalized = preg_replace('/\s+/', ' ', $normalized);
                    $paymentType = ($normalized === 'cod') ? 'cod' : 'paid';

                    if ($paymentType === 'cod') {
                        $rawCod = (string) data_get($row, 'cod', '');
                        $cleaned = str_replace(['OMR', ' ', ','], '', $rawCod);
                        if ($cleaned === '' || !is_numeric($cleaned)) {
                            $this->skippedCount++;
                            $this->errors[] = "$trackingNo (Invalid COD amount)";
                            continue;
                        }
                        $codValue = (float) $cleaned;
                    } else {
                        $codValue = 0.0;
                    }
                    // Check channels
                    // $country_channel = CountryChannel::where('shipper_id', $gfs->id)
                    //     ->where('external_country_name', $recipientCountry)
                    //     ->first();

                    // $governorate_channel = GovernorateChannel::where('shipper_id', $gfs->id)
                    //     ->where('external_governorate_name', $recipientState) // هنا الاسم لازم يطابق تمامًا
                    //     ->first();

                    // $state_channel = StateChannel::where('shipper_id', $gfs->id)
                    //     ->where('external_state_name', $recipientCity)
                    //     ->first();

                    $missingCountry = !$country_channel;
                    $missingGovernorates = !$governorate_channel;
                    $missingStates = !$state_channel;

                    if ($missingCountry || $missingGovernorates || $missingStates) {
                        // خزّن القيم المفقودة لعرضها بعد الاستيراد
                        if ($missingCountry)
                            $this->missingCountries[] = $recipientCountry;
                        if ($missingGovernorates)
                            $this->missingGovernorates[] = $recipientState;
                        if ($missingStates)
                            $this->missingStates[] = $recipientCity;

                        $reason = [];
                        if ($missingCountry)
                            $reason[] = "country={$recipientCountry}";
                        if ($missingGovernorates)
                            $reason[] = "governorate={$recipientState}";
                        if ($missingStates)
                            $reason[] = "state={$recipientCity}";

                        $this->skippedCount++;
                        $this->unimportableRows[] = $trackingNo . ' (Missing channels: ' . implode(', ', $reason) . ')';

                        Log::warning('Shipment skipped - missing channels', [
                            'tracking' => $trackingNo,
                            'country' => $recipientCountry,
                            'governorate' => $recipientState,
                            'state' => $recipientCity,
                        ]);
                        continue;
                    }


                    // Check commission
                    $shipper_commission = ShipperCommission::where('state_id', $state_channel->internal_state_id)->first();
                    if (!$shipper_commission) {
                        if (!in_array($state_channel->state->en_name, $this->commissions)) {
                            $this->commissions[] = $state_channel->state->en_name;
                        }
                        $this->skippedCount++;
                        $this->unimportableRows[] = $trackingNo . ' (Missing commission)';
                        continue;
                    }

                    // Generate tracking number if empty (fallback)
                    if (empty($trackingNo)) {
                        $trackingNo = generate_tracking_no();
                    }

                    // Create consignee
                    // $consignee = Consignee::create([
                    //     "name" => trim($row['recipient_name']),
                    //     "cellphone" => trim($row['recipient_cellphone']),
                    //     "alternatePhone" => trim(data_get($row, 'recipient_alternate_phone', '')),
                    //     "country_id" => $country_channel->internal_country_id,
                    //     "governorate_id" => $governorate_channel->internal_governorate_id,
                    //     "state_id" => $state_channel->internal_state_id,
                    //     "zipcode" => trim(data_get($row, 'recipient_zipcode', '')),
                    //     "streetAddress" => trim($row['recipient_street_address']),
                    // ]);

                    $cellSplit = splitPhoneNumber(trim($row['recipient_cellphone']));
                    $altRaw = trim((string) data_get($row, 'recipient_alternate_phone', ''));
                    $altSplit = $altRaw ? splitPhoneNumber($altRaw) : ['country_code' => null, 'national_number' => null];
                    $lat = data_get($row, '_lat');
                    $lng = data_get($row, '_lng');
                    $plus = data_get($row, '_plus_code');

                    $gmapsUrl = null;
                    if (!is_null($lat) && !is_null($lng)) {
                        $gmapsUrl = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
                    } elseif (!empty($plus)) {
                        $rc = trim((string) data_get($row, 'recipient_city', ''));
                        $rcty = trim((string) data_get($row, 'recipient_country', ''));
                        $q = urlencode(trim($plus . ' ' . $rc . ' ' . $rcty));
                        $gmapsUrl = "https://www.google.com/maps/search/?api=1&query={$q}";
                    }
                    $consignee = Consignee::create([
                        "name" => trim($row['recipient_name']),
                        "cellphone" => $cellSplit['national_number'],
                        "country_key_cellphone" => $cellSplit['country_code'],
                        "alternatePhone" => $altSplit['national_number'],
                        "country_key_alternatePhone" => $altSplit['country_code'],
                        "country_id" => $country_channel->internal_country_id,
                        "governorate_id" => $governorate_channel->internal_governorate_id,
                        "state_id" => $state_channel->internal_state_id,
                        "zipcode" => trim(data_get($row, 'recipient_zipcode', '')),
                        "streetAddress" => trim($row['recipient_street_address']),
                        "latitude" => !is_null($lat) ? (string) number_format((float) $lat, 7, '.', '') : null,
                        "longitude" => !is_null($lng) ? (string) number_format((float) $lng, 7, '.', '') : null,
                        "location_url" => $gmapsUrl,
                    ]);
                    $deliveryAddressInput = [
                        'country_id' => $country_channel->internal_country_id,
                        'governorate_id' => $governorate_channel->internal_governorate_id,
                        'state_id' => $state_channel->internal_state_id,
                        'place_id' => null,
                        'city_id' => null,
                        'zipcode' => trim((string) data_get($row, 'recipient_zipcode', '')) ?: null,
                        'streetAddress' => trim((string) data_get($row, 'recipient_street_address', '')) ?: null,
                        'latitude' => !is_null($lat) ? (string) number_format((float) $lat, 7, '.', '') : null,
                        'longitude' => !is_null($lng) ? (string) number_format((float) $lng, 7, '.', '') : null,
                        'location_url' => $gmapsUrl,
                        'label' => null,
                    ];

                    $deliveryAddress = Address::firstOrCreate(
                        [
                            'consignee_id' => $consignee->id,
                            'country_id' => $deliveryAddressInput['country_id'],
                            'governorate_id' => $deliveryAddressInput['governorate_id'],
                            'state_id' => $deliveryAddressInput['state_id'],
                            'streetAddress' => $deliveryAddressInput['streetAddress'],
                            'zipcode' => $deliveryAddressInput['zipcode'],
                        ],
                        array_merge($deliveryAddressInput, ['consignee_id' => $consignee->id])
                    );
                    // Create shipment
                    // $shipment = Shipment::create([
                    //     'shipper_id' => $gfs->id,
                    //     'consignee_id' => $consignee->id,
                    //     'tracking_no' => $trackingNo,
                    //     'delivery_fee' => $shipper_commission->delivery_fee,
                    //     'amount' => doubleval($cleanedCodValue),
                    //     'is_return' => isset($row['ofd_times']) ? (int) $row['ofd_times'] === 1 : false,
                    //     'payment_type' => trim($row['payment_type']),
                    //     'fee_payer' => '',
                    //     'is_outsourced' => $this->outsourced ? 1 : 0,
                    // ]);

                    $deliveryFee = (float) ($shipper_commission->delivery_fee ?? 0);
                    $shipment = Shipment::create([
                        'shipper_id' => $gfs->id,
                        'marketplace_partner_id' => $this->marketplacePartnerId,
                        'consignee_id' => $consignee->id,
                        'tracking_no' => $trackingNo,
                        'delivery_fee' => $deliveryFee,
                        'value' => $codValue,
                        'amount' => $codValue + $deliveryFee,
                        'payment_type' => $paymentType === 'cod' ? 'COD' : 'Paid',
                        'is_return' => isset($row['ofd_times']) ? ((int) $row['ofd_times'] === 1) : false,
                        'fee_payer' => 'customer',
                        'is_outsourced' => $this->outsourced ? 1 : 0,
                        'delivery_address_id' => $deliveryAddress->id,
                    ]);
                    $zone = app(SorterController::class)->resolveZoneForShipment($shipment);
                    if ($zone) {
                        $shipment->destination_owner_id = $zone->owner_id;
                        $shipment->destination_owner_type = $zone->owner_type;
                        $shipment->save();
                    } else {
                        \Log::warning('Zone not resolved for imported shipment', ['tracking_no' => $shipment->tracking_no]);
                    }
                    // Create shipment information
                    ShipmentInformation::create([
                        'shipment_id' => $shipment->id,
                        'tracking_no' => $trackingNo,
                        'in_warehouse' => 1,
                        // 'weight' => data_get($row, 'declare', null) ? trim($row['declare']) : null,
                        // 'unit_id' => data_get($row, 'weight_g', null) ? trim($row['weight_g']) : null,
                        'weight' => data_get($row, 'weight_g', null) ? (float) trim($row['weight_g']) : null,
                        'declare' => data_get($row, 'declare', null) ? trim($row['declare']) : null,
                    ]);

                    // Create shipment delivery
                    ShipmentDelivery::create([
                        "shipment_id" => $shipment->id
                    ]);

                    // Create shipment finance
                    ShipmentFinance::create([
                        "shipment_tracking_no" => $shipment->tracking_no
                    ]);

                    // Create shipment integration
                    ShipmentIntegration::create([
                        'shipment_tracking_no' => $trackingNo,
                        'data' => json_encode((array) $row)
                    ]);

                    $this->importableRows[] = $trackingNo;
                    $this->importedCount++;
                    $link = app(AddressUpdateLinkService::class)->generate($shipment);


                    if ($shouldSendWhatsapp) {
                        $shipment->consignee->notify(new OutsourcedShipmentCreatedNotification(
                            $shipment,
                            $link['url'],
                            $link['otp']
                        ));
                    }
                } catch (Exception $e) {
                    $this->skippedCount++;
                    $this->errors[] = ($this->getRowIdentifier($row) ?? 'Row ' . $this->rowIndex) . ' (Error: ' . $e->getMessage() . ')';
                    Log::error('Shipment Import Row Error: ' . $e->getMessage(), [
                        'row' => (array) $row,
                        'row_index' => $this->rowIndex
                    ]);
                    continue;
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Shipment Import Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getRowIdentifier($row): ?string
    {
        if (!empty(trim($row['tracking_no'] ?? ''))) {
            return trim($row['tracking_no']);
        }
        if (!empty(trim($row['recipient_name'] ?? ''))) {
            return trim($row['recipient_name']) . ' (Row ' . $this->rowIndex . ')';
        }
        return 'Row ' . $this->rowIndex;
    }

    public function getPreviewData(): array
    {
        return [
            'importableShipments' => $this->importableRows,
            'unimportableShipments' => $this->unimportableRows,
            'commissions' => array_unique($this->commissions),
            'missingCountries' => array_unique($this->missingCountries),
            'missingGovernorates' => array_unique($this->missingGovernorates),
            'missingStates' => array_unique($this->missingStates),
        ];
    }

    public function getImportResults(): array
    {
        return [
            'imported' => $this->importedCount,
            'skipped' => $this->skippedCount,
            'errors' => $this->errors,
            'importableShipments' => $this->importableRows,
            'unimportableShipments' => $this->unimportableRows,
            // جديد:
            'missingCountries' => array_values(array_unique($this->missingCountries)),
            'missingGovernorates' => array_values(array_unique($this->missingGovernorates)),
            'missingStates' => array_values(array_unique($this->missingStates)),
        ];
    }
}
