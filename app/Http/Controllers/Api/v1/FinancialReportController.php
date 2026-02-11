<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\FinancialReportResource;
use App\Services\TimezoneService;

/**
 * @OA\Tag(
 *     name="Other",
 *     description="Financial Report Controller"
 * )
 */
class FinancialReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/financial-reports",
     *     summary="Get financial report",
     *     description="Retrieves financial report data with summary metrics, chart data, and daily breakdown.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for the report (optional, defaults to one month prior)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for the report (optional, defaults to today)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="granularity",
     *         in="query",
     *         description="Granularity of the report (daily, weekly, monthly, defaults to daily)",
     *         @OA\Schema(type="string", enum={"daily", "weekly", "monthly"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Financial report retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving financial report"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'granularity' => 'in:daily,weekly,monthly',
                'tz' => 'nullable|string',
            ]);
            
            // Resolve timezone
            $tz = app(TimezoneService::class)->resolveFromRequest(
                $validated['tz'] ?? null,
                $request->user()
            );
            
            $startDate = $validated['start_date'] 
                ? Carbon::parse($validated['start_date'], $tz)->startOfDay()->utc()
                : Carbon::now($tz)->subMonth()->startOfDay()->utc();
            $endDate = $validated['end_date'] 
                ? Carbon::parse($validated['end_date'], $tz)->endOfDay()->utc()
                : Carbon::now($tz)->endOfDay()->utc();
            $granularity = $validated['granularity'] ?? 'daily';
            $summary = $this->getSummaryMetrics($startDate, $endDate);
            $chartData = $this->getChartData($startDate, $endDate, $granularity);
            $dailyBreakdown = $this->getDailyBreakdown($startDate, $endDate);
            return sendResponse(
                'Financial report retrieved successfully',
                [
                    'summary' => $summary,
                    'chart_data' => $chartData,
                    'daily_breakdown' => $dailyBreakdown,
                ]
            );
        } catch (\Exception $e) {
            return sendResponse(
                'Error retrieving financial report',
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    private function getSummaryMetrics($startDate, $endDate)
    {
        $revenue = Shipment::whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $codCollected = Shipment::whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_type', 'cod')
            ->sum('amount');
        $expenses = Expense::whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
        return [
            'total_revenue' => (float) $revenue,
            'cod_collected' => (float) $codCollected,
            'total_expenses' => (float) $expenses,
            'net_profit' => (float) ($revenue - $expenses),
        ];
    }

    private function getChartData($startDate, $endDate, $granularity)
    {
        $shipmentQuery = DB::table('shipments')
            ->select(
                DB::raw($this->dateGroupExpression($granularity) . ' as period'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('SUM(CASE WHEN payment_type="cod" THEN amount ELSE 0 END) as cod')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period');
        $expenseQuery = DB::table('expenses')
            ->select(
                DB::raw($this->dateGroupExpression($granularity, 'created_at') . ' as period'),
                DB::raw('SUM(amount) as expense')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period');
        $shipmentData = $shipmentQuery->get();
        $expenseData = $expenseQuery->get();
        return $this->mergeChartData($shipmentData, $expenseData);
    }

    private function getDailyBreakdown($startDate, $endDate)
    {
        $breakdownQuery = DB::table('shipments')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as daily_revenue'),
                DB::raw('SUM(CASE WHEN payment_type="cod" THEN amount ELSE 0 END) as daily_cod')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'));
        $dailyData = $breakdownQuery->get()->map(function ($item) {
            $expenses = Expense::whereDate('created_at', $item->date)->sum('amount');
            return [
                'date' => $item->date,
                'revenue' => (float) $item->daily_revenue,
                'cod_collected' => (float) $item->daily_cod,
                'expenses' => (float) $expenses,
                'net_profit' => (float) ($item->daily_revenue - $expenses),
            ];
        });
        return $dailyData;
    }

    private function dateGroupExpression($granularity, $field = 'created_at')
    {
        switch ($granularity) {
            case 'weekly':
                return "YEAR({$field}), WEEK({$field})";
            case 'monthly':
                return "DATE_FORMAT({$field}, '%Y-%m')";
            case 'daily':
            default:
                return "DATE({$field})";
        }
    }

    /**
     * @OA\Get(
     *     path="/financial-reports/export/pdf",
     *     summary="Export financial report to PDF",
     *     description="Initiates the export of financial report data to a PDF file.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for the report",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for the report",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="granularity",
     *         in="query",
     *         description="Granularity of the report (daily, weekly, monthly)",
     *         @OA\Schema(type="string", enum={"daily", "weekly", "monthly"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF export initiated successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error exporting PDF"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function exportPdf(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'granularity' => 'in:daily,weekly,monthly',
                'tz' => 'nullable|string',
            ]);
            
            // Resolve timezone
            $tz = app(TimezoneService::class)->resolveFromRequest(
                $validated['tz'] ?? null,
                $request->user()
            );
            
            $startDate = Carbon::parse($validated['start_date'], $tz)->startOfDay()->utc();
            $endDate = Carbon::parse($validated['end_date'], $tz)->endOfDay()->utc();
            $granularity = $validated['granularity'] ?? 'daily';
            $summary = $this->getSummaryMetrics($startDate, $endDate);
            $chartData = $this->getChartData($startDate, $endDate, $granularity);
            $dailyBreakdown = $this->getDailyBreakdown($startDate, $endDate);
            return sendResponse(
                'PDF export initiated successfully',
                [
                    'summary' => $summary,
                    'chart_data' => $chartData,
                    'daily_breakdown' => $dailyBreakdown,
                ]
            );
        } catch (\Exception $e) {
            return sendResponse(
                'Error exporting PDF',
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/financial-reports/export/csv",
     *     summary="Export financial report to CSV",
     *     description="Initiates the export of financial report data to a CSV file.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for the report",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for the report",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="granularity",
     *         in="query",
     *         description="Granularity of the report (daily, weekly, monthly)",
     *         @OA\Schema(type="string", enum={"daily", "weekly", "monthly"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CSV export initiated successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error exporting CSV"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function exportCsv(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'granularity' => 'in:daily,weekly,monthly',
                'tz' => 'nullable|string',
            ]);
            
            // Resolve timezone
            $tz = app(TimezoneService::class)->resolveFromRequest(
                $validated['tz'] ?? null,
                $request->user()
            );
            
            $startDate = Carbon::parse($validated['start_date'], $tz)->startOfDay()->utc();
            $endDate = Carbon::parse($validated['end_date'], $tz)->endOfDay()->utc();
            $granularity = $validated['granularity'] ?? 'daily';
            $summary = $this->getSummaryMetrics($startDate, $endDate);
            $chartData = $this->getChartData($startDate, $endDate, $granularity);
            $dailyBreakdown = $this->getDailyBreakdown($startDate, $endDate);
            return sendResponse(
                'CSV export initiated successfully',
                [
                    'summary' => $summary,
                    'chart_data' => $chartData,
                    'daily_breakdown' => $dailyBreakdown,
                ]
            );
        } catch (\Exception $e) {
            return sendResponse(
                'Error exporting CSV',
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/financial-reports/details",
     *     summary="Get detailed transactions for a specific date",
     *     description="Retrieves detailed shipment and expense transactions for a given date.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date for which to retrieve detailed transactions",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detailed transactions retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving detailed transactions"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getDetails(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date',
                'tz' => 'nullable|string',
            ]);
            
            // Resolve timezone
            $tz = app(TimezoneService::class)->resolveFromRequest(
                $validated['tz'] ?? null,
                $request->user()
            );
            
            $date = Carbon::parse($validated['date'], $tz)->startOfDay()->utc();
            $shipments = Shipment::whereDate('created_at', $date)
                ->with(['merchant', 'shipper', 'consignee'])
                ->get()
                ->map(function ($shipment) {
                    return ['shipment_id' => $shipment->id,'merchant' => $shipment->merchant->name,'shipper' => $shipment->shipper->name,'consignee' => $shipment->consignee->name,'amount' => (float) $shipment->total_amount,'payment_type' => $shipment->payment_type,'status' => $shipment->status,];});
            $expenses = Expense::whereDate('created_at', $date)->get()->map(function ($expense) {
                return [
                    'description' => $expense->description,
                    'amount' => (float) $expense->amount,
                    'date' => $expense->date,
                ];
            });
            return sendResponse(
                'Detailed transactions retrieved successfully',
                [
                    'date' => $date->toDateString(),
                    'shipments' => $shipments,
                    'expenses' => $expenses,
                ]
            );
        } catch (\Exception $e) {
            return sendResponse(
                'Error retrieving detailed transactions',
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    private function mergeChartData($shipmentData, $expenseData)
    {
        $merged = [];
        $expMap = [];
        foreach ($expenseData as $exp) {
            $expMap[$exp->period] = (float) $exp->expense;
        }
        foreach ($shipmentData as $ord) {
            $period = $ord->period;
            $merged[$period] = [
                'period' => $period,
                'revenue' => (float) $ord->revenue,
                'cod' => (float) $ord->cod,
                'expenses' => $expMap[$period] ?? 0.0,
                'net_profit' => (float) ($ord->revenue - ($expMap[$period] ?? 0.0)),
            ];
        }

        // Add any periods present in expenses but not in shipments
        foreach ($expMap as $period => $expense) {
            if (!isset($merged[$period])) {
                $merged[$period] = [
                    'period' => $period,
                    'revenue' => 0.0,
                    'cod' => 0.0,
                    'expenses' => $expense,
                    'net_profit' => (float) (0.0 - $expense),
                ];
            }
        }
        ksort($merged);
        return array_values($merged);
    }
}
