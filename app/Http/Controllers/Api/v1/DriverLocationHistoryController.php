<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\DriverLocationHistory;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Other", description="Driver Location History APIs")
 * @OA\Server(url="{{ config('app.url') }}/api/documentation", description="L5 Swagger API Server")
 */
class DriverLocationHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/driver-location-histories/{driver_id}",
     *     summary="Get driver location history",
     *     description="Retrieves the location history for a given driver within specified date range.",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date of the range (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date of the range (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="event_type", type="string"),
     *             @OA\Property(property="latitude", type="number", format="float"),
     *             @OA\Property(property="longitude", type="number", format="float"),
     *             @OA\Property(property="event_timestamp", type="string", format="date-time"),
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Driver not found"),
     * )
     */
    public function index(Request $request, $driver_id)
    {
        $query = DriverLocationHistory::where('driver_id', $driver_id);

        if ($request->filled('from_date')) {
            $query->whereDate('event_timestamp', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('event_timestamp', '<=', $request->input('to_date'));
        }

        $history = $query->orderBy('event_timestamp', 'asc')->get();

        return response()->json($history->map(function ($h) {
            return [
                'id'             => $h->id,
                'event_type'     => $h->event_type,
                'latitude'       => $h->latitude,
                'longitude'      => $h->longitude,
                'event_timestamp'=> $h->event_timestamp->format('h:i A'),
            ];
        }));
    }
}
