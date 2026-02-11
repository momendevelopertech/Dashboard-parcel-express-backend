<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\OutsourcedDriverSettlement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DriverPayrollImportPreview;

class OutsourcedDriverSettlementController extends Controller
{
    /**
     * Send OTP to driver phone number (Mocked)
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return sendResponse('Validation Error', [], false, $validator->errors(), 422);
        }

        // Mocking OTP sending
        return sendResponse('OTP sent successfully to ' . $request->phone, ['otp_sent' => true]);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return sendResponse('Validation Error', [], false, $validator->errors(), 422);
        }

        if ($request->otp === '123456') {
            return sendResponse('OTP verified successfully', ['verified' => true]);
        }

        return sendResponse('Invalid OTP', ['verified' => false], false, ['The provided OTP is incorrect'], 400);
    }

    /**
     * Store settlement record
     */
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'amount' => 'required|numeric|min:0',
        'deductions' => 'nullable|numeric|min:0',
    ]);

    if ($validator->fails()) {
        return sendResponse('Validation Error', [], false, $validator->errors(), 422);
    }

    // Find user with matching phone AND driver record
    $user = User::where('phone', $request->phone)
        ->whereHas('driver')
        ->first();


    try {
        $settlement = OutsourcedDriverSettlement::create([
            'name'       => $request->name,
            'phone'      => $request->phone,
            'amount'     => $request->amount,
            'deductions' => $request->deductions,
            'driver_id'  => optional($user?->driver)->id, // ✅ safe
            'user_id'    => $user?->id,                    // ✅ safe
        ]);

        return sendResponse('Settlement record saved successfully', $settlement);

    } catch (\Exception $e) {
        return sendResponse(
            'Error saving settlement',
            [],
            false,
            [$e->getMessage()],
            500
        );
    }
}


    public function import_preview(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:csv,txt,xlsx'
            ]);

            $importer = new DriverPayrollImportPreview;

            try {
                Excel::import($importer, $request->file('file'));
                $import_data = $importer->getPreviewData();
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'Missing columns')) {
                    $missing = str_replace('Missing columns: ', '', $e->getMessage());
                    return sendResponse(
                        "Validation failed",
                        ['missing_columns' => explode(', ', $missing)],
                        true,
                        ['Excel file is missing required columns'],
                        200
                    );
                }
                Log::error('Driver Payroll Import Preview Error: ' . $e->getMessage());
                return sendResponse(
                    "Error processing file",
                    [],
                    false,
                    [$e->getMessage()],
                    422
                );
            }

            $dependencies = [
                'branches' => $import_data['missingBranches'] ?? [],
                'drivers' => $import_data['missingDrivers'] ?? [],
                'currencies' => $import_data['missingCurrencies'] ?? [],
            ];

            // Check if any missing data exists
            $hasMissingData = count($dependencies['branches']) > 0
                || count($dependencies['drivers']) > 0
                || count($dependencies['currencies']) > 0;

            $response_data = [
                'dependencies' => $dependencies,
                'data' => $import_data,
                'has_missing_data' => $hasMissingData,
                'total_rows' => $import_data['totalRows'] ?? 0,
                'total_payable' => $import_data['totalPayable'] ?? 0,
                'total_deductions' => $import_data['totalDeductions'] ?? 0,
                'total_net_pay' => $import_data['totalNetPay'] ?? 0,
            ];

            if ($hasMissingData) {
                return sendResponse(
                    "Import preview generated with missing dependencies",
                    $response_data,
                    true,
                    ["Some branches, drivers, or currencies are missing. Please create them before importing."],
                    200
                );
            }

            return sendResponse(
                "Import preview generated successfully",
                $response_data
            );
        } catch (ValidationException $e) {
            return sendResponse("Validation failed", [], false, $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Driver Payroll Import Preview Error: ' . $e->getMessage());
            return sendResponse("System error occurred", [], false, [$e->getMessage()], 500);
        }
    }

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:csv,txt,xlsx',
                'batch_ref' => 'nullable|string|max:255',
            ]);

            $importer = new DriverPayrollImport($request->batch_ref);

            Excel::import($importer, $request->file('file'));

            $import_data = $importer->getImportData();

            return sendResponse(
                "Driver payroll imported successfully",
                [
                    'imported_count' => $import_data['imported_count'] ?? 0,
                    'failed_count' => $import_data['failed_count'] ?? 0,
                    'errors' => $import_data['errors'] ?? [],
                    'total_amount' => $import_data['total_amount'] ?? 0,
                ]
            );
        } catch (ValidationException $e) {
            return sendResponse("Validation failed", [], false, $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Driver Payroll Import Error: ' . $e->getMessage());
            return sendResponse("System error occurred", [], false, [$e->getMessage()], 500);
        }
    }

}
