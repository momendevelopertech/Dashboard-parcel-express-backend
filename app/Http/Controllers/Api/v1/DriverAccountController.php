<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;


use App\Exports\DriverAccountsExport;
use Illuminate\Validation\ValidationException;
use App\Models\Account;
use App\Models\Advance;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\DriverInvoice;
use App\Models\Hub;
use App\Models\WarehouseTransaction;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\DriverBonus;
use App\Models\DriverBonusesTransaction;
use App\Models\DriverShipmentAssignment;
use App\Models\DriverRunsheetSubmission;
use App\Models\PickuptaskTransaction;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\DriverService;
use App\Enums\ShipmentStatusEnum;
use Illuminate\Http\UploadedFile;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\WarehouseNetBalanceService;
use Mpdf\Mpdf;

class DriverAccountController extends Controller
{

    private function calcDeliveredCodForAssignment(\App\Models\DriverShipmentAssignment $a): float
    {
        $shipment = $a->shipment;
        if (!$shipment)
            return 0.0;

        // Centralized collectible logic
        return (float) $shipment->getDriverCollectibleAmount();
    }

    /**
     * List all drivers with their account summaries.
     * Pagination is by DRIVERS (not transactions) - per_page controls number of drivers shown.
     */
    public function index(Request $request, DriverService $driverService)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'workspace_id' => 'nullable',
            'workspace_type' => 'nullable',
            'type' => 'nullable|string',
        ]);

        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $from = $request->input('from');
        $to = $request->input('to');
        $type = strtolower($request->input('type')) !== 'all' ? strtolower($request->input('type')) : null;

        $ids = $request->input('workspace_id', []);
        $types = $request->input('workspace_type', []);

        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?? [];
        }

        if (is_string($types)) {
            $types = json_decode($types, true) ?? [];
        }

        $workspaces = [];
        foreach ($ids as $i => $id) {
            if (!empty($id) && !empty($types[$i])) {
                $workspaces[] = [
                    'id' =>  $id,
                    'type' => $types[$i],
                ];
            }
        }

        // Fallback
        if (empty($workspaces)) {
            $workspaces[] = [
                'id' => facility()->id,
                'type' => facility()->type,
            ];
        }

        // Build query for drivers - PAGINATE DRIVERS directly, not transactions
        $driversQuery = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'driver'))
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->when(!empty($workspaces), function ($query) use ($workspaces) {
                $query->where(function ($sub) use ($workspaces) {
                    foreach ($workspaces as $ws) {
                        $sub->orWhere(function ($or) use ($ws) {
                            $or->where('owner_id', $ws['id'])
                            ->where('owner_type', $ws['type']);
                        });
                    }
                });
            })
            ->latest();





        // Paginate DRIVERS (not transactions) - this ensures per_page controls drivers count
        $driversPaginator = $driversQuery->paginate($perPage);

        // Get all driver IDs (without pagination) for global stats
        $allDriverIds = (clone $driversQuery)->pluck('id');

        // Calculate total bonuses for all matching drivers (not just current page)
        $totalBonusesAllDrivers = (float) DriverBonusesTransaction::whereIn('driver_id', $allDriverIds)
            ->where('active', true)
            ->sum('bonus_amount');

        // Build data for each driver in the current page
        $data = $driversPaginator->getCollection()->map(function ($driver) use ($from, $to, $type, $driverService, $request) {
            $totalCOD = DriverShipmentAssignment::query()
                ->where('driver_id', $driver->id)
                ->where('status', ShipmentStatusEnum::DELIVERED)
                ->when($from, fn($q) => $q->where('delivered_at', '>=', $from))
                ->when($to, fn($q) => $q->where('delivered_at', '<=', $to))
                // ->whereHas('shipment', fn($q) => $q->where('payment_type', '=', 'Paid'))
                ->with('shipment:id,fee_payer,delivery_fee,payment_type,value,total_cod')
                ->get()
                ->sum(fn($assignment) => $this->calcDeliveredCodForAssignment($assignment));

            $totalDeposit = (float) Transaction::where('from_id', $driver->id)
                ->where('from_type', User::class)
                ->where('type', 'deposit')
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to))
                ->sum('amount');

            $totalPenalitiesAndDeductions = (float) Transaction::where('to_id', $driver->id)
                ->where('from_type', Hub::class)
                ->where('type', 'fine')
                ->where("isPaid", 0)
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to))
                ->sum('amount');

            $totalBonusQuery = DriverBonusesTransaction::where('driver_id', $driver->id)
                ->where('status', 'delivered')
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to));

            $totalDeliveryBonus = (clone $totalBonusQuery)
                ->where('action', 'delivery')
                ->sum('bonus_amount');

            $totalPickupBonus = (clone $totalBonusQuery)
                ->where('action', 'pickup')
                ->sum('bonus_amount');

            // Calculate total bonus: sum of all active driver_bonuses_transactions
            $totalBonus = (float) (clone $totalBonusQuery)->where("isPaid", 0)->sum('bonus_amount');

            $currentBalance = $totalCOD - $totalDeposit;


            // Get transaction count for display
            $transactionCount = Transaction::where(function ($q) use ($driver) {
                $q->where('from_id', $driver->id)->where('from_type', User::class)
                    ->orWhere(function ($q2) use ($driver) {
                        $q2->where('to_id', $driver->id)->where('to_type', User::class);
                    });
            })
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to))
                ->when($type, fn($q) => $q->where('type', $type))
                ->count();

            // Pickup deposits
            $pickuptaskQuery = PickuptaskTransaction::query()
                ->whereHas('pickuptask', fn($q) => $q->where('driver_id', $driver->id))
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to));

            $pickup_deposits_from_merchants = (float) $pickuptaskQuery->sum("amount");
            $pickup_deposits_remitted_cash = (float) $pickuptaskQuery->sum("paid_by_bank");
            $pickup_deposits_remitted_bank = (float) $pickuptaskQuery->sum("paid_by_cash");
            $pickup_deposits_remitted = $pickup_deposits_remitted_cash + $pickup_deposits_remitted_bank;
            $isPaid = 0;
            $totalSettlementDue = $driverService->calculateSettlementDue($driver, null, null, $isPaid);
            $applyStatus = !empty($status) && strtolower($status) !== 'all';




            $carbonFrom = $from ? \Carbon\Carbon::parse($from) : null;
            $carbonTo = $to ? \Carbon\Carbon::parse($to) : null;

            $applyDateRange = function ($query, string $column) use ($carbonFrom, $carbonTo) {
                return $query
                    ->when($carbonFrom && $carbonTo, fn($q) => $q->whereBetween($column, [$carbonFrom, $carbonTo]))
                    ->when($carbonFrom && !$carbonTo, fn($q) => $q->where($column, '>=', $carbonFrom))
                    ->when(!$carbonFrom && $carbonTo, fn($q) => $q->where($column, '<=', $carbonTo));
            };
            $pickupRef = $request->input('pickup_ref');

            $advanceQ = Transaction::query()
                ->where('to_id', $driver->id)
                ->where('to_type', User::class)
                ->where('type', 'advance')
                ->when($applyStatus, fn($q) => $q->whereNull('id'))
                ->when($pickupRef, fn($q) => $q->whereRaw('1=0'));
            $applyDateRange($advanceQ, 'created_at');
            $totalAdvance = (float) $advanceQ->where("isPaid", 0)->sum('amount');

            $totalPayouts = (float) Transaction::where('to_id', $driver->id)
                ->where('to_type', User::class)
                ->where('type', 'payout')
                ->where("isPaid", 0)
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to))
                ->sum('amount');
            $isPaid = 0;
            $settlementDue  = $driverService->calculateSettlementDue($driver, $from, $to, $isPaid);


            $deliveredShipments = DriverShipmentAssignment::where('driver_id', $driver->id)->where('status', ShipmentStatusEnum::DELIVERED)->count();

            return [
                'driver' => [
                    'id' => $driver->id,
                    'name' => $driver->name,
                    'phone' => $driver->phone ?? null,
                ],
                'summary' => [
                    'cod_collection_from_consignees' => $totalCOD,
                    'cod_remitted_to_company' => $totalDeposit,
                    'uncollected_cod' => $totalCOD - $totalDeposit,
                    'current_balance' => $currentBalance,
                    'total_pickup_bonus' => $totalPickupBonus,
                    'total_delivery_bonus' => $totalDeliveryBonus,
                    'comission_earnings' => $totalBonus,
                    'cash_advances_issued' =>  $totalAdvance,
                    'total_penalities_and_deductions' => $totalPenalitiesAndDeductions,
                    'pickup_deposits_from_merchants' => (float) $pickup_deposits_from_merchants, //Money received from merchants by drivers and not remitted yet
                    'pickup_deposits_remitted' => (float)  $pickup_deposits_remitted, //Money received from merchants by drivers and remitted
                    'uncollected_pickup_deposits' => $pickup_deposits_from_merchants - $pickup_deposits_remitted,
                    'earnings_net_balance' => $totalBonus - ($totalAdvance + $totalPenalitiesAndDeductions),
                    'total_settlement_due' => $totalSettlementDue,
                    'settlement_due' => $settlementDue,
                    'delivered_shipments' => $deliveredShipments,
                ]
            ];
        });

        return response()->json([
            'data' => $data,
            'links' => $driversPaginator->linkCollection(),
            'meta' => [
                'current_page' => $driversPaginator->currentPage(),
                'last_page' => $driversPaginator->lastPage(),
                'per_page' => $driversPaginator->perPage(),
                'total' => $driversPaginator->total(),
                'from' => $driversPaginator->firstItem(),
                'to' => $driversPaginator->lastItem(),
                'total_bonuses_all_drivers' => $totalBonusesAllDrivers,
            ],
        ]);
    }
    private function getDriverAccountDetails(User $driver, $from, $to)
    {
        // 1) COD من التسليمات (وليس من submissions)
        $deliveredCOD = \App\Models\DriverShipmentAssignment::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'DELIVERED')
            ->whereBetween('delivered_at', [$from, $to])
            ->whereHas('shipment', function ($q) {
                // اعتبر كل غير "Paid" = COD (عدّل حسب عندك)
                $q->where('payment_type', '!=', 'Paid');
            })
            ->with(['shipment.shipment_delivery'])
            ->get()
            ->sum(fn($a) => $this->calcDeliveredCodForAssignment($a))
            ->sum(function ($a) {
                $od = optional($a->shipment)->shipment_delivery;
                return (float) ($od->payment_cash ?? 0) + (float) ($od->payment_bank_transfer ?? 0);
            });

        // 2) إجمالي الإيداعات (كما هي)
        $totalDeposit = (float) Transaction::where('from_id', $driver->id)
            ->where('from_type', User::class)
            ->where('type', 'deposit')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // 3) Total bonus from driver_bonuses_transactions where active = true
        $totalBonus = DriverBonusesTransaction::driverBonuses($driver->id);

        // 4) مصروفات payouts (كما هي)
        $totalPayouts = (float) Transaction::where('to_id', $driver->id)
            ->where('to_type', User::class)
            ->where('type', 'payout')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // 5) الرصيد الحالي = تسليمات COD - الإيداعات
        $currentBalance = $deliveredCOD - $totalDeposit;

        // 6) جدول المعاملات (كما هو)
        $tx = Transaction::where(function ($q) use ($driver) {
            $q->where(fn($qq) => $qq->where('from_id', $driver->id)->where('from_type', User::class))
                ->orWhere(fn($qq) => $qq->where('to_id', $driver->id)->where('to_type', User::class));
        })
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($r) {
                return [
                    'created_at' => $r->created_at,
                    'reference' => $r->reference,
                    'type' => $r->type,
                    'description' => $r->description,
                    'amount' => (float) $r->amount,
                    'receipt_url' => $r->receipt_path
                ];
            });

        $pickup_deposits_from_merchants = $pickuptaskQuery->sum("amount");

        $pickup_deposits_cash = $pickuptaskQuery->sum("paid_by_cash");
        $pickup_deposits_bank = $pickuptaskQuery->sum("paid_by_cash");

        $pickup_deposits_remitted = $pickup_deposits_cash + $pickup_deposits_bank;

        return [
            'driver' => $driver->only(['id', 'name']),
            'summary' => [
                'total_cod' => (float) $deliveredCOD,
                'total_deposit' => (float) $totalDeposit,
                'total_bonus' => (float) $totalBonus,
                'total_payouts' => (float) $totalPayouts,
                'current_balance' => (float) $currentBalance,
                'pickup_deposits_from_merchants' => $pickup_deposits_from_merchants,
                'pickup_deposits_remitted' => $pickup_deposits_remitted,
            ],
            'transactions' => $tx,
        ];
    }


    protected function paginateCollection($collection, $perPage, $page)
    {
        $page = $page ?: (LengthAwarePaginator::resolveCurrentPage() ?: 1);
        $items = $collection->slice(($page - 1) * $perPage, $perPage)->values();
        return new LengthAwarePaginator($items, $collection->count(), $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
    }
    /**
     * Build driver account with combined transactions and summary for show endpoint
     */
    private function buildDriverAccount(User $driver, Request $request, DriverService $driverService)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $status = trim($request->input('status'));
        $type = trim($request->input('type'));
        $trackingNo = trim($request->input('tracking_no'));
        $pickupRef = $request->input('pickup_ref');
        $actionBonus = $request->input('action_bonus');

        $carbonFrom = $from ? \Carbon\Carbon::parse($from) : null;
        $carbonTo = $to ? \Carbon\Carbon::parse($to) : null;

        $typesInput = $request->input('type');
        $types = ($typesInput && strtolower($typesInput) !== 'all')
            ? collect(is_array($typesInput) ? $typesInput : explode(',', $typesInput))
            ->map(fn($v) => trim($v))
            ->filter()
            ->values()
            ->all()
            : [];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(5, (int) $request->input('per_page', 15)));

        $applyStatus = !empty($status) && strtolower($status) !== 'all';
        $applyTrackingNo = !empty($trackingNo);
        $includePickupDeposit = empty($types) || in_array('pickup_deposit', $types);

        $applyDateRange = function ($query, string $column) use ($carbonFrom, $carbonTo) {
            return $query
                ->when($carbonFrom && $carbonTo, fn($q) => $q->whereBetween($column, [$carbonFrom, $carbonTo]))
                ->when($carbonFrom && !$carbonTo, fn($q) => $q->where($column, '>=', $carbonFrom))
                ->when(!$carbonFrom && $carbonTo, fn($q) => $q->where($column, '<=', $carbonTo));
        };

        // =====================
        // COD Assignments Query
        // =====================
        $codQuery = DriverShipmentAssignment::query()
            ->where('driver_shipment_assignments.driver_id', $driver->id)
            // ->whereHas('shipment', fn ($q) => $q->where('payment_type', '!=', 'Paid'))
            ->when(
                $pickupRef,
                fn($q) => $q->join('merchant_pickup_shipments as mps', 'mps.shipment_id', '=', 'driver_shipment_assignments.shipment_id')
                    ->join('merchant_pickup_tasks as mpt', 'mpt.id', '=', 'mps.pickup_task_id')
                    ->where('mpt.ref', 'like', "%{$pickupRef}%")
            );

        $listQuery = clone $codQuery;
        $listQuery->when($applyStatus, fn($q) => $q->where('driver_shipment_assignments.status', $status));
        $applyDateRange($listQuery, 'delivered_at');

        $totalCOD = $listQuery
            ->where('driver_shipment_assignments.status', ShipmentStatusEnum::DELIVERED)
            ->with(['shipment:id,fee_payer,delivery_fee,payment_type,value,total_cod'])
            ->get()
            ->sum(fn($assignment) => $this->calcDeliveredCodForAssignment($assignment));

        // ===============================
        // Other Totals (penalties, deposit)
        // ===============================
        $baseQuery = Transaction::query()
            ->where('to_id', $driver->id)
            ->where('from_type', Hub::class)
            ->where('type', 'fine')
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to));

        $totalPenaltiesUnpaid = (float) (clone $baseQuery)
            ->where('isPaid', 0)
            ->sum('amount');

        $totalPenaltiesAll = (float) (clone $baseQuery)
            ->sum('amount');


        $depositQ = Transaction::query()
            ->where('from_id', $driver->id)
            ->where('from_type', User::class)
            ->where('type', 'deposit');
        $applyDateRange($depositQ, 'created_at');
        $totalDeposit = (float) $depositQ->sum('amount');

        // ===============================
        // Bonus Queries
        // ===============================
        $totalBonusQuery = DriverBonusesTransaction::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to));


        $totalDeliveryBonus = (clone $totalBonusQuery)->where('action', 'delivery')->sum('bonus_amount');
        $totalPickupBonus = (clone $totalBonusQuery)->where('action', 'pickup')->sum('bonus_amount');


        $baseQuery = clone $totalBonusQuery;

        $totalBonusAll = (float) (clone $baseQuery)
            ->sum('bonus_amount');

        $baseQuery = Transaction::query()
            ->where('to_id', $driver->id)
            ->where('to_type', User::class)
            ->where('type', 'advance')
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to));

        $totalAdvanceAll = (float) (clone $baseQuery)
            ->sum('amount');


        $totalPayouts = Transaction::query()
            ->where('to_id', $driver->id)
            ->where('to_type', User::class)
            ->where('isPaid', 0)
            ->where('type', 'payout')
            ->sum('amount');

        $currentBalance = $totalCOD - $totalDeposit;

        // ===============================
        // Transactions Queries
        // ===============================
        $txQuery = Transaction::query()
            ->where(function ($q) use ($driver) {
                $q->where(fn($qq) => $qq->where('from_id', $driver->id)->where('from_type', User::class))
                    ->orWhere(fn($qq) => $qq->where('to_id', $driver->id)->where('to_type', User::class));
            })
            ->with('advance')
            ->when(!empty($types), fn($q) => $q->whereIn('type', $types))
            ->when($applyTrackingNo, fn($q) => $q->whereHas('shipment', fn($qq) => $qq->where('tracking_no', $trackingNo)->orWhere('pre_id', $trackingNo)));
        $applyDateRange($txQuery, 'created_at');
        $basicTransactions = $txQuery->orderByDesc('created_at')->get();

        $bonusQuery = DriverBonusesTransaction::query()
            ->where('driver_id', $driver->id)
            ->where('active', true)
            ->when(!empty($actionBonus), fn($q) => $q->where('action', $actionBonus))
            ->when($applyStatus, fn($q) => $q->where('status', $status))
            ->when($applyTrackingNo, fn($q) => $q->whereHas('shipment', fn($qq) => $qq->where('tracking_no', $trackingNo)->orWhere('pre_id', $trackingNo)));
        $applyDateRange($bonusQuery, 'created_at');
        $bonusTransactions = $bonusQuery->orderByDesc('created_at')->get();

        $pickuptaskTransactions = collect();

        $pickupQuery = PickuptaskTransaction::query()
            ->whereHas('pickuptask', fn($q) => $q->where('driver_id', $driver->id));
        $applyDateRange($pickupQuery, 'created_at');
        $pickup_deposits_from_merchants = (float) $pickupQuery->sum("amount");
        $pickup_deposits_remitted_cash = (float) $pickupQuery->sum("paid_by_bank");
        $pickup_deposits_remitted_bank = (float) $pickupQuery->sum("paid_by_cash");
        $pickup_deposits_remitted = $pickup_deposits_remitted_cash + $pickup_deposits_remitted_bank;
        $pickuptaskTransactions = $pickupQuery->orderByDesc('created_at')->get();

        // ===============================
        // Map to arrays after filtering
        // ===============================
        $basicTransactions = $basicTransactions->map(fn($r) => [
            'created_at' => $r->created_at,
            'reference' => $r->reference,
            'type' => $r->type,
            'status' => $r->status,
            'description' => $r->description,
            'shipment_status' => $r->shipment?->status,
            'shipment_tracking_no' => $r->shipment?->tracking_no ?? $r->shipment?->pre_id,
            'amount' => (float) $r->amount,
            'payment_type' => $r->shipment?->payment_type,
            'total_cod' => (float) $r->shipment?->total_cod,
            'receipt_url' => $r->receipt_path
                ? (\Illuminate\Support\Str::startsWith($r->receipt_path, ['http://', 'https://'])
                    ? $r->receipt_path
                    : \Illuminate\Support\Facades\Storage::disk('public')->url($r->receipt_path))
                : null,
            'advance' => $r->advance ? [
                'id' => $r->advance->id,
                'voucher_no' => $r->advance->voucher_no,
                'notes' => $r->advance->notes,
                'image' => $r->advance->image,
            ] : null,
        ]);

        $bonusTransactions = $bonusTransactions->map(fn($b) => [
            'created_at' => $b->created_at,
            'reference' => null,
            'type' => 'bonus',
            'action' => $b->action,
            'status' => $b->status,
            'shipment_status' => $b->shipment?->status,
            'shipment_tracking_no' => $b->shipment?->tracking_no,
            'description' => $b->action === 'pickup'
                ? match ($b->status) {
                    'pending' => 'Pickup bonus is pending and has not been sent yet for traking no. ' . $b->shipment?->tracking_no,
                    'delivered' => 'Pickup bonus has been sent to you.',
                    'cancelled' => 'Pickup bonus was cancelled.',
                    default => 'Pickup bonus status: ' . ucfirst((string)$b->status),
                }
                : 'Driver bonus transaction',
            'amount' => (float) $b->bonus_amount,
            'payment_type' => $b->shipment?->payment_type,
            'total_cod' => (float) $b->shipment?->total_cod,
            'receipt_url' => null,
        ]);

        $pickuptaskTransactions = $pickuptaskTransactions->map(fn($ptt) => [
            'created_at' => $ptt->created_at,
            'reference' => $ptt->pickuptask->ref,
            'type' => 'pickup_deposit',
            'action' => null,
            'status' => null,
            'shipment_status' => null,
            'shipment_tracking_no' => null,
            'description' => "Pickup deposit for {$ptt->pickuptask->ref}",
            'amount' => (float) $ptt->amount,
            'payment_type' => null,
            'total_cod' => null,
            'receipt_url' => null,
        ]);

        // ===============================
        // Merge, Sort & Paginate
        // ===============================
        // 2️⃣ Merge all transactions
        $allTransactions = $basicTransactions
            ->concat($bonusTransactions)
            ->concat($pickuptaskTransactions)
            ->filter(function ($tx) use ($from, $to, $status, $trackingNo, $pickupRef, $type) {
                // Ensure we work with arrays
                $tx = is_array($tx) ? $tx : (array) $tx;

                $txDate       = $tx['created_at'] ?? null;
                $txStatus     = $tx['status'] ?? null;
                $txTrackingNo = $tx['shipment_tracking_no'] ?? null;
                $txPickupRef  = $tx['reference'] ?? null;
                $txType       = $tx['type'] ?? null;

                $matchDate = true;
                if ($from) $matchDate = $matchDate && ($txDate >= $from);
                if ($to)   $matchDate = $matchDate && ($txDate <= $to);

                $matchStatus   = $status ? ($txStatus === $status) : true;
                $matchTracking = $trackingNo ? ($txTrackingNo === $trackingNo) : true;
                $matchPickup   = $pickupRef ? ($txPickupRef === $pickupRef) : true;
                $matchType = (!$type || $type === 'All')
                    ? true
                    : ($txType === $type);

                return $matchDate
                    && $matchStatus
                    && $matchTracking
                    && $matchPickup
                    && $matchType;
            })
            ->sortByDesc(fn($tx) => $tx['created_at'])
            ->values();


        $paginatedTransactions = $this->paginateCollection($allTransactions, $perPage, $page);

        $isPaid = 0;
        $settlementDue  = $driverService->calculateSettlementDue($driver, $from, $to, $isPaid);

        $totalSettlementDue = $driverService->calculateSettlementDue($driver, null, null, $isPaid);

        return [
            'driver' => $driver->only(['id', 'name']),
            'summary' => [
                'pickup_deposits_from_merchants' => (float) $pickup_deposits_from_merchants,
                'pickup_deposits_remitted' => (float)  $pickup_deposits_remitted,
                'uncollected_pickup_deposits' => $pickup_deposits_from_merchants - $pickup_deposits_remitted,
                'cod_collection_from_consignees' => $totalCOD,
                'cod_remitted_to_company' => $totalDeposit,
                'uncollected_cod' => $totalCOD - $totalDeposit,
                'current_balance' => $currentBalance,
                'total_pickup_bonus' => $totalPickupBonus,
                'total_delivery_bonus' => $totalDeliveryBonus,
                'comission_earnings' => $totalBonusAll,
                'cash_advances_issued' => $totalAdvanceAll,
                'total_penalities_and_deductions' => $totalPenaltiesAll,
                'earnings_net_balance' => $totalBonusAll - ($totalAdvanceAll + $totalPenaltiesAll),
                'total_settlement_due' => $totalSettlementDue,
                'settlement_due' => $settlementDue,
                'total_payouts' => $totalPayouts,
            ],
            'filters_applied' => [
                'from' => $carbonFrom?->toDateTimeString(),
                'to' => $carbonTo?->toDateTimeString(),
                'type' => $types,
                'status' => $status ?: 'all',
                'tracking_no' => $trackingNo ?: null,
            ],
            'transactions' => $paginatedTransactions->items(),
            'pagination' => [
                'current_page' => $paginatedTransactions->currentPage(),
                'per_page' => $paginatedTransactions->perPage(),
                'last_page' => $paginatedTransactions->lastPage(),
                'total' => $paginatedTransactions->total(),
                'from' => $paginatedTransactions->firstItem(),
                'to' => $paginatedTransactions->lastItem(),
            ],
        ];
    }


    public function show(Request $request, User $driver)
    {
        $result = $this->buildDriverAccount($driver, $request, app(DriverService::class));
        return sendResponse("Driver account retrieved.", $result, []);
    }

    /**
     * Get total bonuses earned by driver (sum of all active driver_bonuses_transactions)
     *
     * @param User $driver
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTotalBonus(User $driver)
    {
        if (!$driver->driver) {
            return sendResponse("User is not a driver.", [], false, [], 422);
        }

        // Calculate total bonus: sum of all active driver_bonuses_transactions
        $totalBonus = (float) DriverBonusesTransaction::driverBonuses($driver->id);

        return sendResponse("Total bonus retrieved.", [
            'driver_id' => $driver->id,
            'total_bonus' => $totalBonus,
        ], []);
    }

    public function storePayout(Request $request, User $driver, DriverService $driverService)
    {
        $facilityId = Auth::user()->owner_id;
        $facilityType = Auth::user()->owner_type;
        $net = app(WarehouseNetBalanceService::class)
            ->calculate();

        if (!$driver->driver) {
            return sendResponse("User is not a driver.", [], false, [], 422);
        }

        $from = $request->input('from');
        $to = $request->input('to');
        $isPaid = 0;

        $settlementDue  = $driverService->calculateSettlementDue($driver, $from, $to, $isPaid);

        $maxPayable = min($settlementDue, $net);
        // 🔹 Validation (receipt REMOVED)
        $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:'.$settlementDue,
                'max:'.$settlementDue,
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date'],
        ]);

        /** Precise business error */
        if ((float) $request->amount > $maxPayable + 1e-6) {

            // Identify the limiting factor
            if ($net < $settlementDue) {
                $reason = __(
                    'Insufficient facility balance. Available balance is :net',
                    ['net' => number_format($net, 2)]
                );
            } else {
                $reason = __(
                    'Amount exceeds driver settlement due of :due',
                    ['due' => number_format($settlementDue, 2)]
                );
            }

            throw ValidationException::withMessages([
                'amount' => __(
                    'Entered amount (:entered) is not allowed. :reason',
                    [
                        'entered' => number_format($request->amount, 2),
                        'reason'  => $reason,
                    ]
                ),
            ]);
        }


        $ref = 'PAY-' . now()->format('ymd-His') . '-' . strtoupper(Str::random(4));

        /**
         * -------------------------------------------------------------
         * Original payout logic (UNCHANGED)
         * -------------------------------------------------------------
         */
        DB::beginTransaction();
        try {
            $payoutTx = Transaction::create([
                'from_id' => $facilityId,
                'from_type' => $facilityType,
                'to_id' => $driver->id,
                'to_type' => User::class,
                'type' => 'payout',
                'isPaid' => 1,
                'amount' => (float) $request->input('amount'),
                'reference' => $ref,
                'description' => $request->input('notes') ?? 'Driver payout',
                'receipt_path' => null,
                'receipt_uploaded_by' => Auth::id(),
                'receipt_uploaded_at' => now(),
                'created_by' => Auth::id(),
            ]);


            WarehouseTransaction::create([
                'warehouse_id' => $facilityId,
                'warehouse_type' => $facilityType,
                'source' => 'driver_payout',
                'type' => 'cash_out',
                'amount' => (float) $request->input('amount'),
                'reference' => $ref,
                'description' => "Driver #{$driver->id} payout",
                'created_by' => Auth::id(),
            ]);

            //add invoice of driver to driver invoices table
            $driverInvoice = DriverInvoice::create([
                'invoice_file_path' => null,
                'amount' => $request->input('amount'),
                'driver_id' => $driver->id,
                'created_by' => Auth::id(),
            ]);


            $totalBonusQuery = DriverBonusesTransaction::query()
                ->where('driver_id', $driver->id)
                ->where('status', 'delivered')
                ->where('isPaid', 0)
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to));

                $bonusFrom = (clone $totalBonusQuery)->min('created_at');
                $bonusTo   = (clone $totalBonusQuery)->max('created_at');
    
    
            $totalDeliveryBonus = (clone $totalBonusQuery)->where('action', 'delivery')->sum('bonus_amount');
            $totalPickupBonus = (clone $totalBonusQuery)->where('action', 'pickup')->sum('bonus_amount');
            $deliverShipments = driverDeliverSettleShipments($driverInvoice, $from, $to);
            $pickupShipments = driverPickupSettleShipments($driverInvoice, $from, $to);
    


            $transactionBaseQuery = Transaction::query()
            ->where('to_id', $driver->id)
            ->where('to_type', User::class)
            ->where('isPaid', 0)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

            $totalPenaltiesUnpaid = (float) (clone $transactionBaseQuery)
            ->where('type', 'fine')
            ->sum('amount');

            $totalAdvanceUnpaid = (float) (clone $transactionBaseQuery)
            ->where('type', 'advance')
            ->sum('amount');
    
            $txFrom = (clone $transactionBaseQuery)->min('created_at');
            $txTo   = (clone $transactionBaseQuery)->max('created_at');
    
            (clone $transactionBaseQuery)->update([
                'isPaid' => 1,
            ]);


            $from = collect([$bonusFrom, $txFrom, $from ?? null])->filter()->min();
            $to   = collect([$bonusTo,   $txTo,   $to   ?? null])->filter()->max();

            // ----------------------------------
            // Generate Driver Invoice (mPDF)
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

            $html = view('pdf.driver-invoice', [
                'driver' => $driver,
                'from' => $from,
                'to' => $to,
                'amount' => $request->input('amount'),
                'total_penalities' => $totalPenaltiesUnpaid,
                'total_advances' => $totalAdvanceUnpaid,
                'pickup_shipments' => $pickupShipments,
                'deliver_shipments' => $deliverShipments,
                'totalDeliveryBonus' => $totalDeliveryBonus,
                'totalPickupBonus' => $totalPickupBonus,
                'date' => now()->format('Y-m-d H:i'),
            ])->render();

            $mpdf->WriteHTML($html);

            // 1️⃣ Save mPDF output
            $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '.pdf';
            $mpdf->Output($tempPath, \Mpdf\Output\Destination::FILE);

            // 2️⃣ Convert to UploadedFile
            $uploadedFile = new UploadedFile(
                $tempPath,
                'payout_' . now()->format('Ymd_His') . '.pdf',
                'application/pdf',
                null,
                true
            );

            // 3️⃣ Upload (same storage logic)
            $dir = 'public/driver_payouts/' . now()->format('Y/m');
            $uploadedPath = uploadFile($uploadedFile, $dir);
 
            // 4️⃣ Cleanup temp file
            @unlink($tempPath);

            if (!$uploadedPath) {
                return sendResponse("Error generating receipt.", [], false, ["Receipt upload failed."], 500);
            }

            $receiptUrl = $uploadedPath;

            $payoutTx->update(["receipt_path" => $receiptUrl]);
            $driverInvoice->update(["invoice_file_path" => $receiptUrl]);

            // settle oldest bonus credits first
            $remain = (float) $request->input('amount');
            $bonusTx = Transaction::where('to_id', $driver->id)
                ->where('to_type', User::class)
                ->where('type', 'bonus_credit')
                ->whereNull('settled_at')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($bonusTx as $bt) {
                if ($remain <= 0) break;

                $amt = (float) $bt->amount;
                if ($amt <= $remain + 1e-6) {
                    $bt->settled_at = now();
                    $bt->payout_id = $payoutTx->id;
                    $bt->save();
                    $remain -= $amt;
                } else {
                    break;
                }
            }

            DB::commit();

            return sendResponse("Payout recorded.", [
                'payout' => [
                    'id' => $payoutTx->id,
                    'reference' => $payoutTx->reference,
                    'amount' => (float) $payoutTx->amount,
                    'receipt' => $uploadedPath,
                ]
            ], []);
        } catch (\Throwable $e) {
            DB::rollBack();

            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }


     public function multiPayout(Request $request, DriverService $driverService)
    {

        $search = $request->input('search');
        $from = $request->input('from');
        $to = $request->input('to');
        $type = strtolower($request->input('type')) !== 'all' ? strtolower($request->input('type')) : null;

        $ids = $request->input('workspace_id', []);
        $types = $request->input('workspace_type', []);

        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?? [];
        }

        if (is_string($types)) {
            $types = json_decode($types, true) ?? [];
        }

        $workspaces = [];
        foreach ($ids as $i => $id) {
            if (!empty($id) && !empty($types[$i])) {
                $workspaces[] = [
                    'id' =>  $id,
                    'type' => $types[$i],
                ];
            }
        }

        // Fallback
        if (empty($workspaces)) {
            $workspaces[] = [
                'id' => facility()->id,
                'type' => facility()->type,
            ];
        }

        // Build query for drivers - PAGINATE DRIVERS directly, not transactions
        $drivers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'driver'))
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->when(!empty($workspaces), function ($query) use ($workspaces) {
                $query->where(function ($sub) use ($workspaces) {
                    foreach ($workspaces as $ws) {
                        $sub->orWhere(function ($or) use ($ws) {
                            $or->where('owner_id', $ws['id'])
                            ->where('owner_type', $ws['type']);
                        });
                    }
                });
            })
            ->get();


        $facilityId = Auth::user()->owner_id;
        $facilityType = Auth::user()->owner_type;
        $net = app(WarehouseNetBalanceService::class)
            ->calculate();

            $payoutTotal=0;
            foreach($drivers as $driver){
            $due= $driverService->calculateSettlementDue($driver, $from, $to, 0);
            if($due>0){
                $payoutTotal +=$due;
            }

            }


        $maxPayable = min($payoutTotal, $net);


        /** Precise business error */
        if ((float) $request->amount > $maxPayable + 1e-6) {

            // Identify the limiting factor
            if ($net < $payoutTotal) {
                $reason = __(
                    'Insufficient facility balance. Available balance is :net',
                    ['net' => number_format($net, 2)]
                );
            } 

            throw ValidationException::withMessages([
                'amount' => __(
                    'Entered amount (:entered) is not allowed. :reason',
                    [
                        'entered' => number_format($request->amount, 2),
                        'reason'  => $reason,
                    ]
                ),
            ]);
        }


        /**
         * -------------------------------------------------------------
         * Original payout logic (UNCHANGED)
         * -------------------------------------------------------------
         */
        DB::beginTransaction();
        try {

        $createdInvoices = [];

        foreach($drivers as $driver){
            
            if (!$driver->driver) {
                return sendResponse("User is not a driver.", [], false, [], 422);
            }
    
            $from = $request->input('from');
            $to = $request->input('to');
            $isPaid = 0;
    
            $settlementDue  = $driverService->calculateSettlementDue($driver, $from, $to, $isPaid);

            // ✅ Skip drivers with no payable balance
            if ($settlementDue <= 0) {
                continue;
            }
    
            $ref = 'PAY-' . now()->format('ymd-His') . '-' . strtoupper(Str::random(4));
            $payoutTx = Transaction::create([
                'from_id' => $facilityId,
                'from_type' => $facilityType,
                'to_id' => $driver->id,
                'to_type' => User::class,
                'type' => 'payout',
                'isPaid' => 1,
                'amount' => (float) $settlementDue,
                'reference' => $ref,
                'description' => $request->input('notes') ?? 'Driver payout',
                'receipt_path' => null,
                'receipt_uploaded_by' => Auth::id(),
                'receipt_uploaded_at' => now(),
                'created_by' => Auth::id(),
            ]);
    
    
            WarehouseTransaction::create([
                'warehouse_id' => $facilityId,
                'warehouse_type' => $facilityType,
                'source' => 'driver_payout',
                'type' => 'cash_out',
                'amount' => (float) $settlementDue,
                'reference' => $ref,
                'description' => "Driver #{$driver->id} payout",
                'created_by' => Auth::id(),
            ]);
    
            //add invoice of driver to driver invoices table
            $driverInvoice = DriverInvoice::create([
                'invoice_file_path' => null,
                'amount' => $settlementDue,
                'driver_id' => $driver->id,
                'created_by' => Auth::id(),
            ]);
    
    
            $totalBonusQuery = DriverBonusesTransaction::query()
                ->where('driver_id', $driver->id)
                ->where('status', 'delivered')
                ->where('isPaid', 0)
                ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn($q) => $q->where('created_at', '<=', $to));

                $bonusFrom = (clone $totalBonusQuery)->min('created_at');
                $bonusTo   = (clone $totalBonusQuery)->max('created_at');
    
    
            $totalDeliveryBonus = (clone $totalBonusQuery)->where('action', 'delivery')->sum('bonus_amount');
            $totalPickupBonus = (clone $totalBonusQuery)->where('action', 'pickup')->sum('bonus_amount');
            $deliverShipments = driverDeliverSettleShipments($driverInvoice, $from, $to);
            $pickupShipments = driverPickupSettleShipments($driverInvoice, $from, $to);
    


            $transactionBaseQuery = Transaction::query()
            ->where('to_id', $driver->id)
            ->where('to_type', User::class)
            ->where('isPaid', 0)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

            $totalPenaltiesUnpaid = (float) (clone $transactionBaseQuery)
            ->where('type', 'fine')
            ->sum('amount');

            $totalAdvanceUnpaid = (float) (clone $transactionBaseQuery)
            ->where('type', 'advance')
            ->sum('amount');
    
            $txFrom = (clone $transactionBaseQuery)->min('created_at');
            $txTo   = (clone $transactionBaseQuery)->max('created_at');
    
            (clone $transactionBaseQuery)->update([
                'isPaid' => 1,
            ]);


            $from = collect([$bonusFrom, $txFrom, $from ?? null])->filter()->min();
            $to   = collect([$bonusTo,   $txTo,   $to   ?? null])->filter()->max();
    
                // ----------------------------------
                // Generate Driver Invoice (mPDF)
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
    
            $html = view('pdf.driver-invoice', [
                'driver' => $driver,
                'from' => $from,
                'to' => $to,
                'amount' => $settlementDue,
                'total_penalities' => $totalPenaltiesUnpaid,
                'total_advances' => $totalAdvanceUnpaid,
                'pickup_shipments' => $pickupShipments,
                'deliver_shipments' => $deliverShipments,
                'totalDeliveryBonus' => $totalDeliveryBonus,
                'totalPickupBonus' => $totalPickupBonus,
                'date' => now()->format('Y-m-d H:i'),
            ])->render();
    
            $mpdf->WriteHTML($html);
    
            // 1️⃣ Save mPDF output
            $tempPath = sys_get_temp_dir() . '/' . Str::uuid() . '.pdf';
            $mpdf->Output($tempPath, \Mpdf\Output\Destination::FILE);
    
            // 2️⃣ Convert to UploadedFile
            $uploadedFile = new UploadedFile(
                $tempPath,
                'payout_' . now()->format('Ymd_His') . '.pdf',
                'application/pdf',
                null,
                true
            );
    
            // 3️⃣ Upload (same storage logic)
            $dir = 'public/driver_payouts/' . now()->format('Y/m');
            $uploadedPath = uploadFile($uploadedFile, $dir);
    
            // 4️⃣ Cleanup temp file
            @unlink($tempPath);
    
            if (!$uploadedPath) {
                return sendResponse("Error generating receipt.", [], false, ["Receipt upload failed."], 500);
            }
    
            $receiptUrl = $uploadedPath;

            array_push($createdInvoices,$uploadedPath);
    
            $payoutTx->update(["receipt_path" => $receiptUrl]);
            $driverInvoice->update(["invoice_file_path" => $receiptUrl]);
    
            // settle oldest bonus credits first
            $remain = (float) $settlementDue;
            $bonusTx = Transaction::where('to_id', $driver->id)
                ->where('to_type', User::class)
                ->where('type', 'bonus_credit')
                ->whereNull('settled_at')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();
    
            foreach ($bonusTx as $bt) {
                if ($remain <= 0) break;
    
                $amt = (float) $bt->amount;
                if ($amt <= $remain + 1e-6) {
                    $bt->settled_at = now();
                    $bt->payout_id = $payoutTx->id;
                    $bt->save();
                    $remain -= $amt;
                } else {
                    break;
                }
            }
        }


            DB::commit();

            return sendResponse("Payouts recorded.", [
                $createdInvoices
            ], []);
        } catch (\Throwable $e) {
            DB::rollBack();

            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }



    


    // POST /driver-accounts/{driver}/deposits
    public function storeDeposit(Request $request, User $driver)
    {
        if (!$driver->driver) {
            return sendResponse("User is not a driver.", [], false, [], 422);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:2000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        // المستودع/المنشأة الحالية من مستخدم السيشن
        $facilityType = Auth::user()->owner_type; // Branch::class | Station::class | Hub::class
        $facilityId = Auth::user()->owner_id;

        // حسابات
        $driverAcc = Account::where('accountable_id', $driver->id)->where('accountable_type', User::class)->lockForUpdate()->firstOrFail();
        $facilityAcc = Account::where('accountable_id', $facilityId)->where('accountable_type', $facilityType)->lockForUpdate()->firstOrFail();

        $amount = (float) $request->amount;

        // ممنوع يودع أكتر من اللي معاه (اختياري)
        if ($amount > (float) $driverAcc->cash_balance) {
            return sendResponse("Amount exceeds driver's cash balance.", [], false, [], 422);
        }

        DB::beginTransaction();
        try {
            $attachmentPath = null;
            if ($request->hasFile('receipt')) {
                $attachmentPath = uploadFile($request->file('receipt'), 'public/driver_deposits');
            }

            $ref = 'DEP-' . now()->format('ymd') . '-' . Str::upper(Str::random(4));

            // سجل معاملة عامة
            $tx = Transaction::create([
                'from_id' => $driver->id,
                'from_type' => User::class,
                'to_id' => $facilityId,
                'to_type' => $facilityType,
                'warehouse_id' => $facilityId,               // لو عندك مستودع = نفس الـ owner
                'type' => 'deposit',
                'amount' => $amount,
                'reference' => $ref,
                'description' => $request->input('notes', 'Driver cash deposit'),
                'attachment' => $attachmentPath,
                'created_by' => Auth::id(),
            ]);

            // قيد في مستودع الكاش
            WarehouseTransaction::create([
                'warehouse_id' => $facilityId,
                'type' => 'cash_in',
                'amount' => $amount,
                'reference' => $ref,
                'description' => "Driver #{$driver->id} deposit",
                'created_by' => Auth::id(),
            ]);

            // تحديث أرصدة الحسابات
            $driverAcc->cash_balance = (float) $driverAcc->cash_balance - $amount;
            $facilityAcc->cash_balance = (float) $facilityAcc->cash_balance + $amount;
            $driverAcc->save();
            $facilityAcc->save();

            DB::commit();

            return sendResponse("Deposit recorded.", [
                'transaction' => $tx,
                'driver_balance' => (float) $driverAcc->cash_balance,
            ], []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    // GET /driver-accounts/{driver}/export?from=...&to=...
    public function export(Request $request, User $driver)
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()))->endOfDay();

        $tx = Transaction::query()
            ->where(function ($q) use ($driver) {
                $q->where(fn($qq) => $qq->where('from_id', $driver->id)->where('from_type', User::class))
                    ->orWhere(fn($qq) => $qq->where('to_id', $driver->id)->where('to_type', User::class));
            })
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get(['created_at', 'reference', 'type', 'description', 'amount']);

        $rows = $tx->map(function ($r) {
            return [
                'date' => $r->created_at->toDateTimeString(),
                'reference' => $r->reference,
                'type' => $r->type,
                'description' => $r->description,
                'amount' => (float) $r->amount,
            ];
        });

        // CSV بسيط بدون Laravel-Excel لتقليل الإضافات
        $filename = "driver_account_{$driver->id}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];
        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Reference', 'Type', 'Description', 'Amount']);
            foreach ($rows as $row)
                fputcsv($out, $row);
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
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
            $type = strtolower($request->input('type')) !== 'all' ? strtolower($request->input('type')) : null;
            $drivers = User::query()
                ->whereHas('roles', fn($q) => $q->where('name', 'driver'))
                ->when($search, fn($q) => $q->where('name', 'like', "%$search%"))
                ->when($workspaceKey && $workspaceType, function ($query) use ($workspaceKey, $workspaceType) {
                    $workspaceId = Crypt::decryptString(is_array($workspaceKey) ? $workspaceKey[0] : $workspaceKey);
                    $workspaceType = is_array($workspaceType) ? $workspaceType[0] : $workspaceType;
                    if ($workspaceId && $workspaceType) {
                        $query->whereHas('account', function ($q) use ($workspaceId, $workspaceType) {
                            $q->where('owner_id', $workspaceId)->where('owner_type', $workspaceType);
                        });
                    }
                })
                ->get();

            if ($drivers->isEmpty()) {
                return sendResponse("No drivers found matching the filter criteria.", [], [], 404);
            }

            $allTransactions = collect();

            $drivers->each(function ($driver) use ($from, $to, $type, &$allTransactions) {
                $transactionsQuery = Transaction::where(function ($q) use ($driver) {
                    $q->where(fn($qq) => $qq->where('from_id', $driver->id)->where('from_type', User::class))
                        ->orWhere(fn($qq) => $qq->where('to_id', $driver->id)->where('to_type', User::class));
                })
                    ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                    ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
                    ->when($type, fn($q) => $q->where('type', $type));

                $transactions = $transactionsQuery->get();
                $transactions->each(function ($transaction) use ($driver, &$allTransactions) {
                    $allTransactions->push([
                        'driver_name' => $driver->name,
                        'created_at' => $transaction->created_at->toDateTimeString(),
                        'reference' => $transaction->reference,
                        'type' => $transaction->type,
                        'description' => $transaction->description,
                        'amount' => (float) $transaction->amount,
                    ]);
                });
            });

            if ($allTransactions->isEmpty()) {
                return sendResponse("No transactions found matching the filter criteria.", [], [], 404);
            }

            $export = new DriverAccountsExport($allTransactions);
            $fileName = "driver_accounts_export_" . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download($export, $fileName);
        } catch (\Exception $e) {
            return sendResponse("Error occurred during export", [], [$e->getMessage()], 422);
        }
    }
    public function storeAdvance(Request $request, User $driver)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'voucher_no' => 'required|string|max:20|unique:advances,voucher_no',
            'notes' => 'nullable|string|max:2000',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $facilityType = Auth::user()->owner_type;
        $facilityId = Auth::user()->owner_id;

        DB::beginTransaction();
        try {

            $attachmentPath = null;
            if ($request->hasFile('image')) {
                $attachmentPath = uploadFile($request->file('image'), 'public/driver_advances');
            }
            $ref = 'ADV-' . now()->format('ymd-His') . '-' . strtoupper(Str::random(4));

            $advance = Advance::create([
                'driver_id' => $driver->id,
                'warehouse_id' => $facilityId,
                'warehouse_type' => $facilityType,
                'amount' => $request->amount,
                'voucher_no' => $request->voucher_no,
                'reference' => $ref,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'image' => $attachmentPath,
            ]);
            $advance->transaction()->create([
                'from_id' => $facilityId,
                'from_type' => $facilityType,
                'to_id' => $driver->id,
                'to_type' => User::class,
                'type' => 'advance',
                'amount' => $request->amount,
                'reference' => $ref,
                'description' => $request->notes ?? 'Driver advance',
                'created_by' => Auth::id(),
            ]);
            activityLog('advance created', "new advance request created for {$driver->name}");
            DB::commit();

            return sendResponse("Advance recorded.", $advance, []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    public function updateAdvanceImage(Request $request, Advance $advance)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        try {
            $attachmentPath = null;
            if ($request->hasFile('image')) {
                $attachmentPath = uploadFile($request->file('image'), 'public/driver_advances');
            }

            $advance->update(['image' => $attachmentPath]);

            activityLog('advance image updated', "Advance #{$advance->id} image updated");

            return sendResponse("Advance image updated.", $advance->fresh(), []);
        } catch (\Throwable $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }
}
