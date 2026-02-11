<?php

namespace App\Http\Controllers\Api\v1;
use App\Models\User;


use App\Models\Invoice;
use App\Models\Shipment;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\MerchantPaymentSummaryRequest;
use App\Http\Requests\MerchantPaymentTransactionsRequest;
use App\Http\Resources\MerchantPaymentTransactionResource;

class MerchantPaymentController extends Controller
{
    /**
     * Get Payment Summary
     *
     * @OA\Get(
     *     path="/merchant/payments/summary",
     *     summary="Get merchant payment summary",
     *     description="
     * Retrieve payment summary for merchant including COD collections and settlements.
     *
     * **Features:**
     * - COD collected amount
     * - Settled payments amount
     * - Pending payments amount
     * - Date range filtering
     *
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getMerchantPaymentSummary",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Start date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="End date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Summary retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="cod_collected", type="number", format="float"),
     *                 @OA\Property(property="settled", type="number", format="float"),
     *                 @OA\Property(property="pending", type="number", format="float")
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function summary(MerchantPaymentSummaryRequest $request)
    {
        $merchant = Auth::user();

        // Base COD shipments for this merchant
        $baseQuery = Shipment::query()
            ->where('merchant_id', $merchant->id)
            ->where('payment_type', 'COD')
            ->when($request->from, fn ($q) => $q->whereDate('shipments.created_at', '>=', $request->from))
            ->when($request->to,   fn ($q) => $q->whereDate('shipments.created_at', '<=', $request->to));

        // COD collected = delivered COD shipments
        $codCollected = (clone $baseQuery)
            ->whereHas('runsheet_shipment', fn ($q) => $q->where('status', 'delivered'))
            ->sum('total_cod');

        // COD settled = delivered COD shipments that are attached to a PAID invoice
        $codSettled = (clone $baseQuery)
            ->whereHas('invoice_shipment.invoice', fn ($q) => $q->where('status', 'paid'))
            ->sum('total_cod');

        return sendResponse("Summary retrieved successfully", [
            'cod_collected' => (float) $codCollected,
            'settled'       => (float) $codSettled,
            'pending'       => (float) ($codCollected - $codSettled),
        ]);
    }

    /**
     * Get Payment Transactions
     *
     * @OA\Get(
     *     path="/merchant/payments/transactions",
     *     summary="Get merchant payment transactions",
     *     description="
     * Retrieve paginated list of merchant payment transactions including COD shipments and invoices.
     *
     * **Features:**
     * - COD and invoice transactions
     * - Status filtering
     * - Date range filtering
     * - Type filtering
     * - Pagination support
     *
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getMerchantPaymentTransactions",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Start date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="End date filter",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Transaction type filter (cod or invoice)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"cod", "invoice"})
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Transaction status filter",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transactions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Transactions retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function transactions(MerchantPaymentTransactionsRequest $request)
    {
        $merchant = Auth::user();

        $codShipmentsQuery = Shipment::query()
            ->where('merchant_id', $merchant->id)
            ->where('payment_type', 'COD')
            ->whereHas('runsheet_shipment', fn ($q) => $q->where('status', 'delivered'))
            ->when($request->from, fn ($q) => $q->whereDate('shipments.created_at', '>=', $request->from))
            ->when($request->to,   fn ($q) => $q->whereDate('shipments.created_at', '<=', $request->to))
            ->with(['invoice_shipment.invoice:id,status']);

        $invoiceQuery = Invoice::query()
            ->where('owner_type', User::class)
            ->where('owner_id', $merchant->id)
            ->when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,   fn ($q) => $q->whereDate('created_at', '<=', $request->to));

        if ($request->filled('type')) {
            if ($request->type === 'cod') {
                $invoiceQuery->whereRaw('0 = 1');
            } else {
                $codShipmentsQuery->whereRaw('0 = 1');
            }
        }

        if ($request->filled('status')) {
            $status = $request->status;
            $invoiceQuery->where('status', $status);
        }
        $codItems = $codShipmentsQuery->get()->map(function (Shipment $shipment) {
            $settled = optional(optional($shipment->invoice_shipment)->invoice)->status === 'paid';

            return [
                'id'            => $shipment->id,
                'tracking_no'     => $shipment->tracking_no,
                'amount'        => $shipment->total_cod,
                'type'          => 'cod',
                'status'        => $settled ? 'settled' : 'pending',
                'created_at'    => $shipment->created_at,
                'customer_name' => $shipment->consignee->name,
                'customer_phone'=> $shipment->consignee->country_key_cellphone . $shipment->consignee->cellphone,
            ];
        });

        $invoiceItems = $invoiceQuery->get()->map(function (Invoice $invoice) {
            return [
                'id'            => $invoice->id,
                'reference'     => $invoice->invoice_no,
                'amount'        => $invoice->amount,
                'type'          => 'invoice',
                'status'        => $invoice->status,
                'created_at'    => $invoice->created_at,
                'customer_name' => null,
                'customer_phone'=> null,
            ];
        });

        if ($request->filled('status')) {
            $status = $request->status;
            $codItems = $codItems->where('status', $status)->values();
        }

        $items = $codItems->merge($invoiceItems)->sortByDesc('created_at')->values();

        $perPage = min((int) $request->input('per_page', 15), 100);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pagedItems = $items->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator($pagedItems, $items->count(), $perPage, $currentPage, [
            'path'  => request()->url(),
            'query' => request()->query(),
        ]);

        return sendResponse('Transactions retrieved successfully', MerchantPaymentTransactionResource::collection($paginator));
    }
}
