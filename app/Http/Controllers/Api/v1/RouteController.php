<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Shipment;
use App\Services\RouteOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        $query = Route::query();
        if ($request->filled('route_date')) {
            $query->where('route_date', $request->input('route_date'));
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }
        $perPage = $request->query('per_page', 15);
        $routes = $query->orderBy('route_code', 'asc')->paginate($perPage);
        $routes->getCollection()->transform(function ($route) {
            return [
                'id'                 => $route->id,
                'route_code'         => $route->route_code,
                'route_date'         => $route->route_date->toDateString(),
                'driver_id'          => $route->driver_id,
                'driver_name'        => $route->driver->name,
                'start_location'     => $route->start_location,
                'end_location'       => $route->end_location,
                'stops_count'        => $route->stops()->count(),
                'estimated_duration' => $route->estimated_duration,
                'estimated_distance' => $route->estimated_distance,
                'status'             => $route->status,
                'created_at'         => $route->created_at,
                'updated_at'         => $route->updated_at
            ];
        });
        return sendResponse('Routes retrieved successfully', $routes);
    }

    public function plan(Request $request, RouteOptimizationService $optimizer)
    {
        $rules = [
            'route_date' => 'required|date',
            'driver_id'  => 'required|exists:users,id',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return sendResponse('Validation failed', [], false, $validator->errors(), 422);
        }
        $date     = $request->input('route_date');
        $driverId = $request->input('driver_id');
        $shipments = Shipment::with('consignee')
            ->where('driver_id', $driverId)
            ->whereDate('created_at', $date)
            ->get();
        if ($shipments->isEmpty()) {
            return sendResponse('No shipments found', [], false, ['No shipments for this driver on the selected date.'], 404);
        }
        $waypoints = $shipments->map(function ($shipment) {
            return [
                'shipment_id'  => $shipment->id,
                'latitude'  => $shipment->consignee->latitude,
                'longitude' => $shipment->consignee->longitude,
                'address'   => $shipment->delivery_address
            ];
        })->toArray();
        $optimized = $optimizer->optimize($waypoints, [
            'driver_id'  => $driverId,
            'route_date' => $date,
        ]);
        $latestId = Route::max('id') ?? 0;
        $routeCode = 'RT-' . str_pad($latestId + 1, 3, '0', STR_PAD_LEFT);
        $firstStop = $optimized['shipmented_stops'][0] ?? null;
        $lastStop  = end($optimized['shipmented_stops']) ?? null;
        $startLoc  = $firstStop['address'] ?? null;
        $endLoc    = $lastStop['address'] ?? null;
        $route = Route::create([
            'route_code'         => $routeCode,
            'route_date'         => $date,
            'driver_id'          => $driverId,
            'start_location'     => $startLoc,
            'end_location'       => $endLoc,
            'estimated_distance' => $optimized['total_distance'],
            'estimated_duration' => $optimized['total_duration'],
            'polyline'           => $optimized['polyline'],
            'status'             => 'planned',
        ]);
        foreach ($optimized['shipmented_stops'] as $index => $stop) {
            RouteStop::create([
                'route_id'  => $route->id,
                'shipment_id'  => $stop['shipment_id'],
                'sequence'  => $index,
                'latitude'  => $stop['latitude'],
                'longitude' => $stop['longitude'],
            ]);
        }
        return sendResponse('Route created successfully', [
            'id'                 => $route->id,
            'route_code'         => $route->route_code,
            'route_date'         => $route->route_date->toDateString(),
            'driver_id'          => $route->driver_id,
            'driver_name'        => $route->driver->name,
            'start_location'     => $route->start_location,
            'end_location'       => $route->end_location,
            'stops_count'        => $route->stops()->count(),
            'estimated_duration' => $route->estimated_duration,
            'estimated_distance' => $route->estimated_distance,
            'status'             => $route->status,
            'created_at'         => $route->created_at,
            'updated_at'         => $route->updated_at
        ], true, [], 201);
    }

    public function show($id)
    {
        $route = Route::with(['driver', 'stops.shipment'])
            ->findOrFail($id);

        return sendResponse('Route details retrieved successfully', [
            'id'                 => $route->id,
            'route_code'         => $route->route_code,
            'route_date'         => $route->route_date->toDateString(),
            'driver_id'          => $route->driver_id,
            'driver_name'        => $route->driver->name,
            'start_location'     => $route->start_location,
            'end_location'       => $route->end_location,
            'stops'              => $route->stops->map(function ($stop) {
                return [
                    'stop_id'   => $stop->id,
                    'shipment_id'  => $stop->shipment_id,
                    'sequence'  => $stop->sequence,
                    'latitude'  => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'shipment_details' => [
                        'customer_name' => $stop->shipment->customer->name,
                        'address'       => $stop->shipment->delivery_address,
                    ]
                ];
            }),
            'estimated_duration' => $route->estimated_duration,
            'estimated_distance' => $route->estimated_distance,
            'polyline'           => $route->polyline,
            'status'             => $route->status,
            'created_at'         => $route->created_at,
            'updated_at'         => $route->updated_at
        ]);
    }

    public function update(Request $request, Route $route)
    {
        $rules = [
            'status' => 'sometimes|in:planned,in_progress,completed',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return sendResponse('Validation failed', [], false, $validator->errors(), 422);
        }

        $route->update($request->only(['status']));
        return sendResponse('Route status updated successfully', $route);
    }

    public function reshipmentStops(Request $request, Route $route)
    {
        $rules = [
            'stops'            => 'required|array',
            'stops.*.stop_id'  => 'required|exists:route_stops,id',
            'stops.*.sequence' => 'required|integer',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return sendResponse('Validation failed', [], false, $validator->errors(), 422);
        }

        foreach ($request->input('stops') as $stopData) {
            $stop = RouteStop::findOrFail($stopData['stop_id']);
            $stop->sequence = $stopData['sequence'];
            $stop->save();
        }

        $route->load(['stops.shipment']);
        return sendResponse('Route stops reshipmented successfully', $route);
    }

    public function destroy($id)
    {
        $route = Route::findOrFail($id);
        $route->delete();
        return sendResponse('Route deleted successfully', [], true, [], 204);
    }
}
