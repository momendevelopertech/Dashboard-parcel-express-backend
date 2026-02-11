<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Driver;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class DriverPerformanceController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');
        $name     = $request->query('driver_name');
        $driversQuery = Driver::with('user');
        if ($name) {
            $driversQuery->whereHas('user', function ($q) use ($name) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($name) . '%']);
            });
        }
        $drivers = $driversQuery
            ->withCount([
                'deliveries as total_deliveries' => function ($q) use ($dateFrom, $dateTo) {
                    if ($dateFrom) $q->whereDate('delivery_date', '>=', $dateFrom);
                    if ($dateTo)   $q->whereDate('delivery_date', '<=', $dateTo);
                },
                'deliveries as on_time_deliveries' => function ($q) use ($dateFrom, $dateTo) {
                    if ($dateFrom) $q->whereDate('delivery_date', '>=', $dateFrom);
                    if ($dateTo)   $q->whereDate('delivery_date', '<=', $dateTo);
                    $q->whereColumn('actual_delivery_time', '<=', 'scheduled_delivery_time')
                        ->where('status', 'delivered');
                },
                'deliveries as delayed_deliveries' => function ($q) use ($dateFrom, $dateTo) {
                    if ($dateFrom) $q->whereDate('delivery_date', '>=', $dateFrom);
                    if ($dateTo)   $q->whereDate('delivery_date', '<=', $dateTo);
                    $q->whereColumn('actual_delivery_time', '>', 'scheduled_delivery_time')
                        ->where('status', 'delivered');
                },
            ])
            ->withAvg(['feedbacks as average_rating' => function ($q) use ($dateFrom, $dateTo) {
                $q->whereHas('delivery', function ($d) use ($dateFrom, $dateTo) {
                    if ($dateFrom) $d->whereDate('delivery_date', '>=', $dateFrom);
                    if ($dateTo)   $d->whereDate('delivery_date', '<=', $dateTo);
                });
            }], 'rating')
            ->paginate(10);
        $drivers->getCollection()->transform(function ($driver) {
            $total     = $driver->total_deliveries;
            $onTime    = $driver->on_time_deliveries;
            $avgRating = $driver->average_rating ?? 0;
            $onTimeRate = $total > 0 ? ($onTime / $total) * 100 : 0;
            $performanceScore = round((0.7 * ($onTimeRate / 100) + 0.3 * ($avgRating / 5)) * 100, 2);
            return array_merge($driver->toArray(), [
                'driver_name'       => optional($driver->user)->name, // إرجاع اسم السائق من علاقة user
                'on_time_rate'      => round($onTimeRate, 2),
                'performance_score' => $performanceScore,
            ]);
        });
        $summary = $this->computeSummaryMetrics($dateFrom, $dateTo);
        return sendResponse(
            'Driver performance data retrieved successfully',
            [
                'data'    => $drivers,
                'summary' => $summary
            ]
        );
    }


    protected function computeSummaryMetrics($dateFrom, $dateTo)
    {
        $deliveriesQuery = Delivery::query();
        if ($dateFrom) $deliveriesQuery->whereDate('delivery_date', '>=', $dateFrom);
        if ($dateTo)   $deliveriesQuery->whereDate('delivery_date', '<=', $dateTo);

        $totalDeliveries = $deliveriesQuery->count();
        $onTimeCount = (clone $deliveriesQuery)
            ->whereColumn('actual_delivery_time', '<=', 'scheduled_delivery_time')
            ->where('status', 'delivered')
            ->count();

        $avgOnTimeRate = $totalDeliveries > 0 ? round(($onTimeCount / $totalDeliveries) * 100, 2) : 0;

        $drivers = Driver::withCount([
            'deliveries as total_deliveries' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom) $q->whereDate('delivery_date', '>=', $dateFrom);
                if ($dateTo)   $q->whereDate('delivery_date', '<=', $dateTo);
            },
            'deliveries as on_time_deliveries' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom) $q->whereDate('delivery_date', '>=', $dateFrom);
                if ($dateTo)   $q->whereDate('delivery_date', '<=', $dateTo);
                $q->whereColumn('actual_delivery_time', '<=', 'scheduled_delivery_time')
                    ->where('status', 'delivered');
            },
        ])->withAvg(['feedbacks as average_rating' => function ($q) use ($dateFrom, $dateTo) {
            $q->whereHas('delivery', function ($d) use ($dateFrom, $dateTo) {
                if ($dateFrom) $d->whereDate('delivery_date', '>=', $dateFrom);
                if ($dateTo)   $d->whereDate('delivery_date', '<=', $dateTo);
            });
        }], 'rating')->get();

        $sumScores = 0;
        $driverCount = $drivers->count();

        foreach ($drivers as $driver) {
            $total     = $driver->total_deliveries;
            $onTime    = $driver->on_time_deliveries;
            $avgRating = $driver->average_rating ?? 0;

            $onTimeRate = $total > 0 ? ($onTime / $total) * 100 : 0;
            $score = (0.7 * ($onTimeRate / 100) + 0.3 * ($avgRating / 5)) * 100;
            $sumScores += $score;
        }

        $avgPerformanceScore = $driverCount > 0 ? round($sumScores / $driverCount, 2) : 0;

        return [
            'average_on_time_rate'         => $avgOnTimeRate,
            'total_deliveries_all_drivers' => $totalDeliveries,
            'average_performance_score'    => $avgPerformanceScore,
        ];
    }

    public function history(Request $request, $driverId)
    {
        $driver = Driver::findOrFail($driverId);
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');
        $deliveriesQuery = $driver->deliveries()
            ->with('feedback')
            ->orderBy('delivery_date', 'desc');

        if ($dateFrom) $deliveriesQuery->whereDate('delivery_date', '>=', $dateFrom);
        if ($dateTo)   $deliveriesQuery->whereDate('delivery_date', '<=', $dateTo);

        $deliveries = $deliveriesQuery->paginate(20);

        $deliveries->getCollection()->transform(function ($delivery) {
            return [
                'id'                     => $delivery->id,
                'scheduled_delivery_time' => $delivery->scheduled_delivery_time,
                'actual_delivery_time'   => $delivery->actual_delivery_time,
                'status'                 => $delivery->status,
                'delivery_date'          => $delivery->delivery_date,
                'on_time'                => $delivery->isOnTime(),
                'customer_rating'        => optional($delivery->feedback)->rating,
                'feedback_comments'      => optional($delivery->feedback)->comments,
            ];
        });

        return sendResponse(
            'Delivery history retrieved successfully',
            $deliveries
        );
    }

    public function exportCsv(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        $driversData = $this->fetchAllDriversMetrics($dateFrom, $dateTo);

        $filename = 'driver_performance_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = [
            'Driver Name',
            'Total Deliveries',
            'On-Time Deliveries',
            'Delayed Deliveries',
            'Average Rating',
            'On-Time Rate (%)',
            'Performance Score',
        ];

        $callback = function () use ($driversData, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($driversData as $row) {
                fputcsv($file, [
                    $row['name'],
                    $row['total_deliveries'],
                    $row['on_time_deliveries'],
                    $row['delayed_deliveries'],
                    number_format($row['average_rating'], 2),
                    number_format($row['on_time_rate'], 2),
                    number_format($row['performance_score'], 2),
                ]);
            }

            fclose($file);
        };

        return sendResponse(
            'CSV export generated successfully',
            null,
            true,
            [],
            200
        );
    }

    public function exportPdf(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');
        $summary  = $this->computeSummaryMetrics($dateFrom, $dateTo);
        $driversData = $this->fetchAllDriversMetrics($dateFrom, $dateTo);

        $pdf = PDF::loadView('reports.driver_performance', [
            'drivers'    => $driversData,
            'summary'    => $summary,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
        ]);

        $filename = 'driver_performance_' . now()->format('Ymd_His') . '.pdf';

        return sendResponse(
            'PDF export generated successfully',
            null,
            true,
            [],
            200
        );
    }

    protected function fetchAllDriversMetrics($dateFrom, $dateTo)
    {
        $drivers = Driver::withCount([
            'deliveries as total_deliveries' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom) $q->whereDate('delivery_date', '>=', $dateFrom);
                if ($dateTo)   $q->whereDate('delivery_date', '<=', $dateTo);
            },
            'deliveries as on_time_deliveries' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom) $q->whereDate('delivery_date', '>=', $dateFrom);
                if ($dateTo)   $q->whereDate('delivery_date', '<=', $dateTo);
                $q->whereColumn('actual_delivery_time', '<=', 'scheduled_delivery_time')
                    ->where('status', 'delivered');
            },
            'deliveries as delayed_deliveries' => function ($q) use ($dateFrom, $dateTo) {
                if ($dateFrom) $q->whereDate('delivery_date', '>=', $dateFrom);
                if ($dateTo)   $q->whereDate('delivery_date', '<=', $dateTo);
                $q->whereColumn('actual_delivery_time', '>', 'scheduled_delivery_time')
                    ->where('status', 'delivered');
            },
        ])->withAvg(['feedbacks as average_rating' => function ($q) use ($dateFrom, $dateTo) {
            $q->whereHas('delivery', function ($d) use ($dateFrom, $dateTo) {
                if ($dateFrom) $d->whereDate('delivery_date', '>=', $dateFrom);
                if ($dateTo)   $d->whereDate('delivery_date', '<=', $dateTo);
            });
        }], 'rating')->get();

        return $drivers->map(function ($driver) {
            $total     = $driver->total_deliveries;
            $onTime    = $driver->on_time_deliveries;
            $avgRating = $driver->average_rating ?? 0;

            $onTimeRate       = $total > 0 ? ($onTime / $total) * 100 : 0;
            $performanceScore = (0.7 * ($onTimeRate / 100) + 0.3 * ($avgRating / 5)) * 100;

            return [
                'name'               => $driver->name,
                'total_deliveries'   => $total,
                'on_time_deliveries' => $onTime,
                'delayed_deliveries' => $driver->delayed_deliveries,
                'average_rating'     => round($avgRating, 2),
                'on_time_rate'       => round($onTimeRate, 2),
                'performance_score'  => round($performanceScore, 2),
            ];
        })->toArray();
    }
}
