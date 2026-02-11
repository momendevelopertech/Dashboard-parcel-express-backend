<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class FinanceReportsController extends Controller
{
    /**
     * Get comprehensive financial report data
     */
    public function index(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'group_by' => 'nullable|in:day,week,month',
            'report_type' => 'nullable|in:all,drivers,merchants,workspaces',
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();
        $groupBy = $request->input('group_by', 'day');
        $reportType = $request->input('report_type', 'all');

        // Get summary data
        $summary = $this->getReportSummary($from, $to, $reportType);

        // Get trend data
        $trends = $this->getTrendData($from, $to, $groupBy);

        // Get breakdown data
        $breakdown = $this->getBreakdownData($from, $to);

        // Get top performers
        $topPerformers = $this->getTopPerformersData($from, $to);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'trends' => $trends,
                'breakdown' => $breakdown,
                'top_performers' => $topPerformers,
            ],
        ]);
    }

    /**
     * Export financial report
     */
    public function export(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:xlsx,csv,pdf',
            'report_type' => 'nullable|in:all,drivers,merchants,workspaces',
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();
        $format = $request->input('format', 'xlsx');

        $summary = $this->getReportSummary($from, $to, 'all');
        $trends = $this->getTrendData($from, $to, 'day');

        $exportData = [
            ['Financial Report'],
            ['Period:', $from->format('Y-m-d') . ' to ' . $to->format('Y-m-d')],
            [''],
            ['Summary'],
            ['Total Revenue', $summary['total_revenue'] ?? 0],
            ['Total COD Collected', $summary['total_cod_collected'] ?? 0],
            ['Total Fees Earned', $summary['total_fees_earned'] ?? 0],
            ['Total Settlements Paid', $summary['total_settlements_paid'] ?? 0],
            ['Total Expenses', $summary['total_expenses'] ?? 0],
            ['Net Profit', $summary['net_profit'] ?? 0],
            [''],
            ['Daily Trends'],
            ['Date', 'Revenue', 'Expenses', 'Collections', 'Settlements'],
        ];

        foreach ($trends as $trend) {
            $exportData[] = [
                $trend['date'],
                $trend['revenue'] ?? 0,
                $trend['expenses'] ?? 0,
                $trend['collections'] ?? 0,
                $trend['settlements'] ?? 0,
            ];
        }

        $export = new class($exportData) implements \Maatwebsite\Excel\Concerns\FromArray {
            public function __construct(private $data) {}
            public function array(): array { return $this->data; }
        };

        $fileName = 'financial_report_' . now()->format('Y_m_d_H_i_s');

        if ($format === 'csv') {
            return Excel::download($export, $fileName . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download($export, $fileName . '.xlsx');
    }

    /**
     * Get cash flow report
     */
    public function cashflow(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'group_by' => 'nullable|in:day,week,month',
            'workspace_id' => 'nullable|integer',
        ]);

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to = Carbon::parse($request->input('to'))->endOfDay();
        $groupBy = $request->input('group_by', 'day');

        // Calculate inflows (COD collections, pickup deposits, etc.)
        $inflows = $this->calculateInflows($from, $to);

        // Calculate outflows (settlements, expenses, driver payouts)
        $outflows = $this->calculateOutflows($from, $to);

        // Get daily data
        $dailyData = $this->getDailyCashflowData($from, $to, $groupBy);

        // Calculate opening and closing balance
        $openingBalance = $this->getBalanceAtDate($from->copy()->subDay());
        $closingBalance = $openingBalance + $inflows['total'] - $outflows['total'];

        return response()->json([
            'success' => true,
            'data' => [
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'net_change' => $inflows['total'] - $outflows['total'],
                'inflows' => $inflows,
                'outflows' => $outflows,
                'daily_data' => $dailyData,
            ],
        ]);
    }

    /**
     * Get report summary
     */
    private function getReportSummary($from, $to, $reportType)
    {
        // Total COD collected
        $totalCod = DB::table('merchant_transactions')
            ->where('type', 'cod_collected')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        // Total fees earned
        $totalFees = DB::table('merchant_transactions')
            ->whereIn('type', ['delivery_fee', 'return_fee', 'pickup_fee'])
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum(DB::raw('ABS(amount)'));

        // Total settlements paid
        $totalSettlements = DB::table('merchant_transactions')
            ->where('type', 'settlement')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum(DB::raw('ABS(amount)'));

        // Total expenses
        $totalExpenses = DB::table('expenses')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $totalRevenue = $totalCod + $totalFees;
        $netProfit = $totalFees - $totalExpenses;

        return [
            'total_revenue' => (float) $totalRevenue,
            'total_cod_collected' => (float) $totalCod,
            'total_fees_earned' => (float) $totalFees,
            'total_settlements_paid' => (float) $totalSettlements,
            'total_expenses' => (float) $totalExpenses,
            'net_profit' => (float) $netProfit,
        ];
    }

    /**
     * Get trend data grouped by period
     */
    private function getTrendData($from, $to, $groupBy)
    {
        $dateFormat = match($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $trends = DB::table('merchant_transactions')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as date"),
                DB::raw("SUM(CASE WHEN type = 'cod_collected' AND status = 'completed' THEN amount ELSE 0 END) as collections"),
                DB::raw("SUM(CASE WHEN type IN ('delivery_fee', 'return_fee', 'pickup_fee') AND status = 'completed' THEN ABS(amount) ELSE 0 END) as revenue"),
                DB::raw("SUM(CASE WHEN type = 'settlement' AND status = 'completed' THEN ABS(amount) ELSE 0 END) as settlements")
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '{$dateFormat}')"))
            ->orderBy('date')
            ->get();

        // Get expenses by date
        $expenses = DB::table('expenses')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as date"),
                DB::raw("SUM(amount) as expenses")
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '{$dateFormat}')"))
            ->pluck('expenses', 'date');

        return $trends->map(function ($trend) use ($expenses) {
            return [
                'date' => $trend->date,
                'revenue' => (float) $trend->revenue,
                'expenses' => (float) ($expenses[$trend->date] ?? 0),
                'collections' => (float) $trend->collections,
                'settlements' => (float) $trend->settlements,
            ];
        });
    }

    /**
     * Get breakdown data by type
     */
    private function getBreakdownData($from, $to)
    {
        $byType = [
            [
                'name' => 'COD Collections',
                'value' => (float) DB::table('merchant_transactions')
                    ->where('type', 'cod_collected')
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$from, $to])
                    ->sum('amount'),
            ],
            [
                'name' => 'Fees Earned',
                'value' => (float) DB::table('merchant_transactions')
                    ->whereIn('type', ['delivery_fee', 'return_fee', 'pickup_fee'])
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$from, $to])
                    ->sum(DB::raw('ABS(amount)')),
            ],
            [
                'name' => 'Settlements',
                'value' => (float) DB::table('merchant_transactions')
                    ->where('type', 'settlement')
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$from, $to])
                    ->sum(DB::raw('ABS(amount)')),
            ],
            [
                'name' => 'Expenses',
                'value' => (float) DB::table('expenses')
                    ->whereBetween('created_at', [$from, $to])
                    ->sum('amount'),
            ],
        ];

        return [
            'by_type' => $byType,
            'by_workspace' => [], // Can be implemented if workspace tracking is needed
        ];
    }

    /**
     * Get top performers data
     */
    private function getTopPerformersData($from, $to, $limit = 5)
    {
        // Top drivers by delivered count
        $drivers = DB::table('shipments')
            ->join('users', 'shipments.driver_id', '=', 'users.id')
            ->select(
                'shipments.driver_id as id',
                'users.name',
                DB::raw("COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_count"),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN value ELSE 0 END) as total_value")
            )
            ->whereNotNull('shipments.driver_id')
            ->whereBetween('shipments.created_at', [$from, $to])
            ->groupBy('shipments.driver_id', 'users.name')
            ->orderByDesc('delivered_count')
            ->limit($limit)
            ->get();

        // Top merchants by delivered count
        $merchants = DB::table('shipments')
            ->join('users', 'shipments.merchant_id', '=', 'users.id')
            ->select(
                'shipments.merchant_id as id',
                'users.name',
                DB::raw("COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_count"),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN value ELSE 0 END) as total_value")
            )
            ->whereNotNull('shipments.merchant_id')
            ->whereBetween('shipments.created_at', [$from, $to])
            ->groupBy('shipments.merchant_id', 'users.name')
            ->orderByDesc('delivered_count')
            ->limit($limit)
            ->get();

        return [
            'drivers' => $drivers->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'delivered_count' => (int) $d->delivered_count,
                'total_value' => (float) $d->total_value,
            ]),
            'merchants' => $merchants->map(fn($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'delivered_count' => (int) $m->delivered_count,
                'total_value' => (float) $m->total_value,
            ]),
        ];
    }

    /**
     * Calculate inflows
     */
    private function calculateInflows($from, $to)
    {
        $codCollections = DB::table('merchant_transactions')
            ->where('type', 'cod_collected')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $pickupDeposits = DB::table('merchant_transactions')
            ->where('type', 'pickup_deposit')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $other = 0; // Add other inflow sources if needed

        return [
            'total' => (float) ($codCollections + $pickupDeposits + $other),
            'breakdown' => [
                'cod_collections' => (float) $codCollections,
                'pickup_deposits' => (float) $pickupDeposits,
                'other' => (float) $other,
            ],
        ];
    }

    /**
     * Calculate outflows
     */
    private function calculateOutflows($from, $to)
    {
        $settlements = DB::table('merchant_transactions')
            ->where('type', 'settlement')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum(DB::raw('ABS(amount)'));

        $expenses = DB::table('expenses')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $driverPayouts = DB::table('driver_transactions')
            ->where('type', 'payout')
            ->whereBetween('created_at', [$from, $to])
            ->sum(DB::raw('ABS(amount)'));

        return [
            'total' => (float) ($settlements + $expenses + $driverPayouts),
            'breakdown' => [
                'settlements' => (float) $settlements,
                'expenses' => (float) $expenses,
                'driver_payouts' => (float) $driverPayouts,
            ],
        ];
    }

    /**
     * Get daily cashflow data
     */
    private function getDailyCashflowData($from, $to, $groupBy)
    {
        $dateFormat = match($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        // Get inflows by date
        $inflows = DB::table('merchant_transactions')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as date"),
                DB::raw("SUM(CASE WHEN type IN ('cod_collected', 'pickup_deposit') AND status = 'completed' THEN amount ELSE 0 END) as inflow")
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '{$dateFormat}')"))
            ->pluck('inflow', 'date');

        // Get outflows by date (settlements)
        $outflows = DB::table('merchant_transactions')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as date"),
                DB::raw("SUM(CASE WHEN type = 'settlement' AND status = 'completed' THEN ABS(amount) ELSE 0 END) as outflow")
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '{$dateFormat}')"))
            ->pluck('outflow', 'date');

        // Get expenses by date
        $expenses = DB::table('expenses')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as date"),
                DB::raw("SUM(amount) as expense")
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '{$dateFormat}')"))
            ->pluck('expense', 'date');

        // Combine all dates
        $allDates = collect($inflows->keys())
            ->merge($outflows->keys())
            ->merge($expenses->keys())
            ->unique()
            ->sort();

        $runningBalance = $this->getBalanceAtDate($from->copy()->subDay());

        return $allDates->map(function ($date) use ($inflows, $outflows, $expenses, &$runningBalance) {
            $inflow = (float) ($inflows[$date] ?? 0);
            $outflow = (float) ($outflows[$date] ?? 0) + (float) ($expenses[$date] ?? 0);
            $net = $inflow - $outflow;
            $runningBalance += $net;

            return [
                'date' => $date,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => $net,
                'running_balance' => $runningBalance,
            ];
        })->values();
    }

    /**
     * Get balance at a specific date
     */
    private function getBalanceAtDate($date)
    {
        $merchantBalance = DB::table('merchant_transactions')
            ->where('status', 'completed')
            ->where('created_at', '<=', $date)
            ->sum('amount');

        return (float) $merchantBalance;
    }
}

