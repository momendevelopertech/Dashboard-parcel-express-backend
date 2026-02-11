<?php

namespace App\Imports;

use Exception;
use App\Models\Shipper;
use App\Models\CountryChannel;
use App\Models\GovernorateChannel;
use App\Models\StateChannel;
use App\Models\ShipperCommission;
use App\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ShipmentImportPreview implements ToCollection, WithHeadingRow
{
    private bool $outsourced;
    private $unimportableRows;
    private $importableRows;
    private $commissions;
    private $missingCountries;
    private $missingGovernorates;
    private $missingStates;
    private $rowIndex;

    public function __construct(bool $outsourced = false)
    {
        $this->outsourced = $outsourced;
        $this->unimportableRows = [];
        $this->importableRows = [];
        $this->commissions = [];
        $this->missingCountries = [];
        $this->missingGovernorates = [];
        $this->missingStates = [];
        $this->rowIndex = 1;
    }

    // مفيش cod ضمن الأعمدة الإلزامية ⭐
    protected $requiredColumns = [
        'tracking_no',
        'recipient_country',
        'recipient_state',
        'recipient_city',
        'recipient_name',
        'recipient_cellphone',
        'recipient_street_address',
        'payment_type',
    ];

    protected $optionalColumns = [
        'recipient_alternate_phone',
        'recipient_zipcode',
        'declare',
        'weight_g',
        'ofd_times',
        // 'cod' عمود اختياري
    ];

    // ⭐ دالة مساعدة لتطبيع قيمة COD
    private function parseCod($raw): array
    {
        if ($raw === null) {
            return ['present' => false, 'amount' => 0.0, 'raw' => null, 'valid' => true];
        }

        $rawStr = trim((string) $raw);
        if ($rawStr === '') {
            return ['present' => false, 'amount' => 0.0, 'raw' => '', 'valid' => true];
        }

        // اسمح برموز مثل OMR والفواصل
        $clean = preg_replace('/[^\d.]/', '', $rawStr);

        // لو بعد التنظيف مفيش أرقام → غير صالح
        if ($clean === '' || !is_numeric($clean)) {
            return ['present' => true, 'amount' => 0.0, 'raw' => $rawStr, 'valid' => false];
        }

        $amount = (float) $clean;
        return ['present' => true, 'amount' => $amount, 'raw' => $rawStr, 'valid' => true];
    }

    public function collection(Collection $collection)
    {
        try {
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

            $gfs = Shipper::where('email', 'gfs@gmail.com')->first();
            if (!$gfs) {
                throw new Exception('Default shipper not found. Please configure the system properly.');
            }

            foreach ($collection as $row) {
                $this->rowIndex++;

                // تخطي الصفوف الفارغة
                $allFieldsEmpty = true;
                foreach ($row as $value) {
                    if (trim((string) $value) !== '') {
                        $allFieldsEmpty = false;
                        break;
                    }
                }
                if ($allFieldsEmpty) {
                    continue;
                }

                // تخطي صفوف الوصف
                $firstValue = trim((string) (array_values($row->toArray())[0] ?? ''));
                if (str_contains($firstValue, 'Required') || str_contains($firstValue, 'Optional')) {
                    continue;
                }

                $rowIdentifier = $this->getRowIdentifier($row);

                // تحقق من الحقول الإلزامية
                $missingRequired = false;
                $missingFields = [];
                foreach ($this->requiredColumns as $column) {
                    $value = trim((string) data_get($row, $column, ''));
                    if ($value === '') {
                        $missingRequired = true;
                        $missingFields[] = $column;
                    }
                }
                if ($missingRequired) {
                    $this->unimportableRows[] = $rowIdentifier . ' (Missing: ' . implode(', ', $missingFields) . ')';
                    continue;
                }

                $trackingNo = trim((string) $row['tracking_no']);
                $paymentType = strtolower(trim((string) ($row['payment_type'] ?? '')));

                if ($this->outsourced && str_starts_with(strtoupper($trackingNo), 'PE')) {
                    $this->unimportableRows[] = "$trackingNo (Not allowed for outsourced: starts with PE)";
                    continue;
                }

                // ⭐ تطبيع COD
                $cod = $this->parseCod($row['cod'] ?? null);


                // لو payment_type = cod لكن مفيش قيمة أو صفر → نخليها Paid
                if ($paymentType === 'cod' && (!$cod['present'] || $cod['amount'] <= 0)) {
                    $paymentType = 'paid';
                }

                // لو cod مكتوب لكن غير رقمي → خطأ فعلي
                // if ($paymentType === 'cod' && $cod['present'] && !$cod['valid']) {
                //     $this->unimportableRows[] = "$trackingNo (Invalid COD amount)";
                //     continue;
                // }
                if ($paymentType === 'cod') {
                    if (!$cod['present']) {
                        $paymentType = 'paid';
                    }
                    elseif ($cod['present'] && !$cod['valid']) {
                        $paymentType = 'paid';
                    }
                    elseif ($cod['amount'] <= 0) {
                        $paymentType = 'paid';
                    }
                }

                $recipientCountry = trim((string) $row['recipient_country']);
                $recipientState = trim((string) $row['recipient_state']);
                $recipientCity = trim((string) $row['recipient_city']);

                // تكرار تتبع؟
                if (Shipment::where('tracking_no', $trackingNo)->exists()) {
                    $this->unimportableRows[] = $trackingNo . ' (Duplicate tracking number)';
                    continue;
                }


                $country_channel = CountryChannel::where('shipper_id', $gfs->id)
                    ->where('external_country_name', $recipientCountry)
                    ->first();

                $governorate_channel = GovernorateChannel::where('shipper_id', $gfs->id)
                    ->where('external_governorate_name', $recipientState)
                    ->first();

                $state_channel = StateChannel::where('shipper_id', $gfs->id)
                    ->where('external_state_name', $recipientCity)
                    ->first();

                $missingCountry = !$country_channel;
                $missingGovernorates = !$governorate_channel;
                $missingStates = !$state_channel;

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

                    $this->unimportableRows[] = $trackingNo . ' (Missing channels)';
                    continue;
                }

                // العمولة
                $shipper_commission = ShipperCommission::where('state_id', $state_channel->internal_state_id)->first();
                if (!$shipper_commission) {
                    if (!in_array($state_channel->state->en_name, $this->commissions)) {
                        $this->commissions[] = $state_channel->state->en_name;
                    }
                    $this->unimportableRows[] = $trackingNo . ' (Missing commission)';
                    continue;
                }

                // لو وصلنا هنا يبقى الصف قابل للاستيراد
                $this->importableRows[] = $trackingNo;

            }
        } catch (Exception $e) {
            Log::error('Shipment Import Preview Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getRowIdentifier($row): string
    {
        if (!empty(trim((string) $row['tracking_no']))) {
            return trim((string) $row['tracking_no']);
        }

        if (!empty(trim((string) $row['recipient_name']))) {
            return trim((string) $row['recipient_name']) . ' (Row ' . $this->rowIndex . ')';
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
}
