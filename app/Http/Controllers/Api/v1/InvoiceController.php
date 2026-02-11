<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\CompanyResource;
use App\Http\Resources\GeneralResource;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Driver;
use App\Models\InvoiceShipment;
use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Exports\DriverInvoiceExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Other", description="Invoice Management")
 * @OA\Controller(description="Invoice related operations")
 */
class InvoiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/invoices/driver_paid_invoices/{id}",
     *     summary="Get paid invoices for a driver",
     *     description="Retrieves a paginated list of paid invoices for a specific driver.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function driver_paid_invoices($id)
    {
        $driver = User::find($id);
        $invoices = Invoice::with(['invoice_shipments.shipment'])
            ->where('invoiceable_type', User::class)
            ->where('invoiceable_id', $driver->id)
            ->where('status', 'settled')
            ->with('invoice_shipments')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return sendResponse("Invoices reterived successfully.", new GeneralResource(["user" => $driver, "invoices" => $invoices]), []);
    }

    /**
     * @OA\Get(
     *     path="/invoices/print_pending_driver_invoice/{id}",
     *     summary="Print pending driver invoice",
     *     description="Retrieves the HTML content for printing a pending driver invoice.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice HTML retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invoice is not available",
     *     )
     * )
     */
    public function print_pending_driver_invoice($id)
    {
        $driver = User::find($id);
        $invoice = Invoice::with([
            'invoice_shipments.shipment',
            'invoice_shipments.shipment_finance',
            'runsheet'
        ])
            ->where('invoiceable_type', User::class)
            ->where('invoiceable_id', $driver->id)
            ->first();
        if (!$invoice) {
            return sendResponse("", [], false, ["Invoice is not available"], 422);
        }
        $data['invoice'] = $invoice;
        $data['user'] = $driver;
        $html = view('printInvoice', compact('data'))->render();
        return response()->json(['html' => $html]);
    }

    /**
     * @OA\Get(
     *     path="/invoices/pending_user_invoice/{id}",
     *     summary="Get pending invoice for a user",
     *     description="Retrieves the pending invoice for a specific user.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the user",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while retrieving invoice",
     *     )
     * )
     */
    public function pending_user_invoice($id)
    {
        try {
            $user = User::find($id);
            $invoice = Invoice::with([
                'invoiceable.driver.company',
                'invoice_shipments.shipment',
                'invoice_shipments.shipment_finance',
                'runsheet'
            ])
                ->where('invoiceable_type', User::class)
                ->where('invoiceable_id', $user->id)
                ->where('status', 'pending')
                ->first();
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating expense.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Invoices reterived successfully.", new GeneralResource(["user" => $user, "invoice" => $invoice]), []);
    }

    /**
     * @OA\Get(
     *     path="/invoices/completed_user_invoice/{id}",
     *     summary="Get pending invoice for a user",
     *     description="Retrieves the pending invoice for a specific user.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the user",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while retrieving invoice",
     *     )
     * )
     */
    public function completed_user_invoice($id)
    {
        try {
            $user = User::find($id);
            $invoice = Invoice::with([
                'invoiceable.driver.company',
                'invoice_shipments.shipment',
                'invoice_shipments.shipment_finance',
                'runsheet'
            ])
                ->where('invoiceable_type', User::class)
                ->where('invoiceable_id', $user->id)
                ->where('status', 'completed')
                ->first();
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating expense.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Invoices reterived successfully.", new GeneralResource(["user" => $user, "invoice" => $invoice]), []);
    }

    /**
     * @OA\Get(
     *     path="/invoices/pending_company_driver_invoices/{company_id}",
     *     summary="Get pending invoices for company drivers",
     *     description="Retrieves all pending invoices for drivers associated with a specific company.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="path",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pending invoices retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while retrieving pending invoices",
     *     )
     * )
     */
    public function pending_company_driver_invoices($company_id)
    {
        try {
            $company = Company::findOrFail($company_id);
            $driverUserIds = $company->drivers()->pluck('user_id')->toArray();
            $invoices = Invoice::with([
                'invoice_shipments.shipment',
                'invoice_shipments.shipment_finance',
                'invoiceable',
                'runsheet'
            ])
                ->where('invoiceable_type', User::class)
                ->whereIn('invoiceable_id', $driverUserIds)
                ->where('status', 'pending')
                ->get();
        } catch (QueryException $e) {
            return sendResponse("Error occurred while retrieving pending invoices.", [], [$e->getMessage()], 422);
        }

        return sendResponse(
            "Pending invoices retrieved successfully.",
            new CompanyResource(["company" => $company, "invoices" => $invoices]),
            []
        );
    }
    /**
     * @OA\Post(
     *     path="/invoices/settle_driver_invoice",
     *     summary="Settle driver invoice",
     *     description="Settles a driver's invoice and processes the payment.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the invoice"),
     *             @OA\Property(property="status", type="string", description="Status of the invoice (paid)"),
     *             @OA\Property(property="driver_payment_proof", type="file", description="Driver payment proof")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice status updated and payment processed.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred.",
     *     )
     * )
     */
    public function settle_driver_invoice(Request $request)
    {
        DB::beginTransaction();
        try {
            $invoice = Invoice::findOrFail($request->id);
            $originalStatus = $invoice->status;
            if ($originalStatus === 'pending' && $request->status === 'paid') {
                $cashier = Auth::user();
                $pdf = Pdf::loadView('vouchers.paymentVoucher', [
                    'payment' => (object) [
                        'id' => $invoice->id,
                        'amount' => $invoice->paid_amount,
                        'paid_at' => now(),
                        'method' => $invoice->payment_method
                    ],
                    'driver' => $invoice->invoiceable,
                    'cashier' => $cashier
                ]);
                $filename = "payment-voucher-{$invoice->id}-" . now()->timestamp . ".pdf";
                $path = "vouchers/{$filename}";
                Storage::disk('public')->put($path, $pdf->output());
                $invoice->update([
                    'payment_voucher' => "storage/" . $path,
                    'driver_payment_proof' => uploadFile($request->driver_payment_proof, 'public/driver_payment_proofs'),
                ]);
            }
            $invoiceShipments = InvoiceShipment::where('invoice_id', $request->id)->get();
            $user = Auth::user();
            $facilityAccount = Account::where('accountable_id', $user->owner_id)
                ->where('accountable_type', $user->owner_type)
                ->firstOrFail();
            $totalAmount = $invoiceShipments->sum(function ($shipment) {
                return $shipment->shipment_finance->driver_delivery_fee;
            });
            $facilityAccount->cash_balance -= $totalAmount;
            $facilityAccount->save();
            InvoiceShipment::where('invoice_id', $request->id)->update(['status' => 'paid']);
            $invoice->update(['status' => "settled", 'amount' => $totalAmount]);
            $invoice->save();
            DB::commit();
            return sendResponse("Invoice status updated and payment processed.", [
                'voucher_url' => $request->status === 'paid' ? Storage::url($path) : null
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/invoices/drivers/{status}",
     *     summary="Get driver invoices by status",
     *     description="Retrieves a list of driver invoices based on the provided status.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the invoices (e.g., pending, settled)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver invoices retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function index_driver_invoices($status)
    {
        $drivers = Invoice::where('status', $status)
            ->where('invoiceable_type', User::class)
            ->whereHas('invoiceable.driver')
            ->with('invoiceable.driver.company')
            ->get()
            ->unique('id');
        return sendResponse("Driver invoices retrieved successfully.", $drivers, []);
    }

    /**
     * @OA\Get(
     *     path="/invoices/merchants/{status}",
     *     summary="Get merchant invoices by status",
     *     description="Retrieves a list of merchant invoices based on the provided status.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the invoices (e.g., pending, settled)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant invoices retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function index_merchant_invoices($status)
    {
        $drivers = Invoice::where('status', $status)
            ->where('invoiceable_type', User::class)
            ->whereHas('invoiceable.merchant')
            ->with('invoiceable')
            ->get()
            ->unique('id');
        return sendResponse("Merchant invoices retrieved successfully.", $drivers, []);
    }

    /**
     * @OA\Get(
     *     path="/invoices/{status}/{driver_id?}",
     *     summary="Get driver invoices",
     *     description="Retrieves a paginated list of driver invoices based on status and optional driver ID.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the invoices (e.g., pending, settled)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="Optional ID of the driver",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function driver_index_invoices($status, $driver_id = null)
    {
        $status = $status ?? "pending";
        $query = Invoice::where('status', $status)
            ->where('invoiceable_type', User::class);
        if ($driver_id) {
            $query->where('invoiceable_id', $driver_id);
        } else {
            $query->whereHas('invoiceable.driver');
        }
        $invoices = $query->with([
            'invoiceable.driver.company',
            'invoice_shipments.shipment.consignee',
            'runsheet',
            'invoice_shipments.shipment.shipment_finance'
        ])->withCount([
                    'invoice_shipments'
                ])->orderBy('id', 'desc')->paginate(8);
        return sendResponse("Invoices retrieved successfully.", new GeneralResource($invoices), []);
    }

    /**
     * @OA\Get(
     *     path="/invoices/{status}/{merchant_id?}",
     *     summary="Get merchant invoices",
     *     description="Retrieves a paginated list of merchant invoices based on status and optional merchant ID.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the invoices (e.g., pending, settled)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="merchant_id",
     *         in="path",
     *         description="Optional ID of the merchant",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function merchant_index_invoices($status, $merchant = null)
    {
        $status = $status ?? "pending";
        $query = Invoice::where('status', $status)
            ->where('invoiceable_type', User::class);
        if ($merchant) {
            $query->where('invoiceable_id', $merchant);
        } else {
            $query->whereHas('invoiceable.merchant');
        }
        $invoices = $query->with([
            'invoice_shipments.shipment.consignee',
            'runsheet'
        ])->withCount([
                    'invoice_shipments'
                ])->orderBy('id', 'desc')->paginate(8);
        return sendResponse("Invoices retrieved successfully.", new GeneralResource($invoices), []);
    }
    /**
     * @OA\Post(
     *     path="/invoices/confirm_company_driver_invoices/{company_id}",
     *     summary="Confirm company driver invoices",
     *     description="Confirms all pending invoices for drivers associated with a specific company.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="path",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All invoices are confirmed",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while confirming invoices",
     *     )
     * )
     */
    public function confirm_company_driver_invoices(Request $request, $company_id)
    {
        DB::beginTransaction();
        try {
            $company = Company::findOrFail($company_id);
            $driverUserIds = $company->drivers()->pluck('user_id')->toArray();
            $invoices = Invoice::with([
                'invoice_shipments.shipment',
                'invoice_shipments.shipment_finance',
                'runsheet'
            ])
                ->where('invoiceable_type', User::class)
                ->whereIn('invoiceable_id', $driverUserIds)
                ->where('status', 'pending')
                ->get();
            $cashier = Auth::user();
            foreach ($invoices as $invoice) {
                $totalAmount = $invoice->invoice_shipments->sum(function ($shipment) {
                    return $shipment->shipment_finance->driver_delivery_fee ?? 0;
                });
                $pdf = Pdf::loadView('vouchers.paymentVoucher', [
                    'payment' => (object) [
                        'id' => $invoice->id,
                        'amount' => $totalAmount,
                        'paid_at' => now(),
                        'method' => $invoice->payment_method ?? 'default'
                    ],
                    'driver' => $invoice->invoiceable,
                    'cashier' => $cashier
                ]);
                $filename = "payment-voucher-{$invoice->id}-" . now()->timestamp . ".pdf";
                $path = "vouchers/{$filename}";
                Storage::disk('public')->put($path, $pdf->output());
                $invoice->update([
                    'status' => 'paid',
                    'amount' => $totalAmount,
                    'payment_voucher' => "storage/{$path}"
                ]);
                InvoiceShipment::where('invoice_id', $invoice->id)->update(['status' => 'paid']);
                $facilityAccount = Account::where('accountable_id', $cashier->owner_id)
                    ->where('accountable_type', $cashier->owner_type)
                    ->first();
                if ($facilityAccount) {
                    $facilityAccount->cash_balance -= $totalAmount;
                    $facilityAccount->save();
                }
            }
            DB::commit();
            return sendResponse("All invoices are confirmed.", []);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred while confirming invoices.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/invoices/bulk_settle_driver_invoices",
     *     summary="Bulk settle driver invoices",
     *     description="Settles multiple driver invoices in bulk.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="invoice_ids", type="array", description="Array of invoice IDs to settle", @OA\Items(type="integer")),
     *             @OA\Property(property="status", type="string", description="Status to set (e.g., paid)", default="paid"),
     *             @OA\Property(property="driver_payment_proof", type="file", description="Driver payment proof for all invoices")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices settled successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred.",
     *     )
     * )
     */
    public function bulk_settle_driver_invoices(Request $request)
    {
        DB::beginTransaction();
        try {
            $invoiceIds = $request->invoice_ids;
            $status = $request->status;
            $driverPaymentProof = $request->driver_payment_proof;

            $invoices = Invoice::whereIn('id', $invoiceIds)->get();

            foreach ($invoices as $invoice) {
                $originalStatus = $invoice->status;
                if ($originalStatus === 'pending' && $status === 'paid') {
                    $cashier = Auth::user();
                    $pdf = Pdf::loadView('vouchers.paymentVoucher', [
                        'payment' => (object) [
                            'id' => $invoice->id,
                            'amount' => $invoice->paid_amount,
                            'paid_at' => now(),
                            'method' => $invoice->payment_method
                        ],
                        'driver' => $invoice->invoiceable,
                        'cashier' => $cashier
                    ]);
                    $filename = "payment-voucher-{$invoice->id}-" . now()->timestamp . ".pdf";
                    $path = "vouchers/{$filename}";
                    Storage::disk('public')->put($path, $pdf->output());
                    $invoice->update([
                        'payment_voucher' => "storage/" . $path,
                        'driver_payment_proof' => uploadFile($driverPaymentProof, 'public/driver_payment_proofs'),
                    ]);
                }
                $invoiceShipments = InvoiceShipment::where('invoice_id', $invoice->id)->get();
                $user = Auth::user();
                $facilityAccount = Account::where('accountable_id', $user->owner_id)
                    ->where('accountable_type', $user->owner_type)
                    ->firstOrFail();
                $totalAmount = $invoiceShipments->sum(function ($shipment) {
                    return $shipment->shipment_finance->driver_delivery_fee;
                });
                $facilityAccount->cash_balance -= $totalAmount;
                $facilityAccount->save();
                InvoiceShipment::where('invoice_id', $invoice->id)->update(['status' => 'paid']);
                $invoice->update(['status' => "settled", 'amount' => $totalAmount]);
                $invoice->save();
            }
            DB::commit();
            return sendResponse("Invoices settled successfully.", []);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/invoices/bulk_confirm_company_driver_invoices/{company_id}",
     *     summary="Bulk confirm company driver invoices",
     *     description="Confirms multiple pending invoices for drivers associated with a specific company in bulk.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="company_id",
     *         in="path",
     *         description="ID of the company",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="invoice_ids", type="array", description="Array of invoice IDs to confirm", @OA\Items(type="integer")),
     *             @OA\Property(property="status", type="string", description="Status to set (e.g., paid)", default="paid"),
     *             @OA\Property(property="driver_payment_proof", type="file", description="Driver payment proof for all invoices")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices confirmed successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred.",
     *     )
     * )
     */
    public function bulk_confirm_company_driver_invoices(Request $request, $company_id)
    {
        DB::beginTransaction();
        try {
            $invoiceIds = $request->invoice_ids;
            $status = $request->status;
            $driverPaymentProof = $request->driver_payment_proof;

            $company = Company::findOrFail($company_id);
            $driverUserIds = $company->drivers()->pluck('user_id')->toArray();

            $invoices = Invoice::with([
                'invoice_shipments.shipment',
                'invoice_shipments.shipment_finance',
                'runsheet'
            ])
                ->where('invoiceable_type', User::class)
                ->whereIn('invoiceable_id', $driverUserIds)
                ->whereIn('id', $invoiceIds)
                ->where('status', 'pending')
                ->get();

            $cashier = Auth::user();
            foreach ($invoices as $invoice) {
                $totalAmount = $invoice->invoice_shipments->sum(function ($shipment) {
                    return $shipment->shipment_finance->driver_delivery_fee ?? 0;
                });
                $pdf = Pdf::loadView('vouchers.paymentVoucher', [
                    'payment' => (object) [
                        'id' => $invoice->id,
                        'amount' => $totalAmount,
                        'paid_at' => now(),
                        'method' => $invoice->payment_method ?? 'default'
                    ],
                    'driver' => $invoice->invoiceable,
                    'cashier' => $cashier
                ]);
                $filename = "payment-voucher-{$invoice->id}-" . now()->timestamp . ".pdf";
                $path = "vouchers/{$filename}";
                Storage::disk('public')->put($path, $pdf->output());
                $invoice->update([
                    'status' => $status,
                    'amount' => $totalAmount,
                    'payment_voucher' => "storage/{$path}"
                ]);
                InvoiceShipment::where('invoice_id', $invoice->id)->update(['status' => $status]);
                $facilityAccount = Account::where('accountable_id', $cashier->owner_id)
                    ->where('accountable_type', $cashier->owner_type)
                    ->first();
                if ($facilityAccount) {
                    $facilityAccount->cash_balance -= $totalAmount;
                    $facilityAccount->save();
                }
            }
            DB::commit();
            return sendResponse("Invoices confirmed successfully.", []);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred while confirming invoices.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/invoices/bulk_pay",
     *     summary="Bulk pay invoices",
     *     description="Pays multiple invoices in bulk.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="invoice_ids", type="array", description="Array of invoice IDs to pay", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices paid successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred.",
     *     )
     * )
     */
    public function bulk_pay(Request $request)
    {
        DB::beginTransaction();
        try {
            $invoiceIds = $request->invoice_ids;

            $invoices = Invoice::whereIn('id', $invoiceIds)->get();

            foreach ($invoices as $invoice) {
                $invoice->update(['status' => 'paid']);
                InvoiceShipment::where('invoice_id', $invoice->id)->update(['status' => 'paid']);
            }

            DB::commit();
            return sendResponse("Invoices paid successfully.", []);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred while paying invoices.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/invoices/change_status",
     *     summary="Change invoice status",
     *     description="Changes the status of multiple invoices.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="invoice_ids", type="array", description="Array of invoice IDs", @OA\Items(type="integer")),
     *             @OA\Property(property="status", type="string", description="New status to set")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice status updated successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred.",
     *     )
     * )
     */
    public function change_status(Request $request)
    {
        DB::beginTransaction();
        try {
            $invoiceIds = $request->invoice_ids;
            $status = $request->status;

            $invoices = Invoice::whereIn('id', $invoiceIds)->get();

            foreach ($invoices as $invoice) {
                $invoice->update(['status' => $status]);
                InvoiceShipment::where('invoice_id', $invoice->id)->update(['status' => $status]);
            }

            DB::commit();
            return sendResponse("Invoice status updated successfully.", []);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred while updating status.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/invoices/export/{status}",
     *     summary="Export driver invoices with filters",
     *     description="Exports driver invoices to Excel with filtering options.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the invoices (e.g., pending, settled)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="company", type="integer", description="Company ID filter"),
     *             @OA\Property(property="hub", type="integer", description="Hub ID filter"),
     *             @OA\Property(property="driver", type="integer", description="Driver ID filter"),
     *             @OA\Property(property="dateFrom", type="string", format="date", description="Date from filter"),
     *             @OA\Property(property="dateTo", type="string", format="date", description="Date to filter"),
     *             @OA\Property(property="format", type="string", enum={"csv", "xlsx"}, description="Export format", default="xlsx"),
     *             @OA\Property(property="columns", type="array", @OA\Items(type="string"), description="Columns to export")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File downloaded successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred.",
     *     )
     * )
     */
    public function export(Request $request, $status = null)
    {
        $request->validate([
            'company' => 'nullable|exists:companies,id',
            'hub' => 'nullable|integer',
            'driver' => 'nullable|exists:users,id',
            'dateFrom' => 'nullable|date',
            'dateTo' => 'nullable|date',
            'format' => 'nullable|in:csv,xlsx',
            'columns' => 'nullable|array'
        ]);

        try {
            $query = Invoice::when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
                ->where('invoiceable_type', User::class)
                ->whereHas('invoiceable.driver');

            // Apply filters
            if ($request->company) {
                $query->whereHas('invoiceable.driver', function ($q) use ($request) {
                    $q->where('company_id', $request->company);
                });
            }

            if ($request->hub) {
                $query->where('owner_id', $request->hub);
            }

            if ($request->driver) {
                $query->where('invoiceable_id', $request->driver);
            }

            if ($request->dateFrom) {
                $query->where('created_at', '>=', Carbon::parse($request->dateFrom)->startOfDay());
            }

            if ($request->dateTo) {
                $query->where('created_at', '<=', Carbon::parse($request->dateTo)->endOfDay());
            }

            // Get invoices with relationships
            $invoices = $query->with([
                'invoiceable.driver.company',
                'runsheet',
                'owner'
            ])->orderBy('id', 'desc')->get();

            $format = $request->input('format', 'xlsx');
            $columns = $request->input('columns');

            $filename = 'driver_invoices_' . $status . '_' . now()->format('Y_m_d_H_i_s');

            if ($format === 'csv') {
                return Excel::download(
                    new DriverInvoiceExport($invoices, $columns),
                    $filename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            } else {
                return Excel::download(
                    new DriverInvoiceExport($invoices, $columns),
                    $filename . '.xlsx'
                );
            }

        } catch (Exception $e) {
            Log::error('Driver Invoice Export Error: ' . $e->getMessage());
            return sendResponse("Error occurred during export.", [], false, [$e->getMessage()], 500);
        }
    }
}
