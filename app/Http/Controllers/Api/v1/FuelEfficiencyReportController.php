<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\FuelLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Writer as ExcelWriter;
use SplTempFileObject;

/**
 * @OA\Tag(
 *     name="Other",
 *     description="Fuel Efficiency Report Controller"
 * )
 *
 * @OA\Server(url="{{ config('app.url') }}/api/documentation")
 */
class FuelEfficiencyReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/fuel-efficiency",
     *     summary="Get Fuel Efficiency Report",
     *     description="Retrieves a fuel efficiency report based on specified criteria.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date of the report (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date of the report (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         description="ID of the driver to filter the report by (optional)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default: 15)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fuel efficiency report retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *     ),
     * )
     */
    public function index(Request $request)
    {
        $rules = [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'driver_id'  => 'sometimes|exists:users,id',
            'per_page'   => 'sometimes|integer|min:1'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return sendResponse('Validation failed', [], false, $validator->errors()->toArray(), 422);
        }

        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $driverId  = $request->input('driver_id', null);
        $perPage   = $request->query('per_page', 15);

        // Base query: FuelLog filtered by date range
        $baseQuery = FuelLog::whereBetween('log_date', [$startDate, $endDate]);

        if ($driverId) {
            $baseQuery->where('driver_id', $driverId);
        }

        // 1. SUMMARY: total distance and total fuel
        $totals = $baseQuery
            ->selectRaw('SUM(distance_km) AS total_distance, SUM(fuel_liters) AS total_fuel')
            ->first();

        $totalDistance = $totals->total_distance ?? 0.00;
        $totalFuel     = $totals->total_fuel     ?? 0.00;
        $averageEfficiency = $totalFuel > 0
            ? round($totalDistance / $totalFuel, 2)
            : 0.00;

        // 2. DETAIL: per-driver aggregation
        $detailQuery = FuelLog::whereBetween('log_date', [$startDate, $endDate]);
        if ($driverId) {
            $detailQuery->where('driver_id', $driverId);
        }

        $detailData = $detailQuery
            ->selectRaw('driver_id, SUM(distance_km) AS distance_km, SUM(fuel_liters) AS fuel_liters')
            ->groupBy('driver_id')
            ->with('driver:id,name')
            ->orderBy('driver_id', 'asc')
            ->paginate($perPage);

        // Transform detail rows
        $detailTransformed = $detailData->getCollection()->map(function ($row) {
            $distance = (float) $row->distance_km;
            $fuel     = (float) $row->fuel_liters;
            $eff      = $fuel > 0 ? round($distance / $fuel, 2) : 0.00;

            // Recommendation logic
            if ($eff >= 3.0) {
                $rec = 'Excellent efficiency';
            } elseif ($eff >= 2.0) {
                $rec = 'Good efficiency';
            } else {
                $rec = 'Consider vehicle maintenance';
            }

            return [
                'driver_id'      => $row->driver_id,
                'driver_name'    => $row->driver->name,
                'distance_km'    => round($distance, 2),
                'fuel_liters'    => round($fuel, 2),
                'efficiency'     => $eff,
                'recommendation' => $rec
            ];
        });

        // Replace collection with transformed data
        $detailData->setCollection($detailTransformed);

        // 3. CHART: Efficiency by Driver (bar chart)
        $chartByDriverQuery = FuelLog::whereBetween('log_date', [$startDate, $endDate]);
        if ($driverId) {
            $chartByDriverQuery->where('driver_id', $driverId);
        }

        $chartByDriverRaw = $chartByDriverQuery
            ->selectRaw('driver_id, SUM(distance_km) AS distance_km, SUM(fuel_liters) AS fuel_liters')
            ->groupBy('driver_id')
            ->with('driver:id,name')
            ->get();

        $chartByDriver = $chartByDriverRaw->map(function ($row) {
            $distance = (float) $row->distance_km;
            $fuel     = (float) $row->fuel_liters;
            $eff      = $fuel > 0 ? round($distance / $fuel, 2) : 0.00;
            return [
                'driver_id'   => $row->driver_id,
                'driver_name' => $row->driver->name,
                'efficiency'  => $eff
            ];
        });

        // 4. CHART: Efficiency Trend (line chart per day)
        $trendQuery = FuelLog::whereBetween('log_date', [$startDate, $endDate]);
        if ($driverId) {
            $trendQuery->where('driver_id', $driverId);
        }

        $trendRaw = $trendQuery
            ->selectRaw('log_date, SUM(distance_km) AS distance_km, SUM(fuel_liters) AS fuel_liters')
            ->groupBy('log_date')
            ->orderBy('log_date', 'asc')
            ->get();

        $trend = $trendRaw->map(function ($row) {
            $distance = (float) $row->distance_km;
            $fuel     = (float) $row->fuel_liters;
            $eff      = $fuel > 0 ? round($distance / $fuel, 2) : 0.00;
            return [
                'date'           => $row->log_date->toDateString(),
                'avg_efficiency' => $eff
            ];
        });

        $response = [
            'summary' => [
                'total_distance'    => round((float) $totalDistance, 2),
                'total_fuel'        => round((float) $totalFuel, 2),
                'average_efficiency'=> $averageEfficiency
            ],
            'detail' => [
                'data'  => $detailData->items(),
                'links' => [
                    'first'    => $detailData->url(1),
                    'last'     => $detailData->url($detailData->lastPage()),
                    'prev'     => $detailData->previousPageUrl(),
                    'next'     => $detailData->nextPageUrl()
                ],
                'meta'  => [
                    'current_page' => $detailData->currentPage(),
                    'from'         => $detailData->firstItem(),
                    'last_page'    => $detailData->lastPage(),
                    'per_page'     => $detailData->perPage(),
                    'to'           => $detailData->lastItem(),
                    'total'        => $detailData->total()
                ]
            ],
            'charts' => [
                'by_driver' => $chartByDriver,
                'trend'     => $trend
            ]
        ];

        return sendResponse('Fuel efficiency report retrieved successfully', $response);
    }

    /**
     * @OA\Get(
     *     path="/fuel-efficiency/export/pdf",
     *     summary="Export Fuel Efficiency Report as PDF",
     *     description="Exports the fuel efficiency report as a PDF document.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date of the report (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date of the report (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         description="ID of the driver to filter the report by (optional)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF file downloaded successfully",
     *     ),
     *      @OA\Response(
     *         response=500,
     *         description="Failed to generate PDF",
     *     ),
     * )
     */
    public function exportPdf(Request $request)
    {
        $data = $this->index($request)->getData();

        $pdf = Pdf::loadView('reports.fuel_efficiency', [
            'start_date'  => $request->input('start_date'),
            'end_date'    => $request->input('end_date'),
            'driver_id'   => $request->input('driver_id', null),
            'summary'     => $data->summary,
            'detail'      => $data->detail['data'],
            'charts'      => $data->charts
        ]);

        $filename = 'fuel_efficiency_' . now()->format('Ymd_His') . '.pdf';

        try {
            return $pdf->download($filename);
        } catch (\Exception $e) {
            return sendResponse('Failed to generate PDF', [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/fuel-efficiency/export/csv",
     *     summary="Export Fuel Efficiency Report as CSV",
     *     description="Exports the fuel efficiency report as a CSV document.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date of the report (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date of the report (YYYY-MM-DD)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         description="ID of the driver to filter the report by (optional)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CSV file downloaded successfully",
     *     ),
     *      @OA\Response(
     *         response=500,
     *         description="Failed to generate CSV",
     *     ),
     * )
     */
    public function exportCsv(Request $request)
    {
        $data = $this->index($request)->getData();
        $rows = $data->detail['data'];

        $csv = ExcelWriter::createFromFileObject(new SplTempFileObject());
        $csv->insertOne(['Driver', 'Distance (km)', 'Fuel (L)', 'Efficiency (km/L)', 'Recommendation']);

        foreach ($rows as $row) {
            $csv->insertOne([
                $row['driver_name'],
                number_format($row['distance_km'], 2),
                number_format($row['fuel_liters'], 2),
                number_format($row['efficiency'], 2),
                $row['recommendation']
            ]);
        }

        $filename = 'fuel_efficiency_' . now()->format('Ymd_His') . '.csv';

        try {
            $csv->output($filename);
            return response((string) $csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } catch (\Exception $e) {
            return sendResponse('Failed to generate CSV', [], false, [$e->getMessage()], 500);
        }
    }
}