<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\DriverLocation;
use App\Models\DriverLocationHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Events\DriverLocationUpdated;

/**
 * @OA\Tag(name="Other", description="Driver Location Management")
 * @OA\Server(url="api/")
 */
class DriverLocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/driver-locations",
     *     summary="Get all driver locations",
     *     description="Retrieves a list of all driver locations with their driver information.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index()
    {
        $locations = DriverLocation::with('driver')->get();

        $result = $locations->map(function ($loc) {
            return [
                'driver_id'   => $loc->driver_id,
                'driver_name' => $loc->driver->name,
                'latitude'    => $loc->latitude,
                'longitude'   => $loc->longitude,
                'status'      => $loc->status,
                'last_updated'=> $loc->last_updated->toDateTimeString(),
            ];
        });

        return response()->json($result);
    }

    /**
     * @OA\Post(
     *     path="/driver-locations",
     *     summary="Store or update driver location",
     *     description="Stores or updates a driver's location. If a location for the driver already exists, it will be updated; otherwise, a new location will be created.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="driver_id", type="integer", description="ID of the driver", example=1),
     *             @OA\Property(property="latitude", type="number", format="float", description="Latitude of the driver's location", example=34.0522),
     *             @OA\Property(property="longitude", type="number", format="float", description="Longitude of the driver's location", example=-118.2437),
     *             @OA\Property(property="status", type="string", description="Status of the driver (on_route, idle, offline)", example="on_route"),
     *             @OA\Property(property="event_type", type="string", description="Type of event (optional)", example="on_route")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     * @param Request $request
     */
    public function storeOrUpdate(Request $request)
    {
        $rules = [
            'driver_id' => 'required|exists:users,id',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'status'    => 'sometimes|in:on_route,idle,offline',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $loc = DriverLocation::updateOrCreate(
            ['driver_id' => $request->input('driver_id')],
            [
                'latitude'     => $request->input('latitude'),
                'longitude'    => $request->input('longitude'),
                'status'       => $request->input('status', 'on_route'),
                'last_updated' => now(),
            ]
        );

        DriverLocationHistory::create([
            'driver_id'       => $loc->driver_id,
            'event_type'      => $request->input('event_type', $loc->status),
            'latitude'        => $loc->latitude,
            'longitude'       => $loc->longitude,
            'event_timestamp' => now(),
        ]);

        broadcast(new DriverLocationUpdated($loc))->toOthers();

        return response()->json([
            'driver_id'    => $loc->driver_id,
            'latitude'     => $loc->latitude,
            'longitude'    => $loc->longitude,
            'status'       => $loc->status,
            'last_updated' => $loc->last_updated->toDateTimeString(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/driver-locations/{driver_id}",
     *     summary="Get driver location by ID",
     *     description="Retrieves the location of a specific driver.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Driver not found",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     * @param int $driver_id
     */
    public function show($driver_id)
    {
        $loc = DriverLocation::with('driver')->where('driver_id', $driver_id)->firstOrFail();

        return response()->json([
            'driver_id'   => $loc->driver_id,
            'driver_name' => $loc->driver->name,
            'latitude'    => $loc->latitude,
            'longitude'   => $loc->longitude,
            'status'      => $loc->status,
            'last_updated'=> $loc->last_updated->toDateTimeString(),
        ]);
    }
}
