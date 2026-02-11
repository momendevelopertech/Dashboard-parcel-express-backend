<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\MerchantInvoiceResource;
use App\Models\Merchant;
use App\Models\Invoice;
use App\Models\User;
use App\Traits\Searchable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * @OA\Tag(name="Merchant", description="Merchant APIs")
 */
class MerchantInvoiceController extends Controller
{
    use Searchable;
    protected function modelQuery()
    {
        return Invoice::query()
            ->where('invoiceable_type', User::class)
            ->select('id', 'invoiceable_id', 'invoiceable_type', 'invoice_no', 'status', 'amount', 'created_at', 'updated_at', 'notes');
    }
    /**
     * Get Merchant Invoices List
     *
     * @OA\Get(
     *     path="/merchant/invoices/{merchant_id}",
     *     summary="Get paginated list of merchant invoices",
     *     description="
     * Retrieve paginated list of invoices for a specific merchant with search capabilities.
     * 
     * **Features:**
     * - Pagination support
     * - Search functionality
     * - Sorting by latest
     * - Related data included
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data
     * ",
     *     operationId="getMerchantInvoices",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="merchant_id",
     *         in="path",
     *         description="Merchant ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in invoice number, status, or notes",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant invoices retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant invoices retrieved successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function index(Request $request, $merchant_id)
    {
        try {
            $invoices = $this->handleSearch(
                searchColumns: ['invoice_no', 'status', 'notes'],
                withRelationships: [
                    'owner:id,contact_no,address',
                    'invoice_shipments:id,invoice_id,shipment_tracking_no,status'
                ],
                perPage: $request->input('per_page', 10),
                shipmentColumn: 'created_at',
                shipmentDirection: 'desc'
            );
            $invoices = $invoices->where('invoiceable_id', $merchant_id);
            return sendResponse("Merchant invoices retrieved successfully.", new MerchantInvoiceResource($invoices));
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
    }
    /**
     * Get Merchant Invoice Details
     *
     * @OA\Get(
     *     path="/merchant/invoices/{merchant_id}/{invoice_id}",
     *     summary="Get specific merchant invoice details",
     *     description="
     * Retrieve detailed information for a specific merchant invoice including related shipment data.
     * 
     * **Features:**
     * - Complete invoice details
     * - Related shipments included
     * - Merchant information
     * - Shipment status tracking
     * 
     * **Security:**
     * - Merchant authentication required
     * - Invoice ownership verified
     * ",
     *     operationId="getMerchantInvoiceDetails",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="merchant_id",
     *         in="path",
     *         description="Merchant ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="invoice_id",
     *         in="path",
     *         description="Invoice ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant invoice retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchant invoice retrieved successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function show($merchant_id, $id)
    {
        try {
            $invoice = Invoice::where('invoiceable_type', User::class)
                ->with([
                    'invoiceable:id,name,email',
                    'invoice_shipments:id,invoice_id,shipment_tracking_no,status'
                ])
                ->where('invoiceable_id', $merchant_id)
                ->find($id);
            if (!$invoice) {
                return sendResponse("Invoice not found.", [], ["Invoice not found"], 404);
            }
            return sendResponse("Merchant invoice retrieved successfully.", $invoice);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
    }
    /**
     * Download Merchant Invoice PDF
     *
     * @OA\Get(
     *     path="/merchant/invoices/{merchant_id}/{invoice_id}/download",
     *     summary="Download merchant invoice as PDF",
     *     description="
     * Generate and download a PDF version of the specified merchant invoice.
     * 
     * **Features:**
     * - PDF generation
     * - Professional formatting
     * - Complete invoice data
     * - Automatic filename
     * 
     * **Security:**
     * - Merchant authentication required
     * - Invoice ownership verified
     * ",
     *     operationId="downloadMerchantInvoicePDF",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="merchant_id",
     *         in="path",
     *         description="Merchant ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="invoice_id",
     *         in="path",
     *         description="Invoice ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice PDF downloaded successfully",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function download($merchant_id, $id)
    {
        try {
            $invoice = Invoice::where('invoiceable_type', User::class)
                ->with([
                    'invoiceable:id,name,email',
                    'invoice_shipments:id,invoice_id,shipment_tracking_no,status,created_at'
                ])
                ->where('invoiceable_id', $merchant_id)
                ->find($id);
            if (!$invoice) {
                return sendResponse("Invoice not found.", [], ["Invoice not found"], 404);
            }
            $pdf = Pdf::loadView('invoices.merchant_invoice', [
                'invoice' => $invoice,
                'date' => now()->format('Y-m-d H:i:s')
            ]);
            $filename = 'merchant_invoice_' . $invoice->invoice_no . '_' . date('Y-m-d') . '.pdf';
            return $pdf->download($filename);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
    }
}
