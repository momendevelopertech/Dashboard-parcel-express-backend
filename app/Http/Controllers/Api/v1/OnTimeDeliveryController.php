<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\OnTimeDeliveryReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\TimezoneService;

/**
 * @OA\Tag(name="Other", description="On-Time Delivery API endpoints")
 * @OA\Controller(description="API for managing on-time delivery reports.")
 */
class OnTimeDeliveryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/on-time-delivery",
     *     summary="Get on-time delivery data",
     *     description="Retrieves on-time delivery data within a specified date range and region.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for the report (YYYY-MM-DD)",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for the report (YYYY-MM-DD)",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Region to filter the report by",
     *         required=false
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="On-time delivery data retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving on-time delivery data"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        try {
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
            $region = $request->query('region', null);

            $shipmentQuery = Shipment::join('driver_shipment_assignments', 'shipments.id', '=', 'driver_shipment_assignments.shipment_id')
                ->select(
                    DB::raw('DATE(driver_shipment_assignments.delivered_at) as date'),
                    DB::raw('COUNT(*) as total_shipments'),
                    DB::raw('SUM(CASE WHEN driver_shipment_assignments.delivered_at <= driver_shipment_assignments.created_at THEN 1 ELSE 0 END) as on_time_deliveries'),
                    DB::raw('SUM(CASE WHEN driver_shipment_assignments.delivered_at > driver_shipment_assignments.created_at THEN 1 ELSE 0 END) as delayed_deliveries')
                )
                ->whereBetween('driver_shipment_assignments.delivered_at', [$startDate, $endDate])
                ->where('shipments.status', 'delivered');
            if ($region) {
                $shipmentQuery->where('region', $region);
            }
            $shipmentQuery->groupBy(DB::raw('DATE(driver_shipment_assignments.delivered_at)'))
                ->orderBy(DB::raw('DATE(driver_shipment_assignments.delivered_at)'));
            $results = $shipmentQuery->get();
            $data = $results->map(function ($item) {
                $rate = $item->total_shipments > 0
                    ? round(($item->on_time_deliveries / $item->total_shipments) * 100, 2)
                    : 0.00;
                return [
                    'date' => $item->date,
                    'total_shipments' => (int) $item->total_shipments,
                    'on_time_deliveries' => (int) $item->on_time_deliveries,
                    'delayed_deliveries' => (int) $item->delayed_deliveries,
                    'on_time_rate' => (float) $rate,
                ];
            });

            return sendResponse('On-time delivery data retrieved successfully', $data);
        } catch (\Exception $e) {
            return sendResponse('Error retrieving on-time delivery data', [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/on-time-delivery/generate",
     *     summary="Generate on-time delivery report",
     *     description="Generates a daily on-time delivery report for a specified date range and region.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent()
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for the report (YYYY-MM-DD)",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for the report (YYYY-MM-DD)",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Region to filter the report by",
     *         required=false
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="On-Time Delivery report generated successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error generating on-time delivery report"
     *     ),
     *     security={{"bearerAuth":{}}}
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
        $region = $request->input('region', null);

        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            $q = Shipment::whereDate('delivered_at', $date)->where('status', 'delivered');
            if ($region) {
                $q->where('region', $region);
            }
            $totalShipments = $q->count();
            $onTime = $q->whereColumn('delivered_at', '<=', 'scheduled_delivery_date')->count();
            $delayed = $totalShipments - $onTime;

            $rate = $totalShipments > 0
                ? round(($onTime / $totalShipments) * 100, 2)
                : 0.00;

            OnTimeDeliveryReport::updateOrCreate(
                [
                    'report_date' => $date->toDateString(),
                    'region' => $region,
                ],
                [
                    'total_shipments' => $totalShipments,
                    'on_time_deliveries' => $onTime,
                    'delayed_deliveries' => $delayed,
                    'on_time_rate' => $rate,
                ]
            );
        }

        return sendResponse('On-Time Delivery report generated successfully', [], true, [], 201);
    }

    /**
     * @OA\Get(
     *     path="/on-time-delivery/{date}",
     *     summary="Get delayed shipments for a specific date",
     *     description="Retrieves delayed shipments for a given date and optional region.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="date",
     *         in="path",
     *         description="Date for which to retrieve delayed shipments (YYYY-MM-DD)",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Region to filter the report by",
     *         required=false
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delayed shipments retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving delayed shipments"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($date, Request $request)
    {
        $region = $request->query('region', null);
        $dt = Carbon::parse($date);

        $delayedShipmentsQuery = Shipment::whereDate('delivered_at', $dt)
            ->where('status', 'delivered')
            ->whereColumn('delivered_at', '>', 'scheduled_delivery_date');

        if ($region) {
            $delayedShipmentsQuery->where('region', $region);
        }

        $delayedShipments = $delayedShipmentsQuery->select(
            'id as shipment_id',
            'scheduled_delivery_date',
            'delivered_at',
            DB::raw('TIMESTAMPDIFF(MINUTE, scheduled_delivery_date, delivered_at) as delay_minutes')
        )
            ->get();

        return sendResponse('Delayed shipments retrieved successfully', [
            'date' => $dt->toDateString(),
            'delayed_shipments' => $delayedShipments,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/on-time-delivery/export",
     *     summary="Export on-time delivery report",
     *     description="Exports on-time delivery data as a CSV file for a specified date range and region.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent()
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for the report (YYYY-MM-DD)",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for the report (YYYY-MM-DD)",
     *         required=false
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Region to filter the report by",
     *         required=false
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="On-time delivery report exported successfully"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error exporting on-time delivery report"
     *     ),
     *     security={{"bearerAuth":{}}}
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
        $region = $request->input('region', null);

        $shipmentQuery = Shipment::select(
            DB::raw('DATE(delivered_at) as date'),
            DB::raw('COUNT(*) as total_shipments'),
            DB::raw('SUM(CASE WHEN delivered_at <= scheduled_delivery_date THEN 1 ELSE 0 END) as on_time_deliveries'),
            DB::raw('SUM(CASE WHEN delivered_at > scheduled_delivery_date THEN 1 ELSE 0 END) as delayed_deliveries')
        )
            ->whereBetween('delivered_at', [$startDate, $endDate])
            ->where('status', 'delivered');

        if ($region) {
            $shipmentQuery->where('region', $region);
        }

        $shipmentQuery->groupBy(DB::raw('DATE(delivered_at)'))
            ->orderBy(DB::raw('DATE(delivered_at)'));

        $results = $shipmentQuery->get();

        $response = new StreamedResponse(function () use ($results) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Total Shipments', 'On-Time Deliveries', 'Delayed Deliveries', 'On-Time Rate (%)']);
            foreach ($results as $item) {
                $date = $item->date;
                $total = $item->total_shipments;
                $onTime = $item->on_time_deliveries;
                $delayed = $item->delayed_deliveries;
                $rate = $total > 0
                    ? round(($onTime / $total) * 100, 2)
                    : 0.00;

                fputcsv($handle, [$date, $total, $onTime, $delayed, $rate]);
            }
            fclose($handle);
        });

        $filename = 'on_time_delivery_' . now()->format('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename=\"' . $filename . '\"');

        return $response;
    }
}