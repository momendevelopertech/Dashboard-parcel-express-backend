<?php

namespace App\Imports;

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
use App\Models\MerchantWaybill;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\State;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MerchantShipmentImport implements ToCollection, WithHeadingRow
{

    private $unimportableRows;
    private $importableRows;
    private $missingCountries;
    private $missingGovernorates;
    private $missingStates;
    private $importedCount;
    private $skippedCount;
    private $errors;
    private $rowIndex;
    private $merchantId;

    public function __construct($merchantId)
    {
        $this->merchantId = $merchantId;
        $this->unimportableRows = [];
        $this->importableRows = [];
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
        'cod',
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

            $firstRow = $collection->first();
            $missingColumns = [];
            
            foreach ($this->requiredColumns as $column) {
                if (!array_key_exists($column, $firstRow->toArray())) {
                    $missingColumns[] = $column;
                }
            }

            if (!empty($missingColumns)) {
                throw new Exception('Missing columns: ' . implode(', ', $missingColumns));
            }

            // Get Parcel Express shipper (merchant shipments use internal delivery)
            $parcelExpress = Shipper::pe();
            if (!$parcelExpress) {
                throw new Exception('Parcel Express shipper not found. Please configure the system properly.');
            }

            foreach ($collection as $row) {
                $this->rowIndex++;
                
                try {
                    // Skip completely empty rows
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

                    $rowIdentifier = $this->getRowIdentifier($row);

                    // Validate required fields
                    $missingRequired = false;
                    $missingFields = [];
                    foreach ($this->requiredColumns as $column) {
                        $value = trim(data_get($row, $column, ''));
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
                    $recipientCountry = trim($row['recipient_country']);
                    $recipientState = trim($row['recipient_state']);
                    $recipientCity = trim($row['recipient_city']);

                    // PRIORITY 1: Validate MerchantWaybill ownership and usage
                    $merchantWaybill = MerchantWaybill::where('tracking_no', $trackingNo)->first();
                    
                    if (!$merchantWaybill) {
                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (Waybill not found)';
                        continue;
                    }

                    if ($merchantWaybill->merchant_id != $this->merchantId) {
                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (Waybill not owned by merchant)';
                        continue;
                    }

                    if ($merchantWaybill->used == 1) {
                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (Waybill already used)';
                        continue;
                    }

                    // Check if tracking number already exists in shipments
                    if (Shipment::where('tracking_no', $trackingNo)->exists()) {
                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (Duplicate tracking number)';
                        continue;
                    }

                    // Validate COD amount
                    $codValue = trim($row['cod']);
                    $cleanedCodValue = str_replace(['OMR', ' ', ','], '', $codValue);
                    if (!is_numeric($cleanedCodValue)) {
                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (Invalid COD amount)';
                        continue;
                    }

                    // Validate location data directly from system tables (no channels needed for Parcel Express)
                    $country = Country::where('name', $recipientCountry)->first();
                    $governorate = Governorate::where('en_name', $recipientState)
                        ->orWhere('ar_name', $recipientState)
                        ->first();
                    $state = State::where('en_name', $recipientCity)
                        ->orWhere('ar_name', $recipientCity)
                        ->first();

                    $missingCountry = !$country;
                    $missingGovernorates = !$governorate;
                    $missingStates = !$state;

                    if ($missingCountry || $missingGovernorates || $missingStates) {
                        if ($missingCountry && !in_array($recipientCountry, $this->missingCountries)) {
                            $this->missingCountries[] = $recipientCountry;
                        }
                        if ($missingGovernorates && !in_array($recipientState, $this->missingGovernorates)) {
                            $this->missingGovernorates[] = $recipientState;
                        }
                        if ($missingStates && !in_array($recipientCity, $this->missingStates)) {
                            $this->missingStates[] = $recipientCity;
                        }

                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (Invalid location data)';
                        continue;
                    }

                    // Validate governorate belongs to country and state belongs to governorate
                    if ($governorate->country_id != $country->id) {
                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (Governorate does not belong to country)';
                        continue;
                    }

                    if ($state->governorate_id != $governorate->id) {
                        $this->skippedCount++;
                        $this->errors[] = $trackingNo . ' (State does not belong to governorate)';
                        continue;
                    }

                    // Generate tracking number if empty (fallback)
                    if (empty($trackingNo)) {
                        $trackingNo = generate_tracking_no();
                    }

                    // Create consignee
                    $consignee = Consignee::create([
                        "name" => trim($row['recipient_name']),
                        "cellphone" => trim($row['recipient_cellphone']),
                        "alternatePhone" => trim(data_get($row, 'recipient_alternate_phone', '')),
                        "country_id" => $country->id,
                        "governorate_id" => $governorate->id,
                        "state_id" => $state->id,
                        "zipcode" => trim(data_get($row, 'recipient_zipcode', '')),
                        "streetAddress" => trim($row['recipient_street_address']),
                    ]);

                    // Create shipment with merchant ID (no delivery fee needed for internal Parcel Express)
                    $shipment = Shipment::create([
                        'shipper_id' => $parcelExpress->id,
                        'consignee_id' => $consignee->id,
                        'merchant_id' => $this->merchantId,
                        'tracking_no' => $trackingNo,
                        'delivery_fee' => 0, // Parcel Express internal delivery - no commission
                        'amount' => doubleval($cleanedCodValue),
                        'is_return' => isset($row['ofd_times']) ? (int)$row['ofd_times'] === 1 : false,
                        'payment_type' => trim($row['payment_type']),
                        'fee_payer' => '',
                    ]);

                    // Mark merchant waybill as used
                    $merchantWaybill->used = 1;
                    $merchantWaybill->save();

                    // Create shipment information
                    ShipmentInformation::create([
                        'shipment_id' => $shipment->id,
                        'tracking_no' => $trackingNo,
                        'in_warehouse' => 1,
                        'weight' => data_get($row, 'declare', null) ? trim($row['declare']) : null,
                        'unit_id' => data_get($row, 'weight_g', null) ? trim($row['weight_g']) : null,
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
                        'data' => json_encode($row->toArray())
                    ]);

                    $this->importableRows[] = $trackingNo;
                    $this->importedCount++;

                } catch (Exception $e) {
                    $this->skippedCount++;
                    $this->errors[] = ($this->getRowIdentifier($row) ?? 'Row ' . $this->rowIndex) . ' (Error: ' . $e->getMessage() . ')';
                    Log::error('Shipment Import Row Error: ' . $e->getMessage(), [
                        'row' => $row->toArray(),
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
        if (!empty(trim(data_get($row, 'tracking_no', '')))) {
            return trim($row['tracking_no']);
        }
        
        if (!empty(trim(data_get($row, 'recipient_name', '')))) {
            return trim($row['recipient_name']) . ' (Row ' . $this->rowIndex . ')';
        }
        
        return 'Row ' . $this->rowIndex;
    }

    public function getPreviewData(): array
    {
        return [
            'importableShipments'   => $this->importableRows,
            'unimportableShipments' => $this->unimportableRows,
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
        ];
    }
}
