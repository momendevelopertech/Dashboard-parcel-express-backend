<?php

namespace App\Imports;

use Exception;
use App\Models\Branch;
use App\Models\Driver;
use App\Models\DriverPayroll;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DriverPayrollImportPreview implements ToCollection, WithHeadingRow
{
    private $unimportableRows;
    private $importableRows;
    private $missingBranches;
    private $missingDrivers;
    private $invalidCurrencies;
    private $rowIndex;
    private $totalPayable;
    private $totalDeductions;
    private $totalNetPay;

    public function __construct()
    {
        $this->unimportableRows = [];
        $this->importableRows = [];
        $this->missingBranches = [];
        $this->missingDrivers = [];
        $this->invalidCurrencies = [];
        $this->rowIndex = 1;
        $this->totalPayable = 0;
        $this->totalDeductions = 0;
        $this->totalNetPay = 0;
    }

    protected $requiredColumns = [
        'branchname',
        'branchcode',
        'drivercode',
        'drivername',
        'driverphone',
        'nationalid',
        'payableamount',
        'deductions',
        'netpay',
        'currency',
        'payoutdate',
    ];

    protected $optionalColumns = [
        'penaltynotes',
        'batchref',
        'voucherref',
        'notes',
    ];

    // Valid currencies list
    private $validCurrencies = ['OMR', 'USD', 'EUR', 'AED', 'SAR', 'KWD', 'BHD', 'QAR'];

    private function parseAmount($raw): array
    {
        if ($raw === null) {
            return ['present' => false, 'amount' => 0.0, 'raw' => null, 'valid' => true];
        }

        $rawStr = trim((string) $raw);
        if ($rawStr === '') {
            return ['present' => false, 'amount' => 0.0, 'raw' => '', 'valid' => true];
        }

        // Remove currency symbols and commas
        $clean = preg_replace('/[^\d.-]/', '', $rawStr);

        // If no digits after cleaning → invalid
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

            foreach ($collection as $row) {
                $this->rowIndex++;

                // Skip empty rows
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

                // Skip description rows
                $firstValue = trim((string) (array_values($row->toArray())[0] ?? ''));
                if (str_contains($firstValue, 'Required') || str_contains($firstValue, 'Optional')) {
                    continue;
                }

                $rowIdentifier = $this->getRowIdentifier($row);

                // Check required fields
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

                $driverCode = trim((string) $row['drivercode']);
                $branchCode = trim((string) $row['branchcode']);
                $branchName = trim((string) $row['branchname']);
                $driverName = trim((string) $row['drivername']);
                $driverPhone = trim((string) $row['driverphone']);
                $nationalId = trim((string) $row['nationalid']);
                $currency = strtoupper(trim((string) $row['currency']));

                // Parse amounts
                $payableAmount = $this->parseAmount($row['payableamount'] ?? null);
                $deductions = $this->parseAmount($row['deductions'] ?? null);
                $netPay = $this->parseAmount($row['netpay'] ?? null);

                // Validate amounts
                if (!$payableAmount['valid'] || !$payableAmount['present']) {
                    $this->unimportableRows[] = "$rowIdentifier (Invalid payable amount)";
                    continue;
                }

                if (!$deductions['valid']) {
                    $this->unimportableRows[] = "$rowIdentifier (Invalid deductions amount)";
                    continue;
                }

                if (!$netPay['valid'] || !$netPay['present']) {
                    $this->unimportableRows[] = "$rowIdentifier (Invalid net pay amount)";
                    continue;
                }

                // Validate calculation: NetPay = PayableAmount - Deductions
                $calculatedNetPay = $payableAmount['amount'] - ($deductions['present'] ? $deductions['amount'] : 0);
                if (abs($calculatedNetPay - $netPay['amount']) > 0.01) {
                    $this->unimportableRows[] = "$rowIdentifier (Net pay mismatch: Expected " . number_format($calculatedNetPay, 2) . ", Got " . number_format($netPay['amount'], 2) . ")";
                    continue;
                }

                // Validate currency
                if (!in_array($currency, $this->validCurrencies)) {
                    if (!in_array($currency, $this->invalidCurrencies)) {
                        $this->invalidCurrencies[] = $currency;
                    }
                    $this->unimportableRows[] = "$rowIdentifier (Invalid currency: $currency)";
                    continue;
                }

                // Validate payout date
                try {
                    $payoutDate = \Carbon\Carbon::parse($row['payoutdate']);
                } catch (\Exception $e) {
                    $this->unimportableRows[] = "$rowIdentifier (Invalid payout date)";
                    continue;
                }



                // Check if branch exists
                // $branch = Branch::where('code', $branchCode)
                //     ->orWhere('name', $branchName)
                //     ->first();

                // if (!$branch) {
                //     if (!in_array("$branchName ($branchCode)", $this->missingBranches)) {
                //         $this->missingBranches[] = "$branchName ($branchCode)";
                //     }
                //     $this->unimportableRows[] = "$rowIdentifier (Branch not found: $branchName)";
                //     continue;
                // }

                // // Check if driver exists
                // $driver = Driver::where('code', $driverCode)
                //     ->orWhere('phone', $driverPhone)
                //     ->orWhere('national_id', $nationalId)
                //     ->first();

                // if (!$driver) {
                //     if (!in_array("$driverName ($driverCode)", $this->missingDrivers)) {
                //         $this->missingDrivers[] = "$driverName ($driverCode)";
                //     }
                //     $this->unimportableRows[] = "$rowIdentifier (Driver not found: $driverName)";
                //     continue;
                // }

                // If we reached here, the row is importable
                $this->importableRows[] = $rowIdentifier;
                
                // Add to totals
                $this->totalPayable += $payableAmount['amount'];
                $this->totalDeductions += ($deductions['present'] ? $deductions['amount'] : 0);
                $this->totalNetPay += $netPay['amount'];
            }
        } catch (Exception $e) {
            Log::error('Driver Payroll Import Preview Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getRowIdentifier($row): string
    {
        if (!empty(trim((string) ($row['drivercode'] ?? '')))) {
            return trim((string) $row['drivercode']);
        }

        if (!empty(trim((string) ($row['drivername'] ?? '')))) {
            return trim((string) $row['drivername']) . ' (Row ' . $this->rowIndex . ')';
        }

        if (!empty(trim((string) ($row['voucherref'] ?? '')))) {
            return trim((string) $row['voucherref']) . ' (Row ' . $this->rowIndex . ')';
        }

        return 'Row ' . $this->rowIndex;
    }

    public function getPreviewData(): array
    {
        return [
            'importablePayrolls' => $this->importableRows,
            'unimportablePayrolls' => $this->unimportableRows,
            'missingBranches' => array_unique($this->missingBranches),
            'missingDrivers' => array_unique($this->missingDrivers),
            'invalidCurrencies' => array_unique($this->invalidCurrencies),
            'totalRows' => count($this->importableRows) + count($this->unimportableRows),
            'importableCount' => count($this->importableRows),
            'unimportableCount' => count($this->unimportableRows),
            'totalPayable' => $this->totalPayable,
            'totalDeductions' => $this->totalDeductions,
            'totalNetPay' => $this->totalNetPay,
        ];
    }
}