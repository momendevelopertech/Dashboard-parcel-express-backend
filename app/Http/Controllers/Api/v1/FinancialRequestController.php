<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreFinancialRequest;
use App\Http\Resources\FinancialRequestResource;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Models\WarehouseTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class FinancialRequestController extends Controller
{

        private function signedApprovedRequestsTotal($query): float
    {
        $add = (clone $query)->where('type', '!=', 'branch_settlement')->sum('amount');
        $deduct = (clone $query)->where('type', 'branch_settlement')->sum('amount');

        return (float) $add - (float) $deduct;
    }

    
    public function index(Request $r)
    {
        $query = FinancialRequest::query()
            ->where('owner_id', facility('id'))
            ->where('owner_type', facility('type'))
            ->when($r->status, fn($q) => $q->where('status', $r->status))
            ->when($r->type, fn($q) => $q->where('type', $r->type))
            ->latest('id');

        $perPage = (int) ($r->perPage ?? 10);
        $paginator = $query->paginate($perPage);

        $resource = FinancialRequestResource::collection($paginator);
        $normalized = $resource->response()->getData(true);
        $base = FinancialRequest::query()
            ->where('owner_id', facility('id'))
            ->where('owner_type', facility('type'));

        $counters = [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];

        return sendResponse('OK', [
            'list' => $normalized,
            'links' => $normalized['links'],
            'counters' => $counters,
        ]);
    }

    public function getNetBalanceForCurrentWarehouse(Request $r) {
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
        $perPage = (int) ($r->query('per_page') ?? $r->query('perPage') ?? 8);
        $expensesPage = (int) $r->query('expenses_page', 1);


        // FE type → DB type map
        $filterType = $r->query('type');
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
        $fromParam = $r->query('from');
        $toParam = $r->query('to');
        try {
            $from = $fromParam ? Carbon::parse($fromParam) : null;
            $to = $toParam ? Carbon::parse($toParam) : null;
        } catch (\Throwable $e) {
            return sendResponse('Invalid date format provided.', [], false, [], 400);
        }

        // Base WT query
        $base = WarehouseTransaction::query()
            ->where('warehouse_transactions.warehouse_type', $typeClass)
            ->where('warehouse_transactions.warehouse_id', $id);

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


        return sendResponse('OK', [
            'net_balance' => $net,
        ]);
    }

    public function store(StoreFinancialRequest $req)
    {
        $user = auth()->user();
        $payload = $req->validated();
        

        DB::beginTransaction();
        try {
            $payeeId = null;

            if (!empty($payload['payee_ids'])) {
                if (is_array($payload['payee_ids'])) {
                    // If array has multiple IDs, join them as comma-separated
                    $payeeId = count($payload['payee_ids']) > 1 
                        ? implode(',', $payload['payee_ids']) 
                        : $payload['payee_ids'][0]; // single element
                } else {
                    // If it's a single value (not array)
                    $payeeId = $payload['payee_ids'];
                }
            }

            // 3️⃣ Upload (finanical_proof)
            $dir = 'public/financial_proofs/' . now()->format('Y/m');
            $uploadedFile=$req->file("financial_proof");
            $uploadedPath = uploadFile($uploadedFile, $dir);

            $fr = FinancialRequest::create([
                'code' => FinancialRequest::nextCode(),
                'owner_id' => facility('id'),
                'owner_type' => facility('type'),
                'type' => $payload['type'],
                'payee_id' => $payeeId,
                'financial_proof' => $uploadedPath,
                'period_date' => $payload['period_date'] ?? null,
                'amount' => $payload['amount'],
                'notes' => $payload['notes'] ?? null,
                'status' => 'pending',
                'created_by' => $user->id,
                'created_by' => $user->id,
            ]);
             activityLog('financial request created', "new financial request with code : {$fr->code} created");
            DB::commit();
            return sendResponse(
                'Created',
                new FinancialRequestResource($fr),
                true,
                [],
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse('Failed', ['error' => $e->getMessage()], false, [], 500);
        }
    }

    public function approve(Request $r, FinancialRequest $financialRequest)
    {
        if ($financialRequest->status !== 'pending') {
            return sendResponse('Already reviewed', [], false, [], 422);
        }
        $financialRequest->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        activityLog('financial request approved', "financial request approved with code : {$financialRequest->code}");
        return sendResponse('Approved', new FinancialRequestResource($financialRequest));
    }

    public function reject(Request $r, FinancialRequest $financialRequest)
    {
        Gate::authorize('review', $financialRequest);
        if ($financialRequest->status !== 'pending') {
            return sendResponse('Already reviewed', [], false, [], 422);
        }
        $financialRequest->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'notes' => trim($financialRequest->notes . "\nRejected reason: " . ($r->reason ?? '')),
        ]);
        activityLog('financial request rejected', "financial request rejected with code : {$financialRequest->code}");
        return sendResponse('Rejected', new FinancialRequestResource($financialRequest));
    }
}
