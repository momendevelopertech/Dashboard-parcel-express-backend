<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\User;



use App\Models\Merchant;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\MerchantChatMessage;
use App\Models\MerchantChatSession;
use App\Models\PickuptaskTransaction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use App\Events\MerchantChatMessageSent;
use App\Exports\MerchantAccountsExport;
use App\Models\MerchantInvoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use App\Models\WarehouseTransaction;
use App\Services\WarehouseNetBalanceService;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Carbon\Carbon;
class MerchantAccountController extends Controller
{
    /**
     * List all merchants with aggregated financial data.
     * Each merchant appears ONCE with their total COD, fees, settlements, and balance.
     * Pagination is by MERCHANTS (not transactions) - shows all merchants even without transactions.
     */
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'type' => 'nullable|in:All,COD,Fee,Settlement,pickup_deposit',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',

        ]);

        $perPage = $request->input('per_page', 15);
        $searchTerm = $request->input('search');
        $from = $request->input('from');
        $to = $request->input('to');
        $workspaceId = facility()->id;
        $workspaceType = facility()->type;


        // First, get all merchants (users with merchant role/relationship) - paginate MERCHANTS directly
        $merchantsQuery = User::query()
            ->whereHas('merchant') // Only users who are merchants
            ->when($searchTerm, fn($q) => $q->where('name', 'like', '%' . $searchTerm . '%'))
            ->when($workspaceId && $workspaceType, function ($query) use ($workspaceId, $workspaceType) {
                try {
                    if ($workspaceId && $workspaceType) {
                        $query->whereHas('merchant', function ($q) use ($workspaceId, $workspaceType) {
                            $q->where('owner_id', $workspaceId)->where('owner_type', $workspaceType);
                        });
                    }
                } catch (\Exception $e) {
                    // Invalid workspace key, skip filter
                }
            })
            ->latest();

        // Paginate MERCHANTS directly (not transactions)
        $merchantsPaginator = $merchantsQuery->paginate($perPage);

        // For each merchant in current page, calculate their aggregated financial data
        $data = $merchantsPaginator->getCollection()->map(function ($merchant) use ($from, $to) {
            // Build base query for this merchant's transactions
            $transactionsQuery = \App\Models\MerchantTransaction::forMerchant($merchant->id)
                ->completed();

            //get total of all merchant transactions before the filtering process
            $currentBalance = $transactionsQuery->sum('amount');

            // Apply date filters if provided
            if ($from) {
                $transactionsQuery->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
            }
            if ($to) {
                $transactionsQuery->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
            }

            $transactions = $transactionsQuery->get();

            // Calculate totals
            $totalCod = $transactions->where('type', \App\Models\MerchantTransaction::TYPE_COD_COLLECTED)->sum('amount');
            
            $totalFees = abs($transactions->whereIn('type', [
                \App\Models\MerchantTransaction::TYPE_DELIVERY_FEE,
                \App\Models\MerchantTransaction::TYPE_RETURN_FEE,
                \App\Models\MerchantTransaction::TYPE_PICKUP_FEE,
                \App\Models\MerchantTransaction::TYPE_DELIVERY_DISCOUNT,
                \App\Models\MerchantTransaction::TYPE_RETURN_DISCOUNT,
                \App\Models\MerchantTransaction::TYPE_PICKUP_DISCOUNT,
            ])->sum('amount'));
            
            $totalSettlements = abs($transactions->where('type', \App\Models\MerchantTransaction::TYPE_SETTLEMENT)->sum('amount'));
            
            $filteredBalance = $transactions->sum('amount');
            
            $lastTransaction = $transactionsQuery->orderByDesc('created_at')->first();

            return [
                'merchant_id' => (int) $merchant->id,
                'merchant_name' => $merchant->name ?? 'N/A',
                'total_cod' => (float) $totalCod,
                'total_fees' => (float) $totalFees,
                'total_settlements' => (float) $totalSettlements,
                'filtered_balance' => (float) $filteredBalance,
                'current_balance' => (float) $currentBalance,
                'date' => $lastTransaction?->created_at?->toDateTimeString(),
            ];
        });

        // Calculate global totals (across all merchants, not just current page)
        $globalTotals = $this->calculateGlobalMerchantTotals($from, $to, $workspaceId, $workspaceType, $searchTerm);

        return response()->json([
            'data' => $data,
            'links' => $merchantsPaginator->linkCollection(),
            'meta' => [
                'current_page' => $merchantsPaginator->currentPage(),
                'from' => $merchantsPaginator->firstItem(),
                'to' => $merchantsPaginator->lastItem(),
                'total' => $merchantsPaginator->total(),
                'last_page' => $merchantsPaginator->lastPage(),
                'per_page' => $merchantsPaginator->perPage(),
            ],
            'totals' => $globalTotals,
        ]);
    }

    /**
     * Calculate global totals across all merchants (for summary cards)
     */
    private function calculateGlobalMerchantTotals($from = null, $to = null, $workspaceId = null, $workspaceType = null, $searchTerm = null)
    {
        $query = DB::table('merchant_transactions')
            ->join('users', 'merchant_transactions.merchant_id', '=', 'users.id')
            ->whereNull('merchant_transactions.deleted_at')
            ->where('merchant_transactions.status', 'completed');

        if ($searchTerm) {
            $query->where('users.name', 'like', '%' . $searchTerm . '%');
        }

        if ($from) {
            $query->where('merchant_transactions.created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('merchant_transactions.created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
        }

        if ($workspaceId && $workspaceType) {
            try {
                if ($workspaceId && $workspaceType) {
                    $query->join('merchants', 'users.id', '=', 'merchants.user_id')
                        ->where('merchants.owner_id', $workspaceId)
                        ->where('merchants.owner_type', $workspaceType);
                }
            } catch (\Exception $e) {
                // Invalid workspace key, skip filter
            }
        }

        $totals = $query->selectRaw("
            SUM(CASE WHEN type = 'cod_collected' THEN amount ELSE 0 END) as total_cod,
            ABS(SUM(CASE WHEN type IN ('delivery_fee', 'return_fee', 'pickup_fee', 'delivery_discount', 'return_discount', 'pickup_discount') THEN amount ELSE 0 END)) as total_fees,
            ABS(SUM(CASE WHEN type = 'settlement' THEN amount ELSE 0 END)) as total_settlement,
            SUM(CASE WHEN type = 'pickup_deposit' THEN amount ELSE 0 END) as total_pickup_deposit,
            SUM(amount) as total_balance,
            COUNT(DISTINCT merchant_transactions.merchant_id) as merchant_count
        ")->first();

        // Count merchants with positive balance
        $merchantsWithBalance = DB::table('merchant_transactions')
            ->select('merchant_id')
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->groupBy('merchant_id')
            ->havingRaw('SUM(amount) <> 0')
            ->count();

        return [
            'total_cod' => (float) ($totals->total_cod ?? 0),
            'total_fees' => (float) ($totals->total_fees ?? 0),
            'total_settlement' => (float) ($totals->total_settlement ?? 0),
            'total_pickup_deposit' => (float) ($totals->total_pickup_deposit ?? 0),
            'total_balance' => (float) ($totals->total_balance ?? 0),
            'merchant_count' => (int) ($totals->merchant_count ?? 0),
            'merchants_with_balance' => $merchantsWithBalance,
        ];
    }

    private function mapTransactionType($type)
    {
        switch ($type) {
            case \App\Models\MerchantTransaction::TYPE_COD_COLLECTED:
                return 'COD';
            case \App\Models\MerchantTransaction::TYPE_DELIVERY_FEE:
            case \App\Models\MerchantTransaction::TYPE_RETURN_FEE:
            case \App\Models\MerchantTransaction::TYPE_PICKUP_FEE:
            case \App\Models\MerchantTransaction::TYPE_DELIVERY_DISCOUNT:
            case \App\Models\MerchantTransaction::TYPE_RETURN_DISCOUNT:
            case \App\Models\MerchantTransaction::TYPE_PICKUP_DISCOUNT:
                return 'Fee';
            case \App\Models\MerchantTransaction::TYPE_SETTLEMENT:
                return 'Settlement';
            case \App\Models\MerchantTransaction::TYPE_PICKUP_DEPOSIT:
                return 'pickup_deposit';
            default:
                return $type;
        }
    }
    protected function paginateCollection($collection, $perPage, $page)
    {
        $page = $page ?: (LengthAwarePaginator::resolveCurrentPage() ?: 1);
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();
        return new LengthAwarePaginator($items, $collection->count(), $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
    }

    //     public function show(Request $r, int $merchantId)
    //     {
    //         $from = $r->date('from')?->startOfDay() ?? now()->startOfMonth();
    //         $to = $r->date('to')?->endOfDay() ?? now()->endOfDay();
    //         $type = $r->input('type', 'All');      // COD | Fee | Settlement | All
    //         $search = trim((string) $r->input('search', ''));
    //         $perPage = (int) $r->input('per_page', 20);

    //         // لو عندك عمود return_fee داخل shipments استخدمه، وإلا صفر
    //         $returnFeeExpr = Schema::hasColumn('shipments', 'return_fee')
    //             ? "CASE WHEN LOWER(o.fee_payer)='merchant' THEN COALESCE(o.return_fee,0) ELSE 0 END"
    //             : "0";

    //         // قبل الـ$shipments = DB::table(...)

    //         $hasValue = Schema::hasColumn('shipments', 'value');
    //         $hasReturnFee = Schema::hasColumn('shipments', 'return_fee');

    //         $deliveryFeeExpr = "COALESCE(o.delivery_fee,0)";
    //         $returnFeeExpr = $hasReturnFee ? "COALESCE(o.return_fee,0)" : "0";
    //         $feesExpr = "($deliveryFeeExpr + $returnFeeExpr)";

    //         // COD المجمع (قبل خصم رسوم العميل). يتم عرض الرسوم في حقل منفصل ويخصم من الرصيد فقط.
    //         $codExpr = "CASE
    //     WHEN UPPER(o.payment_type)='COD' THEN
    //         CASE WHEN $hasValue = 1 THEN COALESCE(o.value,0) ELSE COALESCE(o.amount,0) END
    //     ELSE 0
    // END";

    //         $shipments = DB::table('shipments as o')
    //             ->where(function ($q) use ($merchantId) {
    //                 $q->where('o.merchant_id', $merchantId)
    //                     ->orWhere('o.shipper_id', $merchantId);
    //             })
    //             ->whereBetween('o.created_at', [$from, $to])
    //             ->select([
    //                 'o.created_at',
    //                 'o.tracking_no',
    //                 DB::raw("$codExpr as cod_for_merchant"),   // << بديل cod_amount القديم

    //                 // نحتفظ بالرسوم للعرض فقط لو حبيت
    //                 DB::raw("CASE WHEN LOWER(o.fee_payer)='merchant' THEN $deliveryFeeExpr ELSE 0 END as merchant_delivery_fee"),
    //                 DB::raw("CASE WHEN LOWER(o.fee_payer)='merchant' THEN $returnFeeExpr   ELSE 0 END as return_fee"),
    //             ])
    //             ->get();


    //         // KPIs
    //         // $totalCOD = (float) $shipments->sum('cod_amount');
    //         // $totalFees = (float) $shipments->sum(fn($row) => (float) $row->merchant_delivery_fee + (float) $row->return_fee);
    //         $totalCOD = (float) $shipments->sum('cod_for_merchant');

    //         // لو عايز تعرض KPI للرسوم (معلوماتي فقط)
    //         $totalFees = (float) $shipments->sum(fn($r) => (float) $r->merchant_delivery_fee + (float) $r->return_fee);


    //         $settQ = DB::table('merchant_settlements')
    //             ->where('merchant_id', $merchantId)
    //             ->whereBetween('created_at', [$from, $to]);

    //         $totalSettlements = (float) $settQ->sum('amount');
//         $settlements = $settQ->select(['created_at', 'reference', 'amount', 'notes', 'receipt_path'])->get();

    //         $currentBalance = $totalCOD - $totalSettlements;


    //         $rows = collect();

    //         foreach ($shipments as $o) {
    //             if ((float) $o->cod_for_merchant > 0) {
    //                 $rows->push([
    //                     'date' => $o->created_at,
    //                     'reference' => $o->tracking_no,
    //                     'type' => 'COD',
    //                     'description' => "COD collected for {$o->tracking_no}",
    //                     'amount' => (float) $o->cod_for_merchant,
    //                 ]);
    //             }
    //         }

    //         foreach ($settlements as $s) {
    //             $rows->push([
    //                 'date' => $s->created_at,
    //                 'reference' => $s->reference,
    //                 'type' => 'Settlement',
    //                 'description' => $s->notes ?: 'Settlement',
    //                 'amount' => -1 * (float) $s->amount,
    //                 'receipt_url' => $s->receipt_path ? Storage::disk('public')->url($s->receipt_path) : null,
    //             ]);
    //         }

    //         if (in_array($type, ['COD', 'Fee', 'Settlement'], true)) {
    //             $rows = $rows->where('type', $type)->values();
    //         }
    //         if ($search !== '') {
    //             $q = mb_strtolower($search);
    //             $rows = $rows->filter(
    //                 fn($row) => str_contains(mb_strtolower((string) $row['reference']), $q) ||
    //                 str_contains(mb_strtolower((string) $row['description']), $q)
    //             )->values();
    //         }

    //         $rows = $rows->sortByDesc('date')->values();
    //         $page = LengthAwarePaginator::resolveCurrentPage();
    //         $slice = $rows->forPage($page, $perPage)->values();
    //         $paginator = new LengthAwarePaginator($slice, $rows->count(), $perPage, $page);

    //         return sendResponse('Merchant account retrieved.', [
    //             'summary' => [
    //                 'total_cod' => $totalCOD,
    //                 'total_fees' => $totalFees,
    //                 'total_settlements' => $totalSettlements,
    //                 'current_balance' => $currentBalance,
    //             ],
    //             'data' => $paginator->items(),
    //             'links' => $paginator->toArray()['links'] ?? [],
    //             'merchant' => DB::table('users')->select('id', 'name')->where('id', $merchantId)->first(),
    //         ], []);
    //     }

    public function show(Request $r, int $merchantId)
    {
        return sendResponse(
            'Merchant account retrieved.',
            $this->buildMerchantAccountPayload($r, $merchantId)
        );
    }






    public function storeSettlement(Request $r, int $userId)
    {

    $baseQuery = \App\Models\MerchantTransaction::forMerchant($userId)
        ->completed();
    $currentBalance = (clone $baseQuery)->sum('amount');

    // 🔹 Validation (receipt removed)
    $r->validate([
        'amount' => [
        'required',
        'numeric',
        'min:'.$currentBalance,
        'max:'.$currentBalance,
        ],
        'notes' => ['nullable', 'string'],
        'reference' => ['nullable', 'string', 'max:100'],
    ]);



    $net = app(WarehouseNetBalanceService::class)
    ->calculate();


    $settlementDue  =$currentBalance;

    $maxPayable = min($settlementDue, $net);


    /** Precise business error */
    if ((float) $r->amount > $maxPayable + 1e-6) {

        // Identify the limiting factor
        if ($net < $settlementDue) {
            $reason = __(
                'Insufficient facility balance. Available balance is :net',
                ['net' => number_format($net, 2)]
            );
        } else {
            $reason = __(
                'Amount exceeds Merchant settlement due of :due',
                ['due' => number_format($settlementDue, 2)]
            );
        }

        throw ValidationException::withMessages([
            'amount' => __(
                'Entered amount (:entered) is not allowed. :reason',
                [
                    'entered' => number_format($r->amount, 2),
                    'reason'  => $reason,
                ]
            ),
        ]);
    }
    /**
     * ------------------------------------------------------------------
     * Generate PDF invoice → uploadFile() → S3
     * ------------------------------------------------------------------
     */
    $user = \App\Models\User::with('merchant')->findOrFail($userId);

    $ref = $r->input('reference') ?: ('SETTLE-' . now()->format('Ymd-His'));

    /**
     * ------------------------------------------------------------------
     * Original logic untouched below
     * ------------------------------------------------------------------
     */
    try {
        return DB::transaction(function () use ($r, $userId,$user, $ref, $baseQuery) {

            $transaction = app(\App\Services\MerchantTransactionService::class)->recordSettlement(
                $userId,
                $r->input('amount'),
                $ref,
                [
                    'description' => $r->input('notes') ?: 'Settlement to merchant',
                    'receipt_path' => null,
                    'received_by' => Auth::id(),
                    'paid_at' => now(),
                    'status' => \App\Models\MerchantTransaction::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]
            );

            $facilityId = Auth::user()->owner_id;
            $facilityType = Auth::user()->owner_type;
            WarehouseTransaction::create([
                'warehouse_id' => $facilityId,
                'warehouse_type' => $facilityType,
                'source' => 'merchant_settlement',
                'type' => 'cash_out',
                'amount' => (float) $r->input('amount'),
                'reference' => $ref,
                'description' => "Merchant #{$userId} Settlement",
                'created_by' => Auth::id(),
            ]);
            //add invoice of merchant to merchant invoices table
            $merchantInvoice=MerchantInvoice::create([
                'invoice_file_path' => null,
                'amount' => $r->input('amount'),
                'merchant_id' => $userId,
                'created_by' => Auth::id(),
            ]);

            $shipments=merchantSettleShipments($merchantInvoice);

            // ----------------------------------
            // Generate PDF using mPDF (Arabic OK)
            // ----------------------------------

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font' => 'dejavusans',
                'autoScriptToLang' => true,
                'autoLangToFont'   => true,
                'directionality'  => 'ltr',
                'tempDir' => storage_path('mpdf'),
            ]);


            $pickupBaseQuery = PickuptaskTransaction::query()
            ->where('remitted', 1)
            ->where('amount', '>', 0)
            ->whereHas('pickuptask', fn ($q) => $q->where('merchant_id', $userId));

            $pickupFrom = (clone $pickupBaseQuery)->min('created_at');
            $pickupTo   = (clone $pickupBaseQuery)->max('created_at');

            $pickupTasks = (clone $pickupBaseQuery)
            ->with('pickuptask')
            ->get();

            $merchantFrom = (clone $baseQuery)->min('created_at');
            $merchantTo   = (clone $baseQuery)->max('created_at');

            $from = collect([$merchantFrom, $pickupFrom])->filter()->min();
            $to   = collect([$merchantTo,   $pickupTo])->filter()->max();

            $from = $from ? Carbon::parse($from) : null;
            $to   = $to ? Carbon::parse($to) : null;


            $html = view('pdf.merchant-invoice', [
                'merchant_name'    => $user->name,
                'merchant_address' => $user->merchant?->address ?? '-',
                'merchant_contact' => $user->phone,
                'amount'           => $r->input('amount'),
                'notes'            => $r->input('notes'),
                'shipments'        => $shipments,
                'pickup_tasks'     => $pickupTasks,
                'reference'        => $ref,
                'from'             => $from,
                'to'               => $to,
                'date'             => now()->format('Y-m-d H:i'),
            ])->render();


            $mpdf->WriteHTML($html);

            // 1️⃣ Save mPDF output to temp file
            $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '.pdf';
            $mpdf->Output($tempPath, \Mpdf\Output\Destination::FILE);

            // 2️⃣ Convert to UploadedFile
            $uploadedFile = new UploadedFile(
                $tempPath,
                'invoice_' . now()->format('Ymd_His') . '.pdf',
                'application/pdf',
                null,
                true
            );

            // 3️⃣ Upload to S3
            $uploadedPath = uploadFile(
                $uploadedFile,
                'public/receipts/merchant-settlements'
            );

            // 4️⃣ Cleanup
            @unlink($tempPath);



            $merchantInvoice->update(["invoice_file_path"=>$uploadedPath]);
            $transaction->update(["receipt_path"=>$uploadedPath]);

            if (!$uploadedPath) {
                return sendResponse("Error generating receipt.", [], false, ["Invoice upload failed."], 500);
            }

            $receiptUrl = $uploadedPath;

            $user = \App\Models\User::with('merchant')->findOrFail($userId);
            $merchantId = $user->id ?? null;
            $senderName = $user->name;

            $session = \App\Models\MerchantChatSession::where('merchant_id', $merchantId)
                ->where('status', 'ACTIVE')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$session) {
                $session = \App\Models\MerchantChatSession::create([
                    'merchant_id' => $merchantId,
                    'session_id' => 'CHAT' . now()->format('Ymd') . strtoupper(Str::random(8)),
                    'subject' => 'General Chat',
                    'priority' => 'MEDIUM',
                    'status' => 'ACTIVE',
                ]);
            }

            $messageContent = json_encode([
                'type' => 'settlement_request',
                'data' => [
                    'amount' => $r->input('amount'),
                    'notes' => $r->input('notes'),
                    'status' => 'pending',
                    'created_at' => now()->toISOString(),
                    'id' => $transaction->id,
                    'receipt' => $receiptUrl,
                ],
                'timestamp' => now()->toISOString(),
            ]);

            $message = \App\Models\MerchantChatMessage::create([
                'merchant_chat_session_id' => $session->id,
                'sender_type' => 'SYSTEM',
                'sender_id' => $merchantId,
                'sender_name' => $senderName,
                'message' => $messageContent,
                'message_type' => 'CARD',
            ]);

            $message->load('chatSession.merchant');

            create_notification(
                $user,
                '💰 New Settlement Received',
                "A new settlement was posted to **{$user->name}** for **" . getCurrency('en') . " {$r->input('amount')}**.",
                [
                    'type' => 'settlement_request',
                    'settlement_request_id' => $transaction->id,
                    'merchant_name' => $user->name,
                    'amount' => $r->input('amount'),
                    'notes' => $r->input('notes'),
                    'receipt_url' => $receiptUrl,
                ],
                'settlement_request',
                false
            );

            broadcast(new \App\Events\MerchantChatMessageSent($message));
            $session->touch();
             activityLog('merchant settlement created',"new settlement created for merchant $user->name");
            return sendResponse('Settlement created.', [
                'reference' => $ref,
                'receipt_url' => $receiptUrl,
                'id' => $transaction->id,
            ], []);
        });
    } catch (\Throwable $e) {
        return sendResponse("Failed to create settlement.", [], false, [$e->getMessage()], 500);
    }
    }





    public function export(Request $r, int $merchantId)
    {
        $r->merge(['per_page' => 100000, 'merchant_id' => $merchantId]);
        $res = $this->index($r);
        $payload = $res->getData(true)['data'] ?? $res->getData(true);

        $rows = collect($payload['data'] ?? $payload['data'] ?? []);
        $format = $r->input('format', 'csv');

        $array = $rows->map(fn($x) => [
            'date' => date('Y-m-d', strtotime($x['date'] ?? $x->date)),
            'reference' => $x['reference'] ?? '',
            'type' => $x['type'] ?? '',
            'description' => $x['description'] ?? '',
            'amount' => $x['amount'] ?? 0,
        ])->toArray();

        // Export بسيط
        $export = new class($array) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(private $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['Date', 'Reference', 'Type', 'Description', 'Amount'];
            }
        };

        $name = 'merchant_account_' . now()->format('Y_m_d_H_i_s');
        if ($format === 'xlsx') {
            return Excel::download($export, $name . '.xlsx');
        }
        return Excel::download($export, $name . '.csv', \Maatwebsite\Excel\Excel::CSV);
    }


    public function merchantAccount(Request $r)
    {
        $user = auth()->user();

        if(!$user->merchant){
            return sendResponse('Unauthenticated.', [], false, ['User not authenticated as Merchant.'], 401);
        }

        return sendResponse(
            'Merchant account retrieved.',
            $this->buildMerchantAccountPayload($r, (int) $user->id)
        );
    }



    public function requestSettlement(Request $request)
    {
        // 1) Validation
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:2000',
        ]);

        // 2) Resolve user/merchant
        $userId = $request->input('sender_id') ?? Auth::id();
        $user = User::findOrFail($userId);

        if (!$user->merchant) {
            return response()->json([
                'message' => 'Merchant not found for this user.',
                'success' => false
            ], 404);
        }

        $merchantId = $user->id;
        $senderName = $request->input('sender_name') ?? $user->name;
        $amount = (float) $request->input('amount');
        $notes = $request->input('notes');

        // 3) Get current balance from unified ledger
        $service = app(\App\Services\MerchantTransactionService::class);
        $currentBalance = $service->getBalance($merchantId);
        $allowed = max(0.0, $currentBalance);

        $total_pickup_deposit=PickuptaskTransaction::whereHas("pickuptask",fn($q) => $q->where('merchant_id', $merchantId))->sum("amount");

        if ($amount > $allowed) {
            return sendResponse("Amount exceeds current balance.", [
                'current_balance' => $currentBalance,
                'requested' => $amount,
                'total_pickup_deposit' => $total_pickup_deposit, //Money received from merchants by drivers while pickup tasks

            ], false, [], 422);
        }


        // 9) إنشاء الطلب/التذكرتين … (نفس منطقك السابق)
        DB::beginTransaction();
        try {
            if (class_exists(\App\Models\MerchantTicket::class)) {
                $ticket = \App\Models\MerchantTicket::create([
                    'merchant_id' => $merchantId,
                    'subject' => 'Settlement Request',
                    'type' => 'settlement_request',
                    'status' => 'Active',
                    'priority' => 'normal',
                    'meta' => json_encode(['amount' => $amount]),
                    'created_by' => $merchantId,
                    'initial_message' => 'Requested settlement amount: ' . $amount . ($notes ? ("\n\nNotes: " . $notes) : ''),
                ]);

                if (class_exists(\App\Models\MerchantTicketMessage::class)) {
                    \App\Models\MerchantTicketMessage::create([
                        'merchant_ticket_id' => $ticket->id,
                        'sender_id' => $merchantId,
                        'message' => 'Requested settlement amount: ' . $amount . ($notes ? ("\n\nNotes: " . $notes) : ''),
                    ]);
                }
            } else {
                $ticketId = DB::table('merchant_tickets')->insertGetId([
                    'merchant_id' => $merchantId,
                    'subject' => 'Settlement Request',
                    'type' => 'settlement_request',
                    'status' => 'open',
                    'priority' => 'normal',
                    'meta' => json_encode(['amount' => $amount]),
                    'created_by' => $merchantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('merchant_ticket_messages')->insert([
                    'ticket_id' => $ticketId,
                    'user_id' => $merchantId,
                    'message' => 'Requested settlement amount: ' . $amount . ($notes ? ("\n\nNotes: " . $notes) : ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            // 10) تأمين جلسة دردشة نشطة
            $session = MerchantChatSession::where('merchant_id', $merchantId)
                ->where('status', 'ACTIVE')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$session) {
                $session = MerchantChatSession::create([
                    'merchant_id' => $merchantId,
                    'session_id' => 'CHAT' . now()->format('Ymd') . strtoupper(Str::random(8)),
                    'subject' => 'General Chat',
                    'priority' => 'MEDIUM',
                    'status' => 'ACTIVE'
                ]);
            }

            // 11) إرسال بطاقة في الدردشة
            $messageContent = json_encode([
                'type' => 'settlement_request',
                'data' => [
                    'amount' => $amount,
                    'notes' => $notes,
                    'status' => 'pending',
                    'created_at' => now()->toISOString(),
                    'id' => isset($ticket) ? $ticket->id : ($ticketId ?? null),
                ],
                'timestamp' => now()->toISOString()
            ]);
            $messageText = "💰 Settlement Request\n"
                . "Amount: {$amount}\n"
                . "Notes: " . ($notes ?: 'N/A') . "\n"
                . "Status: Pending\n"
                . "Requested at: " . now()->toDateTimeString();

            $message = MerchantChatMessage::create([
                'merchant_chat_session_id' => $session->id,
                'sender_type' => 'MERCHANT',
                'sender_id' => $merchantId,
                'sender_name' => $senderName,
                'message' => $messageText,
                'message_type' => 'CARD'
            ]);

            $message->load('chatSession.merchant');

            // 12) تنبيهات جميلة للفِرَق
            $recipients = User::role(['customer service', 'Super Admin'])->get();
            foreach ($recipients as $recipient) {
                create_notification(
                    $recipient,
                    '💰 New Settlement Request',
                    "A new settlement request has been received from **{$user->name}** for a total of **" . getCurrency("en") . "{$amount}**.",
                    [
                        'type' => 'settlement_request',
                        'settlement_request_id' => isset($ticket) ? $ticket->id : ($ticketId ?? null),
                        'merchant_name' => $user->name,
                        'amount' => $amount,
                        'notes' => $notes,
                    ],
                    'settlement_request',
                    false
                );
            }

            // 13) بث الرسالة وتحديث الجلسة
            broadcast(new MerchantChatMessageSent($message));
            $session->touch();

            return sendResponse('Settlement request submitted.', [], []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse('Error occurred.', [], false, [$e->getMessage()], 500);
        }
    }

    protected function buildMerchantAccountPayload(Request $r, int $merchantId): array
    {
        $from = $r->date('from')?->startOfDay();
        $to = $r->date('to')?->endOfDay();
        $type = $r->input('type', 'All');
        $search = $r->input('search');
        $pickupRef = trim((string) $r->input('pickup_ref', ''));
        $perPage = (int) $r->input('per_page', 20);

        $query = \App\Models\MerchantTransaction::forMerchant($merchantId)
            ->completed()
            ->orderByDesc('created_at');

        //get total of all merchant transactions before the filtering process
        $currentBalance = $query->sum('amount');


        if(isset($from) && isset($to)){
            $query->whereBetween('created_at', [$from, $to]);
        }

        if (isset($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            });
        }

        if ($pickupRef !== '') {
            $query->where(function ($q) use ($pickupRef) {
                $q->where('reference', 'like', "%{$pickupRef}%");
            });
        }

        if ($type !== 'All') {
            switch ($type) {
                case 'COD':
                    $query->where('type', \App\Models\MerchantTransaction::TYPE_COD_COLLECTED);
                    break;
                case 'Fee':
                    $query->whereIn('type', [
                        \App\Models\MerchantTransaction::TYPE_DELIVERY_FEE,
                        \App\Models\MerchantTransaction::TYPE_RETURN_FEE,
                        \App\Models\MerchantTransaction::TYPE_PICKUP_FEE,
                        \App\Models\MerchantTransaction::TYPE_DELIVERY_DISCOUNT,
                        \App\Models\MerchantTransaction::TYPE_RETURN_DISCOUNT,
                        \App\Models\MerchantTransaction::TYPE_PICKUP_DISCOUNT,
                    ]);
                    break;
                case 'Settlement':
                    $query->where('type', \App\Models\MerchantTransaction::TYPE_SETTLEMENT);
                    break;
                case 'pickup_deposit':
                    $query->where('type', \App\Models\MerchantTransaction::TYPE_PICKUP_DEPOSIT);
                    break;
                case 'delivery_discount':
                    $query->where('type', \App\Models\MerchantTransaction::TYPE_DELIVERY_DISCOUNT);
                    break;
                case 'delivery_rebate_to_merchant':
                    $query->where('type', \App\Models\MerchantTransaction::TYPE_DELIVERY_REBATE_TO_MERCHANT);
                    break;
            }
        }

        $allForSummary = (clone $query)->get();

        $totalCOD = $allForSummary->where('type', \App\Models\MerchantTransaction::TYPE_COD_COLLECTED)->sum('amount');
        
        $totalFees = abs($allForSummary->whereIn('type', [
            \App\Models\MerchantTransaction::TYPE_DELIVERY_FEE,
            \App\Models\MerchantTransaction::TYPE_RETURN_FEE,
            \App\Models\MerchantTransaction::TYPE_PICKUP_FEE,
            \App\Models\MerchantTransaction::TYPE_DELIVERY_DISCOUNT,
            \App\Models\MerchantTransaction::TYPE_RETURN_DISCOUNT,
            \App\Models\MerchantTransaction::TYPE_PICKUP_DISCOUNT,
        ])->sum('amount'));

        $totalSettlements = abs($allForSummary->where('type', \App\Models\MerchantTransaction::TYPE_SETTLEMENT)->sum('amount'));
        
        $totalPickupDeposit = $allForSummary->where('type', \App\Models\MerchantTransaction::TYPE_PICKUP_DEPOSIT)->sum('amount');

        $totalDiscountBenefits = $allForSummary->where('type', \App\Models\MerchantTransaction::TYPE_DELIVERY_DISCOUNT)->sum('amount');

        $deliveryRebateToMerchant = $allForSummary->where('type', \App\Models\MerchantTransaction::TYPE_DELIVERY_REBATE_TO_MERCHANT)->sum('amount');

        $filteredBalance = $allForSummary->sum('amount');


        $paginated = $query->paginate($perPage);

        $rows = collect($paginated->items())->map(function ($t) {
            return [
                'date' => $t->created_at->toDateTimeString(),
                'reference' => $t->reference,
                'type' => $this->mapTransactionType($t->type),
                'description' => $t->description,
                'amount' => (float) $t->amount,
            ];
        });

        return [
            'summary' => [
                'total_cod' => $totalCOD,
                'total_fees' => $totalFees,
                'total_settlements' => $totalSettlements,
                'total_pickup_deposit' => $totalPickupDeposit,
                'current_balance' => $currentBalance,
                'filtered_balance' => $filteredBalance,
                'Total_fees_before_discount' => $totalFees + $totalDiscountBenefits,
                'total_discount_benefits' => $totalDiscountBenefits,
                'delivery_rebate_to_merchant' => $deliveryRebateToMerchant,
            ],
            'data' => $rows,
            'links' => $paginated->linkCollection(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'merchant' => \App\Models\User::select('id', 'name')->where('id', $merchantId)->first(),
        ];
    }

    public function exportAll(Request $request)
    {
        try {
            $request->validate([
                'search' => 'nullable|string|max:100',
                'from' => 'nullable|date',
                'to' => 'nullable|date|after_or_equal:from',
                'workspace_key' => 'nullable',
                'workspace_type' => 'nullable',
                'type' => 'nullable|string',
            ]);

            $search = $request->input('search');
            $from = $request->input('from');
            $to = $request->input('to');
            $workspaceKey = $request->input('workspace_key');
            $workspaceType = $request->input('workspace_type');
            $type = $request->input('type', 'All');

            $query = \App\Models\MerchantTransaction::with('merchant')
                ->orderByDesc('created_at');

            // Apply filters (same as index)
            if ($search) {
                $query->whereHas('merchant', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            }

            if ($from) {
                $query->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay());
            }

            if ($to) {
                $query->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay());
            }

            if ($type !== 'All') {
                switch ($type) {
                    case 'COD':
                        $query->where('type', \App\Models\MerchantTransaction::TYPE_COD_COLLECTED);
                        break;
                    case 'Fee':
                        $query->whereIn('type', [
                            \App\Models\MerchantTransaction::TYPE_DELIVERY_FEE,
                            \App\Models\MerchantTransaction::TYPE_RETURN_FEE,
                            \App\Models\MerchantTransaction::TYPE_PICKUP_FEE,
                            \App\Models\MerchantTransaction::TYPE_DELIVERY_DISCOUNT,
                            \App\Models\MerchantTransaction::TYPE_RETURN_DISCOUNT,
                            \App\Models\MerchantTransaction::TYPE_PICKUP_DISCOUNT,
                        ]);
                        break;
                    case 'Settlement':
                        $query->where('type', \App\Models\MerchantTransaction::TYPE_SETTLEMENT);
                        break;
                    case 'pickup_deposit':
                        $query->where('type', \App\Models\MerchantTransaction::TYPE_PICKUP_DEPOSIT);
                        break;
                }
            }

            if ($workspaceKey && $workspaceType) {
                $workspaceId = Crypt::decryptString(is_array($workspaceKey) ? $workspaceKey[0] : $workspaceKey);
                $workspaceTypeStr = is_array($workspaceType) ? $workspaceType[0] : $workspaceType;
                if ($workspaceId && $workspaceTypeStr) {
                    $query->whereHas('merchant', function ($q) use ($workspaceId, $workspaceTypeStr) {
                        $q->where('owner_id', $workspaceId)
                            ->where('owner_type', $workspaceTypeStr);
                    });
                }
            }

            $transactions = $query->get();

            if ($transactions->isEmpty()) {
                return sendResponse("No transactions found.", [], [], 404);
            }

            // Map to the format expected by the export class
            $exportData = $transactions->map(function ($t) {
                return (object) [
                    'merchant_name' => $t->merchant->name ?? 'N/A',
                    'type' => $this->mapTransactionType($t->type),
                    'reference' => $t->reference ?? ($t->shipment ? $t->shipment->tracking_no : null),
                    'description' => $t->description,
                    'amount' => (float) $t->amount,
                    'created_at' => $t->created_at->toDateTimeString(),
                ];
            });

            $export = new MerchantAccountsExport($exportData);
            $fileName = "merchant_accounts_export_" . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download($export, $fileName);
        } catch (\Exception $e) {
            return sendResponse("Error occurred during export", [], [$e->getMessage()], 422);
        }
    }
    public function deactivateSelf(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->status === 'inactive') {
            return sendResponse('Account already inactive.', [], true, [], 200);
        }

        DB::transaction(function () use ($user) {
            $user->update([
                'status' => 'inactive',
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ]);

            $user->tokens()->delete();
        });

        Auth::guard('web')->logout();

        return sendResponse('Your account has been deactivated successfully.', [], true);
    }
}
