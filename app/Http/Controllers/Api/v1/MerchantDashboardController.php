<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MerchantDashboardController extends Controller
{
    /**
     * Get KPI Summary
     *
     * @OA\Get(
     *     path="/merchant/dashboard/kpi-summary",
     *     summary="Get merchant KPI summary for analytics cards",
     *     description="
     * Retrieve comprehensive KPI summary for authenticated merchant analytics dashboard.
     * 
     * **Features:**
     * - Total shipments count
     * - Success/failure rate statistics  
     * - Total COD collections
     * - Revenue metrics
     * - Delivery performance indicators
     * - Date range filtering support
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getMerchantKPISummary",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date filter (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date filter (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="KPI summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="KPI summary retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_shipments", type="integer"),
     *                 @OA\Property(property="delivered_shipments", type="integer"),
     *                 @OA\Property(property="pending_shipments", type="integer"),
     *                 @OA\Property(property="success_rate", type="number", format="float"),
     *                 @OA\Property(property="total_cod", type="number", format="float"),
     *                 @OA\Property(property="total_revenue", type="number", format="float"),
     *                 @OA\Property(property="avg_shipment_value", type="number", format="float"),
     *                 @OA\Property(property="delivery_rate", type="number", format="float")
     *             ),
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
    public function kpiSummary(Request $request)
    {
        try {
            $merchantId = Auth::id();

            // Build base query with date filters
            $baseQuery = Shipment::where('merchant_id', $merchantId);

            if ($request->from_date) {
                $baseQuery->whereDate('created_at', '>=', Carbon::parse($request->from_date));
            }

            if ($request->to_date) {
                $baseQuery->whereDate('created_at', '<=', Carbon::parse($request->to_date));
            }

            // Total shipments
            $totalShipments = (clone $baseQuery)->count();

            // Delivered shipments
            $deliveredShipments = (clone $baseQuery)
                ->whereHas('runsheet_shipment', fn($q) => $q->where('status', 'delivered'))
                ->count();

            // Pending shipments (active statuses)
            $pendingShipments = (clone $baseQuery)
                ->whereIn('status', ['CREATED', 'PICKED', 'OFD', 'DISPATCH'])
                ->count();

            // Success rate calculation
            $successRate = $totalShipments > 0 ? round(($deliveredShipments / $totalShipments) * 100, 2) : 0;

            // Total COD collections (delivered COD shipments)
            $totalCod = (clone $baseQuery)
                ->where('payment_type', 'COD')
                ->whereHas('runsheet_shipment', fn($q) => $q->where('status', 'delivered'))
                ->sum('total_cod');

            // Total revenue (all shipment amounts)
            $totalRevenue = (clone $baseQuery)->sum('total_cod');

            // Average shipment value
            $avgShipmentValue = $totalShipments > 0 ? round($totalRevenue / $totalShipments, 2) : 0;

            // Delivery rate (similar to success rate but more specific)
            $deliveryRate = $totalShipments > 0 ? round(($deliveredShipments / $totalShipments) * 100, 2) : 0;

            return sendResponse("KPI summary retrieved successfully.", [
                'total_shipments' => $totalShipments,
                'delivered_shipments' => $deliveredShipments,
                'pending_shipments' => $pendingShipments,
                'success_rate' => $successRate,
                'total_cod' => (float) $totalCod,
                'total_revenue' => (float) $totalRevenue,
                'avg_shipment_value' => $avgShipmentValue,
                'delivery_rate' => $deliveryRate
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching KPI summary.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Unexpected error occurred.", [], [$e->getMessage()], 500);
        }
    }

    /**
     * Get Charts Data
     *
     * @OA\Get(
     *     path="/merchant/dashboard/charts",
     *     summary="Get chart datasets for analytics visualization",
     *     description="
     * Retrieve chart data for various analytics visualizations on merchant dashboard.
     * 
     * **Features:**
     * - Shipment trends (line chart)
     * - Status distribution (pie chart)
     * - Payment type breakdown (bar chart)
     * - Monthly performance (bar chart)
     * - Date range and groupBy filtering
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getMerchantChartsData",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date filter (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date filter (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="group_by",
     *         in="query",
     *         description="Group data by time period",
     *         required=false,
     *         @OA\Schema(type="string", enum={"day", "week", "month"}, default="day")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Charts data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Charts data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="shipment_trends", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="status_distribution", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="payment_breakdown", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="monthly_performance", type="array", @OA\Items(type="object"))
     *             ),
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
    public function chartsData(Request $request)
    {
        try {
            $merchantId = Auth::id();
            $groupBy = $request->input('group_by', 'day');

            // Build base query
            $baseQuery = Shipment::where('merchant_id', $merchantId);

            if ($request->from_date) {
                $baseQuery->whereDate('created_at', '>=', Carbon::parse($request->from_date));
            }

            if ($request->to_date) {
                $baseQuery->whereDate('created_at', '<=', Carbon::parse($request->to_date));
            }

            // Shipment trends over time
            $dateFormat = match ($groupBy) {
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                default => '%Y-%m-%d'
            };

            $shipmentTrends = (clone $baseQuery)
                ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period, COUNT(*) as count")
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(function ($item) use ($groupBy) {
                    return [
                        'period' => $item->period,
                        'label' => $this->formatPeriodLabel($item->period, $groupBy),
                        'count' => $item->count
                    ];
                });

            // Status distribution
            $statusDistribution = (clone $baseQuery)
                ->leftJoin('driver_runsheet_shipments', 'shipments.tracking_no', '=', 'driver_runsheet_shipments.shipment_tracking_no')
                ->selectRaw('
                    CASE 
                        WHEN driver_runsheet_shipments.status = "delivered" THEN "Delivered"
                        WHEN shipments.status IN ("CREATED", "PICKED") THEN "Processing"
                        WHEN shipments.status IN ("OFD", "DISPATCH") THEN "In Transit"
                        ELSE "Other"
                    END as status_group,
                    COUNT(*) as count
                ')
                ->groupBy('status_group')
                ->get();

            // Payment type breakdown
            $paymentBreakdown = (clone $baseQuery)
                ->selectRaw('payment_type, COUNT(*) as count, SUM(total_cod) as total_amount')
                ->groupBy('payment_type')
                ->get();

            // Monthly performance (last 12 months)
            $monthlyPerformance = Shipment::where('merchant_id', $merchantId)
                ->selectRaw('
                    YEAR(created_at) as year,
                    MONTH(created_at) as month,
                    COUNT(*) as total_shipments,
                    SUM(CASE WHEN EXISTS(
                        SELECT 1 FROM driver_runsheet_shipments 
                        WHERE driver_runsheet_shipments.shipment_tracking_no = shipments.tracking_no 
                        AND driver_runsheet_shipments.status = "delivered"
                    ) THEN 1 ELSE 0 END) as delivered_shipments,
                    SUM(total_cod) as total_amount
                ')
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'period' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                        'label' => Carbon::create($item->year, $item->month)->format('M Y'),
                        'total_shipments' => $item->total_shipments,
                        'delivered_shipments' => $item->delivered_shipments,
                        'total_amount' => (float) $item->total_amount,
                        'delivery_rate' => $item->total_shipments > 0 ? round(($item->delivered_shipments / $item->total_shipments) * 100, 2) : 0
                    ];
                });

            return sendResponse("Charts data retrieved successfully.", [
                'shipment_trends' => $shipmentTrends,
                'status_distribution' => $statusDistribution,
                'payment_breakdown' => $paymentBreakdown,
                'monthly_performance' => $monthlyPerformance
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching charts data.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Unexpected error occurred.", [], [$e->getMessage()], 500);
        }
    }

    /**
     * Get Shipments Report
     *
     * @OA\Get(
     *     path="/merchant/dashboard/shipments",
     *     summary="Get paginated shipments data for detailed reports",
     *     description="
     * Retrieve paginated and filtered shipments data for detailed analytics reporting.
     * 
     * **Features:**
     * - Comprehensive filtering options
     * - Status-based filtering
     * - Date range filtering
     * - Service type filtering
     * - City/location filtering
     * - Pagination support
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getMerchantShipmentsReport",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by shipment status",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filter by destination city",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="service_type",
     *         in="query",
     *         description="Filter by payment/service type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date filter (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date filter (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
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
     *         description="Shipments report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipments report retrieved successfully."),
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
    public function shipmentsReport(Request $request)
    {
        try {
            $merchantId = Auth::id();
            $perPage = min((int) $request->input('per_page', 15), 100);

            $query = Shipment::where('merchant_id', $merchantId)
                ->with([
                    'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone,streetAddress,governorate_id,state_id',
                    'consignee.governorate:id,en_name,ar_name',
                    'consignee.state:id,en_name,ar_name',
                    'shipper:id,name',
                    'runsheet_shipment:shipment_tracking_no,status,delivered_at',
                    'shipment_finance:shipment_tracking_no,merchant_balance,driver_delivery_fee'
                ]);

            // Apply filters
            if ($request->status) {
                if ($request->status === 'delivered') {
                    $query->whereHas('runsheet_shipment', fn($q) => $q->where('status', 'delivered'));
                } else {
                    $query->where('status', $request->status);
                }
            }

            if ($request->city) {
                $query->whereHas('consignee', function ($q) use ($request) {
                    $q->whereHas('state', function ($subQ) use ($request) {
                        $subQ->where('en_name', 'like', "%{$request->city}%")
                            ->orWhere('ar_name', 'like', "%{$request->city}%");
                    });
                });
            }

            if ($request->service_type) {
                $query->where('payment_type', $request->service_type);
            }

            if ($request->from_date) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->from_date));
            }

            if ($request->to_date) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->to_date));
            }

            $shipments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return sendResponse("Shipments report retrieved successfully.", $shipments);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching shipments report.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Unexpected error occurred.", [], [$e->getMessage()], 500);
        }
    }

    /**
     * Format period label for chart display
     */
    private function formatPeriodLabel($period, $groupBy)
    {
        return match ($groupBy) {
            'week' => 'Week ' . substr($period, -2) . ', ' . substr($period, 0, 4),
            'month' => Carbon::createFromFormat('Y-m', $period)->format('M Y'),
            default => Carbon::createFromFormat('Y-m-d', $period)->format('M d, Y')
        };
    }
}
