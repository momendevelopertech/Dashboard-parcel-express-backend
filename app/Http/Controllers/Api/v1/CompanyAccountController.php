<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreCompanyAccountRequest;
use App\Http\Requests\UpdateCompanyAccountRequest;
use App\Models\Company;
use App\Models\CompanyAccount;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\FinancialRequest;
use App\Models\ShipmentFeeAllocation;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanyAccountController extends Controller
{
    public function index()
    {
        $accounts = CompanyAccount::with('company')->orderBy('id', 'desc')->paginate(10);
        return sendResponse('Company Accounts', $accounts);
    }

    // CompanyAccountController.php

    public function show($companyId, Request $request)
    {
        $account = CompanyAccount::firstOrCreate(['company_id' => $companyId], ['balance' => 0]);
        $account->load('company');

        $from = $request->query('from') ? \Carbon\Carbon::parse($request->query('from'))->startOfDay() : null;
        $to = $request->query('to') ? \Carbon\Carbon::parse($request->query('to'))->endOfDay() : null;

        $fr = DB::table('financial_requests')
            ->when(
                Schema::hasColumn('financial_requests', 'payee_type') && Schema::hasColumn('financial_requests', 'payee_id'),
                fn($q) => $q->where(function ($qq) use ($companyId) {
                    $qq->where('payee_type', \App\Models\Company::class)->where('payee_id', $companyId);
                }),
                fn($q) => $q->when(Schema::hasColumn('financial_requests', 'company_id'), fn($qq) => $qq->where('company_id', $companyId))
                    ->when(Schema::hasColumn('financial_requests', 'payee_company_id'), fn($qq) => $qq->orWhere('payee_company_id', $companyId))
            )
            ->where('status', 'approved')
            ->when($from, fn($q) => $q->where('approved_at', '>=', $from))
            ->when($to, fn($q) => $q->where('approved_at', '<=', $to))
            ->orderBy('approved_at', 'asc')
            ->get([
                'id',
                'code',
                'type',
                'amount',
                'notes',
                'approved_at',
                'created_at'
            ]);

            $totalPendingSettlements=FinancialRequest::where("status","pending")->count();
            $totalBranchExpenses=Expense::sum("amount");

            $typeDebit = [
                'incoming', 'cash_in', 'deposit', 'transfer_in', 'ibt_in', 'receive', 'collection',
                'branch_settlement', 'driver_salary', 'other'
            ];

            $typeCredit = [
                'outgoing', 'cash_out', 'expense', 'transfer_out', 'ibt_out', 'payout', 'salary', 'payment',
                'merchant_settlement'
];

        $ledger = [];
        $running = 0.0;
        $totDebit = 0.0;
        $totCredit = 0.0;
        $totalMerchantSettlements=0.0;
        $totalDriverSalaries=0.0;
        $branchSettlements=0.0;

        foreach ($fr as $row) {
            $t = strtolower((string) $row->type);
            $amt = (float) $row->amount;

            $debit = in_array($t, $typeDebit, true) ? $amt : 0.0;
            $credit = in_array($t, $typeCredit, true) ? $amt : 0.0;

            if ($debit === 0.0 && $credit === 0.0) {
                if ($amt >= 0)
                    $debit = $amt;
                else
                    $credit = abs($amt);
            }

            $totDebit += $debit;
            $totCredit += $credit;
            $running += ($debit - $credit);

            if($row->type=='merchant_settlement')
             $totalMerchantSettlements+=$credit;
            else if($row->type=='driver_salary')
             $totalDriverSalaries+=$credit;
            else if($row->type=='branch_settlement')
             $branchSettlements+=$debit;

            $ledger[] = [
                'date' => $row->approved_at ?? $row->created_at,
                'statement' => $row->code ?: $row->type,
                'details' => $row->notes ?? '',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
                'raw_type' => $row->type,
                'id' => $row->id,
            ];
        }

        // summary
        $summary = [
            'inflows' => $totDebit,
            'outflows' => $totCredit,
            'merchant_settlement' => $totalMerchantSettlements,
            'driver_salary' => $totalDriverSalaries,
            'branch_settlement' => $branchSettlements,
            'pending_settlements' => $totalPendingSettlements,
            'branchExpenses' => $totalBranchExpenses,
            'net' => $totDebit - $totCredit,
            'opening' => 0.0, // if you later add a real opening, plug it here
            'balance' => $running,
            'period' => [
                'from' => $from?->toDateTimeString(),
                'to' => $to?->toDateTimeString(),
            ],
        ];

        // tab counts like your screenshot header
        $tabsCounts = [
            'all' => count($ledger),
            'incoming' => collect($ledger)->where('debit', '>', 0)->count(),
            'outgoing' => collect($ledger)->where('credit', '>', 0)->count(),
        ];

        // keep previous payload shape & add ledger block
        return sendResponse('Company Account', [
            'account' => $account,
            'company' => $account->company,
            'balance' => $summary['balance'],
            'summary' => $summary,
            'ledger' => $ledger,
            'tabs_counts' => $tabsCounts,
        ]);
    }


    public function store(StoreCompanyAccountRequest $request)
    {
        $request->validated();
        try {
            $account = CompanyAccount::firstOrCreate([
                'company_id' => $request->company_id,
            ]);
        } catch (QueryException $e) {
            return sendResponse('Error Occured.', [], [$e->getMessage()], 422);
        }
        return sendResponse('Company Account created.', $account);
    }

    public function recalculate($companyId)
    {
        // Sum company_amount from shipment_fee_allocations for shipments whose pickup/delivery driver is in the company
        // Join allocations -> shipments by tracking_no -> drivers by driver_id
        $sum = DB::table('shipment_fee_allocations as ofa')
            ->join('shipments as o', 'ofa.shipment_tracking_no', '=', 'o.tracking_no')
            ->leftJoin('drivers as pd', 'ofa.pickup_driver_id', '=', 'pd.id')
            ->leftJoin('drivers as dd', 'ofa.delivery_driver_id', '=', 'dd.id')
            ->where(function ($q) use ($companyId) {
                $q->where('pd.company_id', $companyId)
                    ->orWhere('dd.company_id', $companyId);
            })
            ->sum('ofa.company_amount');

        $account = CompanyAccount::firstOrCreate(['company_id' => $companyId]);
        $account->balance = $sum;
        $account->save();

        return sendResponse('Company Acc ount recalculated.', $account);
    }


}


