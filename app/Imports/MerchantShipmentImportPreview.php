<?php

namespace App\Imports;

use Exception;
use App\Models\Shipper;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\State;
use App\Models\Shipment;
use App\Models\MerchantWaybill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MerchantShipmentImportPreview implements ToCollection, WithHeadingRow
{

    private $unimportableRows;
    private $importableRows;
    private $missingCountries;
    private $missingGovernorates;
    private $missingStates;
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

                // Skip description rows (they contain text like "Required" or "Optional")
                $firstValue = trim(array_values($row->toArray())[0] ?? '');
                if (str_contains($firstValue, 'Required') || str_contains($firstValue, 'Optional')) {
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
                    $this->unimportableRows[] = $rowIdentifier . ' (Missing: ' . implode(', ', $missingFields) . ')';
                    continue;
                }

                $trackingNo = trim($row['tracking_no']);
                $recipientCountry = trim($row['recipient_country']);
                $recipientState = trim($row['recipient_state']);
                $recipientCity = trim($row['recipient_city']);

                // PRIORITY 1: Validate MerchantWaybill ownership and usage
                $merchantWaybill = MerchantWaybill::where('tracking_no', $trackingNo)->first();
                
                if (!$merchantWaybill) {
                    $this->unimportableRows[] = $trackingNo . ' (Waybill not found)';
                    continue;
                }

                if ($merchantWaybill->merchant_id != $this->merchantId) {
                    $this->unimportableRows[] = $trackingNo . ' (Waybill not owned by merchant)';
                    continue;
                }

                if ($merchantWaybill->used == 1) {
                    $this->unimportableRows[] = $trackingNo . ' (Waybill already used)';
                    continue;
                }

                // Check if tracking number already exists in shipments
                if (Shipment::where('tracking_no', $trackingNo)->exists()) {
                    $this->unimportableRows[] = $trackingNo . ' (Duplicate tracking number)';
                    continue;
                }

                // Validate COD amount
                $codValue = trim($row['cod']);
                if (!is_numeric(str_replace(['OMR', ' ', ','], '', $codValue))) {
                    $this->unimportableRows[] = $trackingNo . ' (Invalid COD amount)';
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

                    $this->unimportableRows[] = $trackingNo . ' (Invalid location data)';
                    continue;
                }

                // Validate governorate belongs to country and state belongs to governorate
                if ($governorate->country_id != $country->id) {
                    $this->unimportableRows[] = $trackingNo . ' (Governorate does not belong to country)';
                    continue;
                }

                if ($state->governorate_id != $governorate->id) {
                    $this->unimportableRows[] = $trackingNo . ' (State does not belong to governorate)';
                    continue;
                }

                // If we reach here, the shipment is importable
                $this->importableRows[] = $trackingNo;
            }
        } catch (Exception $e) {
            Log::error('Shipment Import Preview Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getRowIdentifier($row): string
    {
        if (!empty(trim($row['tracking_no']))) {
            return trim($row['tracking_no']);
        }
        
        if (!empty(trim($row['recipient_name']))) {
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
}
