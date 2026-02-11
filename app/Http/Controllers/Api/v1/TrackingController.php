<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingController extends Controller
{
    public function show(string $tracking)
    {
        $row = DB::table('trackings')->where('tracking_number', $tracking)->first();
        if (!$row)
            return response()->json(['code' => 'resource_not_found', 'message' => 'Tracking not found'], 404);
        $events = DB::table('tracking_events')->where('tracking_number', $tracking)->orderBy('timestamp')->get()->map(fn($e) => [
            'status' => $e->status,
            'status_code' => strtoupper($e->status_code),
            'description' => $e->description,
            'location' => ['city' => $e->city, 'country' => $e->country],
            'timestamp' => optional($e->timestamp)->toIso8601String(),
        ]);
        return response()->json([
            'tracking_number' => $tracking,
            'internal_shipment_id' => $row->shipment_id,
            'current_status' => $row->current_status,
            'estimated_delivery' => optional($row->eta_at)->toIso8601String(),
            'events' => $events,
        ]);
    }


    public function index(Request $request)
    {
        $shipmentId = $request->query('shipment_id');
        $from = $request->query('from');
        $limit = min((int) $request->query('limit', 100), 500);
        $q = DB::table('tracking_events');
        if ($shipmentId)
            $q->where('shipment_id', $shipmentId);
        if ($from)
            $q->where('timestamp', '>=', $from);
        $events = $q->orderBy('timestamp')->limit($limit)->get()->map(fn($e) => [
            'tracking_number' => $e->tracking_number,
            'status' => $e->status,
            'status_code' => strtoupper($e->status_code),
            'description' => $e->description,
            'location' => ['city' => $e->city, 'country' => $e->country],
            'timestamp' => optional($e->timestamp)->toIso8601String(),
        ]);
        return response()->json(['data' => $events]);
    }
}
