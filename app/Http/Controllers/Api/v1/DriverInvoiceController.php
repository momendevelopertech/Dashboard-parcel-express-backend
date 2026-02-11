<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\DriverInvoice;
use App\Exports\DriverInvoiceExport;
use App\Http\Resources\InvoiceResource;
use App\Models\DriverRunsheet;
use App\Models\Invoice;
use App\Models\InvoiceShipment;
use App\Models\Shipment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Arr;
/**
 * @OA\Tag(
 *     name="Other",
 *     description="Driver Invoice Controller"
 * )
 */
class DriverInvoiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/driver_invoices",
     *     summary="Get a list of invoices",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="company_id",
     *         in="query",
     *         description="Filter by company ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Filter by creation date (from)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="Filter by creation date (to)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="facility",
     *         in="query",
     *         description="Filter by facility (JSON: {type: string, id: integer})",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="driver",
     *         in="query",
     *         description="Filter by driver ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="invoice_no", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="total_amount", type="number", format="float"),
     *                     @OA\Property(property="paid_to_driver", type="number", format="float"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="driver", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="company", type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="name", type="string")
     *                         )
     *                     ),
     *                     @OA\Property(property="runsheet", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="delivered_shipments", type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="shipment", type="object",
     *                                     @OA\Property(property="consignee", type="object",
     *                                         @OA\Property(property="state", type="object",
     *                                             @OA\Property(property="shipper_commission", type="number", format="float")
     *                                         )
     *                                     )
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index(Request $request)
    {
        $perPage = request()->query('itemsPerPage', 8);
        $query = Invoice::whereHas('invoiceable.driver');
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->driver) {
            $query->whereHas('invoiceable.driver', function ($q) use ($request) {
                $q->where('user_id', $request->driver);
            });
        }
        $company_id = request()->input('company_id');
        if ($company_id) {
            $query->whereHas('driver.driver', function ($q) use ($company_id) {
                $q->where('company_id', $company_id);
            });
        }
        if ($request->from) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }
        if ($request->user) {
            $query->where('invoiceable_id', $request->user);
        }
        $facility = json_decode($request->facility);
        if ($facility) {
            $query->where('owner_type', $facility->type);
            $query->where('owner_id', $facility->id);
        }
        $invoices = $query->with([
            'invoiceable.driver.company',
            'runsheet.delivered_shipments.shipment.consignee.state.shipper_commission',
            'runsheet.delivered_shipments.shipment.shipment_finance',
            'owner',
            'invoice_shipments.shipment',
            'invoice_shipments.shipment.shipment_finance',
        ])
            ->orderBy('id', 'desc')
            ->paginate($perPage);
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->setAttribute(
                'driver_total_commission',
                $this->calcDriverDueForInvoice($invoice)
            );
            return $invoice;
        });

        return sendResponse("Invoices retrieved successfully.", new InvoiceResource($invoices), []);
    }

    /**
     * @OA\Get(
     *     path="/driver_invoices/invoice_details/{invoice_id}",
     *     summary="Get invoice details",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="invoice_id",
     *         in="path",
     *         description="Invoice ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="searchTerm",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="facility_id",
     *         in="query",
     *         description="Filter by facility ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="facility_type",
     *         in="query",
     *         description="Filter by facility type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="createTimeFrom",
     *         in="query",
     *         description="Filter by creation time (from)",
     *         @OA\Schema(type="string", format="datetime")
     *     ),
     *     @OA\Parameter(
     *         name="createTimeTo",
     *         in="query",
     *         description="Filter by creation time (to)",
     *         @OA\Schema(type="string", format="datetime")
     *     ),
     *     @OA\Parameter(
     *         name="company",
     *         in="query",
     *         description="Filter by company ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="invoice_no", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="total_amount", type="number", format="float"),
     *             @OA\Property(property="paid_to_driver", type="number", format="float"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="driver", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="company", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="runsheet", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="delivered_shipments", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="shipment", type="object",
     *                             @OA\Property(property="consignee", type="object",
     *                                 @OA\Property(property="state", type="object",
     *                                     @OA\Property(property="shipper_commission", type="number", format="float")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function invoice_details($invoice_id, Request $request)
    {
        $invoice = Invoice::where('id', $invoice_id);
        if ($request->searchTerm) {
            $searchTerm = $request->searchTerm;
            $invoice->whereHas('invoice_shipments', function ($query) use ($searchTerm) {
                $query->whereHas('invoice', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('id', 'like', "%{$searchTerm}%");
                });
            });
        }
        if ($request->status) {
            $invoice->where('status', $request->status);
        }
        if ($request->facility_id && $request->facility_type) {
            $invoice->where('owner_id', $request->facility_id);
            $invoice->where('owner_type', $request->facility_type);
        }
        if ($request->createTimeFrom) {
            $createTimeFrom = Carbon::parse(request()->createTimeFrom)->endOfMinute();
            $invoice->where('created_at', '>', $createTimeFrom);
        }
        if ($request->createTimeTo) {
            $createTimeTo = Carbon::parse(request()->createTimeTo)->startOfMinute();
            $invoice->where('created_at', '<', $createTimeTo);
        }
        if ($request->company) {
            $company_id = $request->company;
            $invoice->whereHas('invoice_shipments.invoice.invoiceable.driver.company', function ($q) use ($company_id) {
                $q->where('id', $company_id);
            });
        }
        $invoice = $invoice->with([
            'runsheet.delivered_shipments.shipment.consignee.state.shipper_commission',
            'invoice_shipments.invoice.invoiceable.driver.company',
            'invoice_shipments.invoice.owner',
            'invoice_shipments.invoice.runsheet.submission.received_by',
            'invoice_shipments.shipment_finance',
            'runsheet.delivered_shipments.shipment.shipment_finance',
            'invoice_shipments.shipment',
            'invoice_shipments.shipment.shipment_finance',
        ])->first();
        if (!$invoice) {
            return sendResponse("Invoice not found.", [], false, [], 404);
        }

        $invoice->setAttribute(
            'driver_total_commission',
            $this->calcDriverDueForInvoice($invoice)
        );
        return sendResponse("Invoice.", new InvoiceResource($invoice), []);
    }

    /**
     * @OA\Post(
     *     path="/driver_invoices/confirm_invoice",
     *     summary="Confirm invoice",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="invoice_id", type="integer", description="Invoice ID"),
     *             @OA\Property(property="runsheet_id", type="integer", description="Runsheet ID"),
     *             @OA\Property(property="paid_to_driver", type="number", format="float", description="Amount paid to driver")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice confirmed successfully",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */

    private function calcDriverDueForInvoice(Invoice $invoice): float
    {
        $sum = 0.0;

        // 1) Sum via invoice_shipments -> shipment -> shipment_finance
        if ($invoice->relationLoaded('invoice_shipments')) {
            foreach ($invoice->invoice_shipments as $io) {
                $of = optional($io->shipment)->shipment_finance;   // <— changed
                if ($of) {
                    $delivery = (float) ($of->delivery_driver_commission ?? 0);
                    $pickup = (float) ($of->pickup_driver_commission ?? 0);
                    $bonus = (float) ($of->driver_bonus ?? 0);
                    $penalty = (float) ($of->driver_penalty ?? 0);
                    $sum += max(0, $delivery + $pickup + $bonus - $penalty);
                }
            }
        }

        // 2) Fallback: runsheet -> delivered_shipments -> shipment -> shipment_finance
        if ($sum == 0.0 && $invoice->relationLoaded('runsheet')) {
            foreach (($invoice->runsheet->delivered_shipments ?? collect()) as $row) {
                $of = optional($row->shipment)->shipment_finance;  // <— unchanged but safer
                if ($of) {
                    $delivery = (float) ($of->delivery_driver_commission ?? 0);
                    $pickup = (float) ($of->pickup_driver_commission ?? 0);
                    $bonus = (float) ($of->driver_bonus ?? 0);
                    $penalty = (float) ($of->driver_penalty ?? 0);
                    $sum += max(0, $delivery + $pickup + $bonus - $penalty);
                }
            }
        }

        return round($sum, 3);
    }

    public function confirm_invoice(Request $request)
    {
        $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'runsheet_id' => ['required', 'integer', 'exists:driver_runsheets,id'],
            'paid_to_driver' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $invoice = Invoice::with([
                'runsheet.delivered_shipments.shipment.shipment_finance',
                'invoice_shipments.shipment_finance',
            ])->findOrFail($request->invoice_id);

            // Recompute expected due
            $expected = $this->calcDriverDueForInvoice($invoice);
            $paid = (float) $request->paid_to_driver;

            // If your currency is 3 decimals, use bccomp/round accordingly.
            if (round($paid, 3) !== round($expected, 3)) {
                DB::rollBack();
                return sendResponse(
                    "The amount does not match the driver’s due for this invoice.",
                    ['expected' => $expected, 'received' => $paid],
                    false,
                    ["Expected {$expected}, received {$paid}"],
                    422
                );
            }

            // Mark invoice shipments as paid
            InvoiceShipment::where('invoice_id', $invoice->id)->update(['status' => 'paid']);

            // Update invoice
            $invoice->status = "paid";
            $invoice->paid_to_driver = $paid;
            if ($request->filled('notes')) {
                $invoice->notes = $request->notes; // ensure column exists, otherwise remove
            }
            $invoice->save();

            // Settle runsheet
            DriverRunsheet::where('id', $request->runsheet_id)
                ->update(['status' => 'settled', 'confirmed_at' => now()]);

            DB::commit();
            return sendResponse("Invoice confirmed successfully.", [], []);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    // public function confirm_invoice(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $data = $request->all();
    //         $invoice = Invoice::findOrFail($request->invoice_id);
    //         InvoiceShipment::where('invoice_id', $request->invoice_id)
    //             ->update(['status' => "paid"]);
    //         $invoice->status = "paid";
    //         $invoice->paid_to_driver = $request->paid_to_driver;
    //         $invoice->save();
    //         DriverRunsheet::where('id', $request->runsheet_id)
    //             ->update(['status' => 'settled', 'confirmed_at' => now()]);
    //         DB::commit();
    //         return sendResponse("Invoice confirmed successfully.", [], []);
    //     } catch (Exception $e) {
    //         DB::rollback();
    //         return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
    //     }
    // }
    /**
     * @OA\Get(
     *     path="/driver_invoices/export",
     *     summary="Export invoices",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Export format (csv, pdf)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Selected columns (comma-separated)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter by date (from)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter by date (to)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoices exported successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified",
     *     ),
     *      security={{ "bearerAuth": {} }}
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format specified', [], false, null, 422);
        }
        $availableColumns = [
            'invoice_id',
            'invoice_no',
            'statement_date',
            'runsheet.id',
            'owner.name',
            'consignee.country.name',
            'consignee.governorate.en_name',
            'consignee.state.en_name',
            'consignee.streetAddress',
            'consignee.zipcode',
            'amount',
            'payment_type',
            'shipment_information.weight',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }
        $columns = array_intersect($availableColumns, $selectedColumns);
        $relationships = [];
        foreach ($availableColumns as $column) {
            if (strpos($column, '.') !== false) {
                $parts = explode('.', $column);
                array_pop($parts); // Remove the attribute part
                if (!empty($parts)) {
                    $relationships[] = implode('.', $parts);
                }
            }
        }

        $query = Shipment::with(array_unique($relationships));
        info($query->get());
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }
        $shipments = $query->get();
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.shipments', [
                'shipments' => $shipments,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ]);
            return $pdf->download('shipments_' . now()->format('Y-m-d') . '.pdf');
        }
        $name = 'shipments.' . $format;
        info($shipments);
        return Excel::download(new DriverInvoiceExport($shipments, $columns), $name);
    }

    public function exportRunsheets(Request $request)
    {
        $request->validate([
            'driver_id' => 'nullable|exists:users,id',
            'company_id' => 'nullable|exists:companies,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'create_date_start' => 'nullable|date',
            'create_date_end' => 'nullable|date',
            'confirm_date_start' => 'nullable|date',
            'confirm_date_end' => 'nullable|date',
            'manifest_id' => 'nullable|integer',
            'status' => 'nullable|in:pending,holding,settled',
            'format' => 'required|in:csv,xlsx',
            'columns' => 'nullable|array'
        ]);

        try {
            $status = $request->input('status', 'pending');
            $query = DriverRunsheet::where('status', $status);

            // Apply filters
            if ($request->driver_id) {
                $query->where('driver_id', $request->driver_id);
            }

            if ($request->company_id) {
                $query->whereHas('driver.driver', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
                });
            }

            if ($request->start_date) {
                $query->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
            }

            if ($request->end_date) {
                $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
            }

            if ($request->create_date_start) {
                $query->where('created_at', '>=', Carbon::parse($request->create_date_start)->startOfDay());
            }

            if ($request->create_date_end) {
                $query->where('created_at', '<=', Carbon::parse($request->create_date_end)->endOfDay());
            }

            if ($request->confirm_date_start) {
                $query->where('confirmed_at', '>=', Carbon::parse($request->confirm_date_start)->startOfDay());
            }

            if ($request->confirm_date_end) {
                $query->where('confirmed_at', '<=', Carbon::parse($request->confirm_date_end)->endOfDay());
            }

            if ($request->manifest_id) {
                $query->where('id', $request->manifest_id);
            }

            // Get runsheets with relationships and counts
            $runsheets = $query->with([
                'driver.driver.company',
                'assigned_shipments.shipment.consignee',
                'delivered_shipments.shipment.consignee',
                'not_delivered_shipments.shipment.consignee',
                'returned_shipments.shipment.consignee',
                'holding_shipments.shipment.consignee',
                'difference_shipments.shipment.consignee',
                'submission.receivedBy'
            ])->withCount([
                        'assigned_shipments',
                        'delivered_shipments',
                        'not_delivered_shipments',
                        'returned_shipments',
                        'holding_shipments',
                        'difference_shipments',
                    ])->orderBy('id', 'desc')->get();

            $format = $request->input('format', 'csv');
            $columns = $request->input('columns');

            $filename = 'cod_collection_runsheets_' . now()->format('Y_m_d_H_i_s');

            if ($format === 'csv') {
                return Excel::download(
                    new DriverInvoiceExport($runsheets, $columns),
                    $filename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            } else {
                return Excel::download(
                    new DriverInvoiceExport($runsheets, $columns),
                    $filename . '.xlsx'
                );
            }

        } catch (Exception $e) {
            Log::error('COD Collection Export Error: ' . $e->getMessage());
            return sendResponse("Error occurred during export.", [], false, [$e->getMessage()], 500);
        }
    }
}
