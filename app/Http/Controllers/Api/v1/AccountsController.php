<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;


use App\Exports\AccountExport;
use App\Http\Resources\FinancialRequestResource;
use App\Http\Resources\GeneralResource;
use App\Models\Account;
use App\Models\MerchantSettlement;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Models\Hub;
use App\Models\ShipmentFine;
use App\Models\Station;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseTransaction;
use Carbon\Carbon;
use Google\Service\ShoppingContent\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;


/**
 * @OA\Tag(
 *     name="Other",
 *     description="Accounts management"
 * )
 */
class AccountsController extends Controller
{

    private function signedApprovedRequestsTotal($query): float
    {
        $add = (clone $query)->where('type', '!=', 'branch_settlement')->sum('amount');
        $deduct = (clone $query)->where('type', 'branch_settlement')->sum('amount');

        return (float) $add - (float) $deduct;
    }

    private function runsheetTotals(int $runsheetId): array
    {
        $rs = \App\Models\DriverRunsheet::with([
            'delivered_shipments.shipment',
            'delivered_shipments.shipment.transfer_task',
            'delivered_shipments.shipment.transfer_shipment'
        ])->find($runsheetId);

        if (!$rs) {
            return ['cod' => 0.0, 'fee' => 0.0, 'transfer_amount' => 0.0, 'transfer_shipments' => []];
        }

        $cod = 0.0;
        $fee = 0.0;

        $transferAmount = 0.0;
        $transferShipments = [];

        foreach ($rs->delivered_shipments as $ro) {
            $o = $ro->shipment;
            if (!$o)
                continue;

            $cod += (float) ($o->value ?? 0);
            $fee += (float) ($o->delivery_fee ?? 0);

            $hasTransferTask = (bool) ($o->transfer_task ?? $o->transfer_shipment ?? null);
            if ($hasTransferTask) {
                $collectible = (float) ($o->value ?? 0);
                $feeVal = (float) ($o->delivery_fee ?? 0);
                $payer = strtolower((string) ($o->fee_payer ?? ''));

                if ($payer === 'merchant') {
                    $collectible -= $feeVal;
                } elseif ($payer === 'customer') {
                    $collectible += $feeVal;
                }
                $collectible = max(0, $collectible);

                $transferAmount += $collectible;
                if ($o->tracking_no) {
                    $transferShipments[] = (string) $o->tracking_no;
                }
            }
        }

        return [
            'cod' => $cod,
            'fee' => $fee,
            'transfer_amount' => $transferAmount,
            'transfer_shipments' => $transferShipments,
        ];
    }
    public function index(Request $request, $type, $id)
    {
        $typeClass = accountables($type);
        if (!$typeClass) {
            return sendResponse('Invalid account type', [], false, [], 400);
        }

        $entity = app($typeClass)->find($id);
        if (!$entity) {
            return sendResponse("Account not found.", [], false, [], 404);
        }

        $from = $request->query('from')
            ? \Carbon\Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->query('to')
            ? \Carbon\Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        $range = [$from->toDateTimeString(), $to->toDateTimeString()];

        $base = \App\Models\WarehouseTransaction::query()
            ->where('warehouse_transactions.warehouse_type', $typeClass)
            ->where('warehouse_transactions.warehouse_id', $id);

        $openIn = (clone $base)
            ->when($from, fn ($q) =>
                $q->where('warehouse_transactions.created_at', '<', $from)
            )
            ->whereIn('warehouse_transactions.type', ['cash_in', 'transfer_in'])
            ->sum('warehouse_transactions.amount');


        $openOut = (clone $base)
            ->when($from, fn ($q) =>
                $q->where('warehouse_transactions.created_at', '<', $from)
            )
            ->whereIn('warehouse_transactions.type', ['cash_out', 'transfer_out', 'expense'])
            ->sum('warehouse_transactions.amount');

        $opening = (float) $openIn - (float) $openOut;

        $agg = (clone $base)
            ->whereBetween('warehouse_transactions.created_at', $range)
            ->selectRaw("
                SUM(CASE WHEN warehouse_transactions.type = 'cash_in'
                         THEN warehouse_transactions.amount ELSE 0 END) AS cash_in_only,
                SUM(CASE WHEN warehouse_transactions.type = 'cash_out'
                         THEN warehouse_transactions.amount ELSE 0 END) AS cash_out_only,
                SUM(CASE WHEN warehouse_transactions.type = 'expense'
                         THEN warehouse_transactions.amount ELSE 0 END) AS expenses_only,
                SUM(CASE WHEN warehouse_transactions.type = 'transfer_in'
                         THEN warehouse_transactions.amount ELSE 0 END) AS ibt_in_only,
                SUM(CASE WHEN warehouse_transactions.type = 'transfer_out'
                         THEN warehouse_transactions.amount ELSE 0 END) AS ibt_out_only
            ")
            ->first();

        $cashIn = (float) ($agg->cash_in_only ?? 0);
        $cashOut = (float) ($agg->cash_out_only ?? 0);
        $expenses = (float) ($agg->expenses_only ?? 0);
        $ibtIn = (float) ($agg->ibt_in_only ?? 0);
        $ibtOut = (float) ($agg->ibt_out_only ?? 0);

        $net = $opening + $cashIn + $ibtIn - $cashOut - $ibtOut - $expenses;

        $rows = (clone $base)
            ->leftJoin('users', 'users.id', '=', 'warehouse_transactions.created_by')
            ->whereBetween('warehouse_transactions.created_at', $range)
            ->orderBy('warehouse_transactions.created_at', 'desc')
            ->get([
                'warehouse_transactions.created_at as date',
                'warehouse_transactions.reference',
                'warehouse_transactions.type',
                'warehouse_transactions.description',
                'warehouse_transactions.amount',
                \DB::raw('COALESCE(users.name, "-") as user_name'),
            ]);

        return sendResponse("Account retrieved successfully.", [
            'accountable' => $entity->only(['id', 'name']),
            'summary' => [
                'opening_balance' => $opening,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'expenses' => $expenses,
                'net_balance' => $net,
                'ibt' => ['in' => $ibtIn, 'out' => $ibtOut],
                'period' => ['from' => $from, 'to' => $to],
            ],
            'transactions' => $rows,
        ], []);
    }


    public function show(Request $request)
    {
        // Resolve accountable class (Hub/Station/User...) via your helper
        $typeClass = facility()->type;
        $id=facility()->id;
        if (!$typeClass) {
            return sendResponse('Invalid account type', [], false, [], 400);
        }

        $entity = $typeClass::find($id);
        if (!$entity) {
            return sendResponse('Account not found', [], false, [], 404);
        }

        // Pagination & filters
        $perPage = (int) ($request->query('per_page') ?? $request->query('perPage') ?? 8);
        $transactionsPage = (int) $request->query('transactions_page', 1);
        $expensesPage = (int) $request->query('expenses_page', 1);
        $settlementsPage = (int) $request->query('settlements_page', 1);
        $payoutsPage = (int) $request->query('payouts_page', 1);
        $afrPage = (int) $request->query('approved_requests_page', 1);
        $advancesPage = (int) $request->query('advances_page', 1);
        $advancesPerPage = (int) ($request->query('advances_per_page') ?? $perPage);
        $type = $request->query('type');

        if ($type === 'all') {
            $type = null;
        }

        // FE type → DB type map
        $filterType = $request->query('type');
        $typeMap = [
            'ibt_in' => 'transfer_in',
            'ibt_out' => 'transfer_out',
            'cash_in' => 'cash_in',
            'cash_out' => 'cash_out',
            'expense' => 'expense',
        ];
        $effectiveType = $filterType && $filterType !== 'null'
            ? ($typeMap[$filterType] ?? $filterType)
            : null;

        // Date window
        $fromParam = $request->query('from');
        $toParam = $request->query('to');
        try {
            $from = $fromParam ? Carbon::parse($fromParam) : null;
            $to = $toParam ? Carbon::parse($toParam) : null;
        } catch (\Throwable $e) {
            return sendResponse('Invalid date format provided.', [], false, [], 400);
        }

        // Base WT query
        $base = WarehouseTransaction::query()
            ->where('warehouse_transactions.warehouse_type', $typeClass)
            ->where('warehouse_transactions.warehouse_id', $id)            
            ->when($type, fn ($q) =>
                $q->where('type', '<=', $type)
            );

        if ($effectiveType) {
            $base->where('warehouse_transactions.type', $effectiveType);
        }
        $openAgg = (clone $base)
        ->when($from, fn ($q) =>
                $q->where('warehouse_transactions.created_at', '<', $from)
            )
            ->selectRaw("
                SUM(CASE WHEN warehouse_transactions.type = 'cash_in'      THEN warehouse_transactions.amount ELSE 0 END) AS open_cash_in,
                SUM(CASE WHEN warehouse_transactions.type = 'cash_out'     THEN warehouse_transactions.amount ELSE 0 END) AS open_cash_out,
                SUM(CASE WHEN warehouse_transactions.type = 'expense'      THEN warehouse_transactions.amount ELSE 0 END) AS open_expense,
                SUM(CASE WHEN warehouse_transactions.type = 'transfer_in'  THEN warehouse_transactions.amount ELSE 0 END) AS open_ibt_in,
                SUM(CASE WHEN warehouse_transactions.type = 'transfer_out' THEN warehouse_transactions.amount ELSE 0 END) AS open_ibt_out
            ")
            ->first();

        $opening = $from ? (
            (float) $openAgg->open_cash_in
            + (float) $openAgg->open_ibt_in
            - (float) $openAgg->open_cash_out
            - (float) $openAgg->open_ibt_out
            - (float) $openAgg->open_expense
        ) : 0;


            $agg = (clone $base)
                ->when($from, fn ($q) =>
                    $q->where('warehouse_transactions.created_at', '>=', $from)
                )
                ->when($to, fn ($q) =>
                    $q->where('warehouse_transactions.created_at', '<=', $to)
                )
                ->selectRaw("
                    SUM(CASE WHEN warehouse_transactions.type = 'cash_in'      THEN warehouse_transactions.amount ELSE 0 END) AS cash_in_only,
                    SUM(CASE WHEN warehouse_transactions.type = 'cash_out'     THEN warehouse_transactions.amount ELSE 0 END) AS cash_out_only,
                    SUM(CASE WHEN warehouse_transactions.type = 'expense'      THEN warehouse_transactions.amount ELSE 0 END) AS expenses_only,
                    SUM(CASE WHEN warehouse_transactions.type = 'transfer_in'  THEN warehouse_transactions.amount ELSE 0 END) AS ibt_in_only,
                    SUM(CASE WHEN warehouse_transactions.type = 'transfer_out' THEN warehouse_transactions.amount ELSE 0 END) AS ibt_out_only
                ")
                ->first();


        $cashIn = (float) ($agg->cash_in_only ?? 0);
        $cashOut = (float) ($agg->cash_out_only ?? 0);
        $ibtIn = (float) ($agg->ibt_in_only ?? 0);
        $ibtOut = (float) ($agg->ibt_out_only ?? 0);

        $expensesPaginator = Expense::where('owner_id', $id)
            ->where('owner_type', $typeClass)
            ->when($from, fn ($q) =>
                $q->where('created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->where('created_at', '<=', $to)
            )
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'expenses_page', $expensesPage);


            $totalExpensesAmount = (float) Expense::where('owner_id', $id)
                ->where('owner_type', $typeClass)
                ->when($from, fn ($q) =>
                    $q->where('created_at', '>=', $from)
                )
                ->when($to, fn ($q) =>
                    $q->where('created_at', '<=', $to)
                )
                ->sum('amount');

        $expenses = $totalExpensesAmount;

        // Approved Financial Requests for THIS warehouse/branch reduce available net
        $afrQuery = FinancialRequest::query()
            ->where('status', 'approved')
            ->when($from, fn ($q) =>
                $q->where('created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->where('created_at', '<=', $to)
            );


        if (
            Schema::hasColumn('financial_requests', 'requester_id') &&
            Schema::hasColumn('financial_requests', 'requester_type')
        ) {
            $afrQuery->where('requester_type', $typeClass)
                ->where('requester_id', $id);
        } else {
            $afrQuery->where('owner_type', $typeClass)
                ->where('owner_id', $id);
        }

        $approvedRequestsTotal = $this->signedApprovedRequestsTotal($afrQuery);

        // NET = opening + inflows - outflows - expenses - approved requests
        $net = $opening + $cashIn + $ibtIn - $cashOut - $ibtOut - $expenses + $approvedRequestsTotal;

        // Today collections (just cash_in today)
        $todayCollections = (clone $base)
            ->whereDate('warehouse_transactions.created_at', now()->toDateString())
            ->where('warehouse_transactions.type', 'cash_in')
            ->sum('amount');

        // Transactions list
        $transactionsPaginator = (clone $base)->whereIn("source",["cod_collection","pickup_deposit"])
            ->leftJoin('users', 'users.id', '=', 'warehouse_transactions.created_by')
            ->when($from, fn ($q) =>
                $q->where('warehouse_transactions.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->where('warehouse_transactions.created_at', '<=', $to)
            )
            ->orderBy('warehouse_transactions.created_at', 'desc')
            ->paginate(
                $perPage,
                [
                    'warehouse_transactions.created_at as date',
                    'warehouse_transactions.reference',
                    'warehouse_transactions.type',
                    'warehouse_transactions.source',
                    'warehouse_transactions.description',
                    'warehouse_transactions.amount',
                    DB::raw('COALESCE(users.name, "-") as user_name'),
                    'warehouse_transactions.created_by',
                ],
                'transactions_page',
                $transactionsPage
            );



        // Decorate rows for FE
        $ledger = $transactionsPaginator->getCollection()->map(function ($r) {
            $fee = null;
            $cod = null;
            $flag = null;
            $transferAmount = null;
            $transferShipments = [];
            $runsheetDetails = null;

            if ($r->type === 'cash_in') {
                $rsId = $this->extractRunsheetId($r->reference);
                if ($rsId) {
                    $tot = $this->runsheetTotals($rsId);
                    $fee = (float) ($tot['fee'] ?? 0.0);
                    $cod = (float) ($tot['cod'] ?? 0.0);
                    $transferAmount = (float) ($tot['transfer_amount'] ?? 0.0);
                    $transferShipments = $tot['transfer_shipments'] ?? [];
                    if ($transferAmount > 0 && !empty($transferShipments)) {
                        $flag = 'transfer';
                    }
                    $runsheetDetails = $this->getRunsheetDetails($rsId);
                }
            }

            if ($r->created_by && !$runsheetDetails) {
                $runsheetDetails = $this->getDriverRunsheets($r->created_by);
            }

            if (in_array($r->type, ['transfer_in', 'transfer_out'])) {
                $flag = 'transfer';
            }

            return [
                'date' => $r->date,
                'reference' => $r->reference,
                'type' => $r->type,
                'source' => $r->source,
                'description' => $r->description,
                'amount' => (float) $r->amount,
                'fee' => $fee,
                'cod' => $cod,
                'flag' => $flag,
                'transfer_amount' => $transferAmount,
                'transfer_shipments' => $transferShipments,
                'user_name' => $r->user_name,
                'runsheet_details' => $runsheetDetails,
            ];
        })->values();


        $transactionsPaginator->setCollection($ledger);


        $settlementsPaginator = (clone $base)->whereIn("source",["driver_payout","merchant_settlement"])
            ->leftJoin('users', 'users.id', '=', 'warehouse_transactions.created_by')
            ->when($from, fn ($q) =>
                $q->where('warehouse_transactions.created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->where('warehouse_transactions.created_at', '<=', $to)
            )
            ->orderBy('warehouse_transactions.created_at', 'desc')
            ->paginate(
                $perPage,
                [
                    'warehouse_transactions.created_at as date',
                    'warehouse_transactions.reference',
                    'warehouse_transactions.type',
                    'warehouse_transactions.source',
                    'warehouse_transactions.description',
                    'warehouse_transactions.amount',
                    DB::raw('COALESCE(users.name, "-") as user_name'),
                    'warehouse_transactions.created_by',
                ],
                'settlements_page',
                $settlementsPage
            );


        $settlementsLedger = $settlementsPaginator->getCollection()->map(function ($r) {
            $fee = null;
            $cod = null;
            $flag = null;
            $transferAmount = null;
            $transferShipments = [];


            if (in_array($r->type, ['transfer_in', 'transfer_out'])) {
                $flag = 'transfer';
            }

            return [
                'date' => $r->date,
                'reference' => $r->reference,
                'type' => $r->type,
                'source' => $r->source,
                'description' => $r->description,
                'amount' => (float) $r->amount,
                'fee' => $fee,
                'cod' => $cod,
                'flag' => $flag,
                'transfer_amount' => $transferAmount,
                'transfer_shipments' => $transferShipments,
                'user_name' => $r->user_name,
            ];
        })->values();


        $settlementsPaginator->setCollection($settlementsLedger);


        // Merchant settlements (using unified ledger)
        $merchantSettlements = \App\Models\MerchantTransaction::query()
            ->where('type', \App\Models\MerchantTransaction::TYPE_SETTLEMENT)
            ->whereHas('merchant', function ($q) use ($typeClass, $id) {
                $q->where('owner_type', $typeClass)
                ->where('owner_id', $id);
            })
            ->when(
                class_basename($typeClass) === 'User',
                fn ($q) => $q->where('merchant_id', $id),
                fn ($q) => $q->where('merchant_id', '>', 0)
            )
            ->when($from, fn ($q) =>
                $q->where('created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->where('created_at', '<=', $to)
            )
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'settlements_page', $settlementsPage);


        // Driver payouts
        $payouts = Transaction::query()
            ->where('from_id', $id)
            ->where('from_type', $typeClass)
            ->whereHas('to', function ($q) use ($typeClass, $id) {
                $q->where('owner_type', $typeClass)
                ->where('owner_id', $id);
            })
            ->where('type', 'payout')
            ->when($from, fn ($q) =>
                $q->where('created_at', '>=', $from)
            )
            ->when($to, fn ($q) =>
                $q->where('created_at', '<=', $to)
            )
            ->orderByDesc('created_at')
            ->paginate(
                $perPage,
                [
                    'id',
                    'reference',
                    'amount',
                    'created_at',
                    'receipt_path',
                    'to_id',
                    'to_type',
                    'description',
                ],
                'payouts_page',
                $payoutsPage
            );

    $advancesBase = Transaction::query()
        ->where('transactions.from_id', $id)
        ->where('transactions.from_type', $typeClass)
        ->where('transactions.type', 'advance')
        ->when($from, fn ($q) =>
            $q->where('transactions.created_at', '>=', $from)
        )
        ->when($to, fn ($q) =>
            $q->where('transactions.created_at', '<=', $to)
        );

            
        $driverAdvancesTotal = (clone $advancesBase)->sum('transactions.amount');
        // $advancesQuery = Transaction::query()
        //     ->where('from_id', $id)
        //     ->where('from_type', $typeClass)
        //     ->where('type', 'advance')
        //     ->whereBetween('created_at', [$from, $to])
        //     ->leftJoin('users', function ($j) {
        //         $j->on('users.id', '=', 'transactions.to_id');
        //     })
        //     ->orderByDesc('transactions.created_at');

        // $driverAdvancesTotal = (float) (clone $advancesQuery)->sum('transactions.amount');

        $driverAdvances = $advancesBase
            ->leftJoin('users', function ($j) {
                $j->on('users.id', '=', 'transactions.to_id');
                $j->where('transactions.to_type', '=', User::class);
            })
            ->orderByDesc('transactions.created_at')
            ->paginate(
                $advancesPerPage,
                [
                    'transactions.id',
                    'transactions.reference',
                    'transactions.amount',
                    'transactions.description',
                    'transactions.created_at',
                    'transactions.to_id',
                    'transactions.to_type',
                    DB::raw('COALESCE(users.name, "-") as driver_name'),
                ],
                'advances_page',
                $advancesPage
            );

        // Paginated approved financial requests
        $approvedFinancialRequests = (clone $afrQuery)
            ->orderByDesc('approved_at')
            ->paginate(
                $perPage,
                [
                    'id',
                    'code',
                    'type',
                    'payee_id',
                    'period',
                    'amount',
                    'notes',
                    'status',
                    'approved_at',
                    'approved_by',
                    'attachment_path',
                    'created_at',
                ],
                'approved_requests_page',
                $afrPage
            );


        return sendResponse('Account retrieved successfully.', [
            'accountable' => $entity->only(['id', 'name']),
            'summary' => [
                'opening_balance' => $opening,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'expenses' => $expenses,
                'approved_requests_total' => $approvedRequestsTotal,
                'net_balance' => $net,
                'today_collections' => (float) $todayCollections,
                'ibt' => ['in' => $ibtIn, 'out' => $ibtOut],
                'period' => [
                    'from' => $from?->toDateTimeString(),
                    'to' => $to?->toDateTimeString(),
                ],
                'driver_advances' => $driverAdvancesTotal,
            ],
            'transactions' => $transactionsPaginator,
            'settlements' => $settlementsPaginator,
            'expenses' => $expensesPaginator,
            'merchant_settlements' => $merchantSettlements,
            'payouts' => $payouts,
            'approved_financial_requests' => FinancialRequestResource::collection($approvedFinancialRequests),
            'driver_advances' => $driverAdvances,
        ], []);
    }


    /**
     * استخراج الـ runsheet ID من المرجع
     */
    private function extractRunsheetId($reference)
    {
        if (str_starts_with($reference, 'COD-RS-')) {
            $id = (int) str_replace('COD-RS-', '', $reference);
            return $id > 0 ? $id : null;
        }
        return null;
    }

    private function getRunsheetDetails($runsheetId)
    {
        $runsheet = \App\Models\DriverRunsheet::with([
            'driver',
            'assigned_shipments.shipment',
            'assigned_shipments.shipment_finance',
            'submission'
        ])->find($runsheetId);

        if (!$runsheet) {
            return null;
        }

        return [
            'id' => $runsheet->id,
            'driver_id' => $runsheet->driver_id,
            'driver_name' => $runsheet->driver->name ?? 'Unknown',
            'status' => $runsheet->status,
            'confirmed_at' => $runsheet->confirmed_at,
            'holded_at' => $runsheet->holded_at,
            'notes' => $runsheet->notes,
            'created_at' => $runsheet->created_at,
            'updated_at' => $runsheet->updated_at,
            'shipments' => $runsheet->assigned_shipments->map(function ($runsheetShipment) {
                return [
                    'tracking_no' => $runsheetShipment->shipment_tracking_no,
                    'status' => $runsheetShipment->status,
                    'shipment_details' => $runsheetShipment->shipment ? [
                        'customer_name' => $runsheetShipment->shipment->customer_name,
                        'customer_phone' => $runsheetShipment->shipment->customer_phone,
                        'address' => $runsheetShipment->shipment->address,
                        'consignee' => $runsheetShipment->shipment->consignee,
                        'payment_type' => $runsheetShipment->shipment->payment_type,
                        'amount' => $runsheetShipment->shipment->total_cod,
                        'cod_amount' => $runsheetShipment->shipment->cod_amount,
                        'delivery_fee' => $runsheetShipment->shipment->delivery_fee,
                    ] : null,
                    'finance_details' => $runsheetShipment->shipment_finance ? [
                        'collected_amount' => $runsheetShipment->shipment_finance->collected_amount,
                        'remaining_amount' => $runsheetShipment->shipment_finance->remaining_amount,
                        'payment_status' => $runsheetShipment->shipment_finance->payment_status,
                    ] : null,
                ];
            })->toArray(),
            'submission' => $runsheet->submission ? [
                'submitted_at' => $runsheet->submission->submitted_at,
                'received_by' => $runsheet->submission->received_by,
                'received_by_name' => $runsheet->submission->receiver->name ?? 'Unknown',
                'notes' => $runsheet->submission->notes,
            ] : null,
            'statistics' => [
                'total_shipments' => $runsheet->assigned_shipments->count(),
                'delivered_shipments' => $runsheet->delivered_shipments->count(),
                'not_delivered_shipments' => $runsheet->not_delivered_shipments->count(),
                'returned_shipments' => $runsheet->returned_shipments->count(),
                'holding_shipments' => $runsheet->holding_shipments->count(),
            ]
        ];
    }

    private function getDriverRunsheets($userId)
    {
        $user = \App\Models\User::with([
            'driver.runsheet_shipments.runsheet.driver',
            'driver.runsheet_shipments.runsheet.assigned_shipments.shipment',
            'driver.runsheet_shipments.runsheet.submission'
        ])->find($userId);

        if (!$user || !$user->driver) {
            return null;
        }

        return $user->driver->runsheet_shipments->groupBy('runsheet_id')->map(function ($shipments, $runsheetId) {
            $runsheet = $shipments->first()->runsheet;

            return [
                'runsheet_id' => $runsheetId,
                'driver_id' => $runsheet->driver_id,
                'driver_name' => $runsheet->driver->name ?? 'Unknown',
                'status' => $runsheet->status,
                'confirmed_at' => $runsheet->confirmed_at,
                'created_at' => $runsheet->created_at,
                'shipments_count' => $shipments->count(),
                'shipments' => $shipments->map(function ($shipment) {
                    return [
                        'tracking_no' => $shipment->shipment_tracking_no,
                        'status' => $shipment->status,
                        'shipment_details' => $shipment->shipment ? [
                            'customer_name' => $shipment->shipment->customer_name,
                            'customer_phone' => $shipment->shipment->customer_phone,
                            'cod_amount' => $shipment->shipment->cod_amount,
                        ] : null,
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }

    public function showAll(Request $request)
    {
        $accountableClasses = accountables('all');
        if (!$accountableClasses) {
            return sendResponse('No accountable types found', [], false, [], 400);
        }

        $workspaceKey = $request->input('workspace_key');
        $workspaceType = $request->input('workspace_type');
        $workspaceId = null;
        $perPage = $request->input('per_page', 8);

        if ($workspaceKey && $workspaceType) {
            $keyToDecrypt = is_array($workspaceKey) ? $workspaceKey[0] : $workspaceKey;
            $workspaceType = is_array($workspaceType) ? $workspaceType[0] : $workspaceType;
            try {
                $workspaceId = Crypt::decryptString($keyToDecrypt);
            } catch (\Throwable $e) {
            }
        }

        $from = $request->input('from');
        $to = $request->input('to');
        $carbonFrom = $from ? Carbon::parse($from) : now()->startOfDay();
        $carbonTo = $to ? Carbon::parse($to) : now()->endOfDay();

        $typeFilter = $request->query('type');
        $searchFilter = $request->query('search');

        $base = WarehouseTransaction::query()
            ->with('warehouse');

        if ($workspaceId && $workspaceType) {
            $base->where('warehouse_type', $workspaceType)
                ->where('warehouse_id', $workspaceId);
        } else {
            $base->whereIn('warehouse_type', $accountableClasses);
        }

        $typeMap = [
            'ibt_in' => 'transfer_in',
            'ibt_out' => 'transfer_out',
            'cash_in' => 'cash_in',
            'cash_out' => 'cash_out',
            'expense' => 'expense',
        ];
        $effectiveType = $typeFilter ? ($typeMap[$typeFilter] ?? $typeFilter) : null;
        if ($effectiveType) {
            $base->where('type', $effectiveType);
        }

        if ($searchFilter) {
            $base->whereHasMorph('warehouse', $accountableClasses, function ($q) use ($searchFilter) {
                $q->where('name', 'LIKE', "%{$searchFilter}%");
            });
        }

        $openAgg = (clone $base)
            ->where('created_at', '<', $carbonFrom)
            ->selectRaw("
                SUM(CASE WHEN type='cash_in' THEN amount ELSE 0 END) AS open_cash_in,
                SUM(CASE WHEN type='cash_out' THEN amount ELSE 0 END) AS open_cash_out,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS open_expense,
                SUM(CASE WHEN type='transfer_in' THEN amount ELSE 0 END) AS open_ibt_in,
                SUM(CASE WHEN type='transfer_out' THEN amount ELSE 0 END) AS open_ibt_out
            ")
            ->first();

        $opening = $from ? (
            (float) $openAgg->open_cash_in
            + (float) $openAgg->open_ibt_in
            - (float) $openAgg->open_cash_out
            - (float) $openAgg->open_ibt_out
            - (float) $openAgg->open_expense
        ) : 0;
        


        $agg = (clone $base)
            ->whereBetween('created_at', [$carbonFrom, $carbonTo])
            ->selectRaw("
                SUM(CASE WHEN type='cash_in' THEN amount ELSE 0 END) AS cash_in,
                SUM(CASE WHEN type='cash_out' THEN amount ELSE 0 END) AS cash_out,
                SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expenses,
                SUM(CASE WHEN type='transfer_in' THEN amount ELSE 0 END) AS ibt_in,
                SUM(CASE WHEN type='transfer_out' THEN amount ELSE 0 END) AS ibt_out
            ")
            ->first();

        $cashIn = (float) ($agg->cash_in ?? 0);
        $cashOut = (float) ($agg->cash_out ?? 0);
        $expense = (float) ($agg->expenses ?? 0);
        $ibtIn = (float) ($agg->ibt_in ?? 0);
        $ibtOut = (float) ($agg->ibt_out ?? 0);

        $net = $opening + $cashIn + $ibtIn - $cashOut - $ibtOut - $expense;

        $fullQuery = (clone $base)
            ->whereBetween('created_at', [$carbonFrom, $carbonTo]);

        $paginator = (clone $fullQuery)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $rows = $paginator->getCollection();

        $userNames = DB::table('users')
            ->whereIn('id', $rows->pluck('created_by')->filter()->unique()->values())
            ->pluck('name', 'id');

        $ledger = $rows->map(function ($r) use ($userNames) {
            return [
                'date' => $r->created_at,
                'reference' => $r->reference,
                'type' => $r->type,
                'description' => $r->description,
                'amount' => (float) $r->amount,
                'user_name' => $userNames[$r->created_by] ?? '-',
                'workspace_name' => $r->warehouse->name ?? '-',
            ];
        })->values();

        $counts = [
            'collections' => (clone $fullQuery)->where('type', 'cash_in')->count(),
            'expenses' => (clone $fullQuery)->where('type', 'expense')->count(),
            'transfers' => (clone $fullQuery)->whereIn('type', ['transfer_in', 'transfer_out'])->count(),
        ];

        $todaysCollections = (clone $base)
            ->whereDate('created_at', now()->toDateString())
            ->where('type', 'cash_in')
            ->sum('amount');

        $advancesQ = Transaction::query()
            ->where('type', 'advance')
            ->whereBetween('created_at', [$carbonFrom, $carbonTo]);

        if ($workspaceId && $workspaceType) {
            $advancesQ->where('from_type', $workspaceType)
                ->where('from_id', $workspaceId);
        } else {
            $advancesQ->whereIn('from_type', $accountableClasses);
        }
        $driverAdvancesTotal = (float) $advancesQ->sum('amount');

        return sendResponse('All accounts retrieved successfully.', [
            'summary' => [
                'opening_balance' => $opening,
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'expenses' => $expense,
                'net_balance' => $net,
                'ibt' => ['in' => $ibtIn, 'out' => $ibtOut],
                'today_collections' => (float) $todaysCollections,
                'driver_advances' => $driverAdvancesTotal,
                'period' => ['from' => $carbonFrom?->toDateTimeString(), 'to' => $carbonTo?->toDateTimeString()],
            ],
            'counts' => $counts,
            'transactions' => $ledger,
            'links' => $paginator->linkCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], []);
    }


    /**
     * @OA\Get(
     *     path="/accounts/export/{type}/{id}",
     *     summary="Export account details",
     *     description="Exports account details to CSV based on type and ID.",
     *     tags={"Other"},
     *     security={{ "bearerAuth":{ }}},
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         description="Type of accountable (e.g., 'App\\Models\\User')",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the accountable",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account exported successfully",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Account not found."
     *     )
     * )
     */
    public function export($type, $id)
    {
        $type = accountables($type);
        $account = Account::where("accountable_id", $id)
            ->where("accountable_type", $type)
            ->with('accountable')
            ->first();
        $account['sent_transactions'] = Transaction::where('from_id', $id)
            ->where('from_type', $type)
            ->with(['from', 'to', 'shipment'])
            ->get();
        $account['received_transactions'] = Transaction::where('to_id', $id)
            ->where('to_type', $type)
            ->with(['from', 'to', 'shipment'])
            ->get();
        if (!$account) {
            return sendResponse("Account not found.", [], false, [], 500);
        }
        return Excel::download(new AccountExport($account), 'account.csv');
    }
}
