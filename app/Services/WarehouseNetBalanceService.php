<?php

namespace App\Services;

use App\Models\{
    WarehouseTransaction,
    Expense,
    FinancialRequest
};
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class WarehouseNetBalanceService
{

    private function signedApprovedRequestsTotal($query): float
    {
        $add = (clone $query)->where('type', '!=', 'branch_settlement')->sum('amount');
        $deduct = (clone $query)->where('type', 'branch_settlement')->sum('amount');

        return (float) $add - (float) $deduct;
    }

    
    public function calculate(
        ?Carbon $from = null,
        ?Carbon $to = null
    ) {
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
        $perPage = (int) (request()->query('per_page') ?? request()->query('perPage') ?? 8);
        $expensesPage = (int) request()->query('expenses_page', 1);


        // FE type → DB type map
        $filterType = request()->query('type');
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


        /** ---------------- Final net ---------------- */
        return (float) (
            $net
        );
    }
}
