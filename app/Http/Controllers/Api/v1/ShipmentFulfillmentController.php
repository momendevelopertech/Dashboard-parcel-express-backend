<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\ShipmentFulfillmentReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\TimezoneService;

/**
 * @OA\Tag(name="Other", description="Shipment Fulfillment APIs")
 * @OA\Server(url="/api/v1")
 */
class ShipmentFulfillmentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/shipment-fulfillment",
     *     summary="Get shipment fulfillment data",
     *     description="Retrieves shipment fulfillment data within a specified date range.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date of the range (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date of the range (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment fulfillment data retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {
        // Resolve timezone
        $tz = app(TimezoneService::class)->resolveFromRequest(
            $request->query('tz'),
            $request->user()
        );

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'), $tz)->startOfDay()->utc()
            : Carbon::now($tz)->subDays(30)->startOfDay()->utc();
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'), $tz)->endOfDay()->utc()
            : Carbon::now($tz)->endOfDay()->utc();
        $shipmentQuery = Shipment::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total_shipments'),
            DB::raw('SUM(CASE WHEN status=\"COMPLETED\" THEN 1 ELSE 0 END) as fulfilled_shipments'),
            DB::raw('SUM(CASE WHEN status!=\"COMPLETED\" THEN 1 ELSE 0 END) as failed_shipments')
        )
            ->whereBetween('created_at', [$startDate, $endDate]);
        $shipmentQuery->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'));
        $results = $shipmentQuery->get();
        $data = $results->map(function ($item) {
            $rate = $item->total_shipments > 0
                ? round(($item->fulfilled_shipments / $item->total_shipments) * 100, 2)
                : 0.00;
            return [
                'date' => $item->date,
                'total_shipments' => (int) $item->total_shipments,
                'fulfilled_shipments' => (int) $item->fulfilled_shipments,
                'failed_shipments' => (int) $item->failed_shipments,
                'fulfillment_rate' => (float) $rate,
            ];
        });
        return sendResponse('Shipment fulfillment data retrieved successfully', ['data' => $data]);
    }

    /**
     * @OA\Post(
     *     path="/shipment-fulfillment/generate",
     *     summary="Generate shipment fulfillment reports",
     *     description="Generates shipment fulfillment reports for a specified date range.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         description="Start and end dates for report generation",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date (YYYY-MM-DD)"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date (YYYY-MM-DD)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shipment Fulfillment reports generated successfully",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function generate(Request $request)
    {
        // Resolve timezone
        $tz = app(TimezoneService::class)->resolveFromRequest(
            $request->input('tz'),
            $request->user()
        );

        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'), $tz)->startOfDay()->utc()
            : Carbon::yesterday($tz)->startOfDay()->utc();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'), $tz)->endOfDay()->utc()
            : Carbon::yesterday($tz)->endOfDay()->utc();
        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            $q = Shipment::whereDate('created_at', $date);
            $total = $q->count();
            $fulfilled = $q->where('status', 'COMPLETED')->count();
            $failed = $total - $fulfilled;
            $rate = $total > 0
                ? round(($fulfilled / $total) * 100, 2)
                : 0.00;
            ShipmentFulfillmentReport::updateOrCreate(
                [
                    'report_date' => $date->toDateString(),
                ],
                [
                    'total_shipments' => $total,
                    'fulfilled_shipments' => $fulfilled,
                    'failed_shipments' => $failed,
                    'fulfillment_rate' => $rate,
                ]
            );
        }

        return sendResponse('Shipment Fulfillment reports generated successfully', [], true, [], 201);
    }

    /**
     * @OA\Get(
     *     path="/shipment-fulfillment/{date}",
     *     summary="Get failed shipments for a specific date",
     *     description="Retrieves failed shipments for a given date.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="date",
     *         in="path",
     *         description="Date (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Failed shipments data retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show($date, Request $request)
    {
        $dt = Carbon::parse($date);
        $failedQuery = Shipment::whereDate('created_at', $dt)
            ->where('status', '!=', 'COMPLETED');

        $failedShipments = $failedQuery->select(
            'id as shipment_id',
            'created_at as shipment_date',
            'status'
        )
            ->get();
        return sendResponse('Failed shipments data retrieved successfully', [
            'date' => $dt->toDateString(),
            'failed_shipments' => $failedShipments
        ]);
    }

    /**
     * @OA\Post(
     *     path="/shipment-fulfillment/export",
     *     summary="Export shipment fulfillment data",
     *     description="Exports shipment fulfillment data to a CSV file.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         description="Start and end dates for export",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date (YYYY-MM-DD)"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date (YYYY-MM-DD)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CSV file exported successfully",
     *         @OA\MediaType(mediaType="text/csv")
     *     ),
     *      @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function export(Request $request)
    {
        // Resolve timezone
        $tz = app(TimezoneService::class)->resolveFromRequest(
            $request->input('tz'),
            $request->user()
        );

        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'), $tz)->startOfDay()->utc()
            : Carbon::now($tz)->subDays(30)->startOfDay()->utc();
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'), $tz)->endOfDay()->utc()
            : Carbon::now($tz)->endOfDay()->utc();
        $shipmentQuery = Shipment::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total_shipments'),
            DB::raw('SUM(CASE WHEN status=\"COMPLETED\" THEN 1 ELSE 0 END) as fulfilled_shipments'),
            DB::raw('SUM(CASE WHEN status!=\"COMPLETED\" THEN 1 ELSE 0 END) as failed_shipments')
        )
            ->whereBetween('created_at', [$startDate, $endDate]);
        $shipmentQuery->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'));
        $results = $shipmentQuery->get();
        $response = new StreamedResponse(function () use ($results) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Total Shipments', 'Fulfilled Shipments', 'Failed Shipments', 'Fulfillment Rate (%)']);
            foreach ($results as $item) {
                $date = $item->date;
                $total = $item->total_shipments;
                $fulfilled = $item->fulfilled_shipments;
                $failed = $item->failed_shipments;
                $rate = $total > 0
                    ? round(($fulfilled / $total) * 100, 2)
                    : 0.00;
                fputcsv($handle, [$date, $total, $fulfilled, $failed, $rate]);
            }
            fclose($handle);
        });

        $filename = 'shipment_fulfillment_' . now()->format('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', "\"attachment; filename=\"$filename\"");

        return $response;
    }
}
