<?php

namespace App\Http\Controllers\Api\v1;

use App\Exports\GeneralExport;
use App\Models\Hub;
use App\Models\Station;
use Database\Seeders\FacilityRoleSeeder;
use Illuminate\Http\Request;
use App\Http\Resources\HubResource;
use App\Http\Controllers\Controller;
use App\Models\FacilityAccount;
use App\Models\HubUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class HubController extends Controller
{
    /**
     * @OA\Tag(
     *     name="WMS",
     *     description="Warehouse Management System API Endpoints"
     * )
     */
    /**
     * @OA\Schema(
     *     schema="Hub",
     *     type="object",
     *     title="Hub",
     *     @OA\Property(
     *         property="id",
     *         type="integer",
     *         format="int64"
     *     ),
     *     @OA\Property(
     *         property="name",
     *         type="string"
     *     ),
     *     @OA\Property(
     *         property="location",
     *         type="string"
     *     ),
     *     @OA\Property(
     *         property="contact_number",
     *         type="string",
     *         nullable=true
     *     ),
     *     @OA\Property(
     *         property="country",
     *         type="object",
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="en_name", type="string")
     *     ),
     *     @OA\Property(
     *         property="governorate",
     *         type="object",
     *         nullable=true,
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="en_name", type="string")
     *     ),
     *     @OA\Property(
     *         property="state",
     *         type="object",
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="en_name", type="string")
     *     ),
     *     @OA\Property(
     *         property="place",
     *         type="object",
     *         nullable=true,
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="en_name", type="string")
     *     ),
     *     @OA\Property(
     *         property="city",
     *         type="object",
     *         nullable=true,
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="en_name", type="string")
     *     ),
     *     @OA\Property(
     *         property="latitude",
     *         type="string",
     *         nullable=true
     *     ),
     *     @OA\Property(
     *         property="longitude",
     *         type="string",
     *         nullable=true
     *     ),
     *     @OA\Property(
     *         property="address",
     *         type="string",
     *         nullable=true
     *     ),
     *     @OA\Property(
     *         property="created_at",
     *         type="string",
     *         format="date-time"
     *     ),
     *     @OA\Property(
     *         property="updated_at",
     *         type="string",
     *         format="date-time"
     *     )
     * )
     */

    /**
     * List all hubs
     *
     * @OA\Get(
     *   path="/hubs",
     *   tags={"WMS"},
     *   summary="List all hubs",
     *   description="Get paginated list of hubs with optional search",
     *   operationId="getHubsList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for hub name",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Hubs reterived successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           @OA\Property(property="id", type="integer", format="int64"),
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="location", type="string"),
     *           @OA\Property(property="contact_number", type="string", nullable=true),
     *           @OA\Property(property="country", type="object",
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="governorate", type="object", nullable=true,
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="state", type="object",
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="place", type="object", nullable=true,
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="city", type="object", nullable=true,
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="latitude", type="string", nullable=true),
     *           @OA\Property(property="longitude", type="string", nullable=true),
     *           @OA\Property(property="address", type="string", nullable=true),
     *           @OA\Property(property="created_at", type="string", format="date-time"),
     *           @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/hubs",
     *     summary="Get list of hubs with pagination",
     *     tags={"Hubs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for hub names",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=8)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hubs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Hubs retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Hub")),
     *                 @OA\Property(property="first_page_url", type="string"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="last_page_url", type="string"),
     *                 @OA\Property(property="links", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="next_page_url", type="string", nullable=true),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="prev_page_url", type="string", nullable=true),
     *                 @OA\Property(property="to", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index()
    {
        $perPage = request()->input('per_page', 8);
        $searchQuery = request()->input('query');

        $hubs = Hub::query()
            ->with(['country', 'state', 'city', 'governorate', 'place'])
            ->when($searchQuery, function ($query) use ($searchQuery) {
                return $query->where('name', 'like', "%{$searchQuery}%");
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        if ($hubs->isEmpty()) {
            return sendResponse("No hubs found.", [], false, ['No hubs found']);
        }

        return sendResponse(
            "Hubs retrieved successfully.",
            $hubs,
            true,
            []
        );
    }

    /**
     * Get all hubs
     *
     * @OA\Get(
     *   path="/hubs/all",
     *   tags={"WMS"},
     *   summary="Get all hubs",
     *   description="Retrieve all hubs without pagination",
     *   operationId="getAllHubs",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Hubs retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Hubs"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           @OA\Property(property="id", type="integer", format="int64"),
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="location", type="string"),
     *           @OA\Property(property="contact_number", type="string", nullable=true),
     *           @OA\Property(property="country", type="object",
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="governorate", type="object", nullable=true,
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="state", type="object",
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="place", type="object", nullable=true,
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="city", type="object", nullable=true,
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="en_name", type="string")
     *           ),
     *           @OA\Property(property="latitude", type="string", nullable=true),
     *           @OA\Property(property="longitude", type="string", nullable=true),
     *           @OA\Property(property="address", type="string", nullable=true),
     *           @OA\Property(property="created_at", type="string", format="date-time"),
     *           @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function all()
    {
        $hubs = Hub::with('country', "state", "city", "governorate", "place")->get();
        return sendResponse("Hubs", new HubResource($hubs));
    }

    // Store a new hub
    /**
     * Create a new hub
     *
     * @OA\Post(
     *   path="/hubs/store",
     *   tags={"WMS"},
     *   summary="Create a new hub",
     *   description="Create a new hub with specified details",
     *   operationId="createHub",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Hub creation data",
     *     @OA\JsonContent(
     *       required={"name", "location", "country_id", "state_id"},
     *       @OA\Property(property="name", type="string", example="Main Hub"),
     *       @OA\Property(property="location", type="string", example="Central Location"),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="governorate_id", type="integer", nullable=true),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="place_id", type="integer", nullable=true),
     *       @OA\Property(property="city_id", type="integer", nullable=true),
     *       @OA\Property(property="contact_number", type="string", nullable=true, example="+1234567890"),
     *       @OA\Property(property="lat", type="number", nullable=true, example=37.7749),
     *       @OA\Property(property="lng", type="number", nullable=true, example=-122.4194),
     *       @OA\Property(property="address", type="string", nullable=true, example="123 Main St")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Hub created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Hub created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="country", type="object",
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="governorate", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="state", type="object",
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="place", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="city", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="latitude", type="string", nullable=true),
     *         @OA\Property(property="longitude", type="string", nullable=true),
     *         @OA\Property(property="address", type="string", nullable=true),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Error Occured."),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string',
            'country_id' => 'required',
            'governorate_id' => 'nullable',
            'state_id' => 'required',
            'place_id' => 'nullable',
            'city_id' => 'nullable',
            'contact_number' => 'nullable|string|max:15',
            'lat' => 'nullable',
            'lng' => 'nullable',
            'address' => 'nullable',
            'timezone' => 'required|timezone',
        ]);
        DB::beginTransaction();
        try {
            $hub = Hub::create($validated);

            FacilityAccount::create([
                "owner_id" => $hub->id,
                "owner_type" => Hub::class,
            ]);
            $superAdmins = User::role('Super Admin')->select('id')->get();
            foreach ($superAdmins as $sa) {
                HubUser::firstOrCreate([
                    'user_id' => $sa->id,
                    'hub_id' => $hub->id,
                ]);
            }

            FacilityRoleSeeder::createRoles(Hub::class, $hub->id);
            activityLog('Hub Created',"new hub created with name : { $hub->name}");
            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Hub created successfully.", new HubResource($hub));
    }

    // Display a specific hub
    /**
     * Get specific hub details
     *
     * @OA\Get(
     *   path="/hubs/show/{id}",
     *   tags={"WMS"},
     *   summary="Get specific hub details",
     *   description="Get detailed information about a specific hub",
     *   operationId="getHubDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Hub ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Hub fetched successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Hub created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="country", type="object",
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="governorate", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="state", type="object",
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="place", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="city", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="lat", type="string", nullable=true),
     *         @OA\Property(property="lng", type="string", nullable=true),
     *         @OA\Property(property="address", type="string", nullable=true),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Hub not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Hub not found")
     *     )
     *   )
     * )
     */
    public function show($id)
    {
        $hub = Hub::with("country", "state", "city", 'governorate', 'place')->find($id);

        if (!$hub) {
            return sendResponse("Hub created successfully.", [], ["error"]);
        }

        return sendResponse("Hub created successfully.", new HubResource($hub));
    }

    // Update a specific hub
    /**
     * Update hub information
     *
     * @OA\Post(
     *   path="/hubs/update",
     *   tags={"WMS"},
     *   summary="Update hub information",
     *   description="Update existing hub's details",
     *   operationId="updateHub",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Hub update data",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", nullable=true, example="Main Hub"),
     *       @OA\Property(property="location", type="string", nullable=true, example="Central Location"),
     *       @OA\Property(property="contact_number", type="string", nullable=true, example="+1234567890"),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="governorate_id", type="integer", nullable=true),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="place_id", type="integer", nullable=true),
     *       @OA\Property(property="city_id", type="integer", nullable=true),
     *       @OA\Property(property="lat", type="number", nullable=true, example=37.7749),
     *       @OA\Property(property="lng", type="number", nullable=true, example=-122.4194),
     *       @OA\Property(property="address", type="string", nullable=true, example="123 Main St")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Hub updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Hub updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="country", type="object",
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="governorate", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="state", type="object",
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="place", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="city", type="object", nullable=true,
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="en_name", type="string")
     *         ),
     *         @OA\Property(property="lat", type="string", nullable=true),
     *         @OA\Property(property="lng", type="string", nullable=true),
     *         @OA\Property(property="address", type="string", nullable=true),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Hub not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Hub not found")
     *     )
     *   )
     * )
     */
    public function update(Request $request)
    {
        $hub = Hub::find($request->id);

        if (!$hub) {
            return response()->json(['message' => 'Hub not found.'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string',
            'contact_number' => 'nullable|string|max:15',
            'country_id' => 'required',
            'governorate_id' => 'nullable',
            'state_id' => 'required',
            'place_id' => 'nullable',
            'city_id' => 'nullable',
            'lat' => 'nullable',
            'lng' => 'nullable',
            'address' => 'nullable',
            'timezone' => 'required|timezone',
        ]);

        $hub->update($validated);
        activityLog('Hub Updated',"hub updated with name : { $hub->name}");

        return sendResponse("Hub updated successfully.", new HubResource($hub));
    }

    /**
     * Get stations by hub
     *
     * @OA\Get(
     *   path="/hubs/getStationsByHub",
     *   tags={"WMS"},
     *   summary="Get stations associated with a hub",
     *   description="Retrieve all stations for a specific hub",
     *   operationId="getStationsByHub",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="hubId",
     *     in="query",
     *     description="ID of the hub to get stations for",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Stations retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="hub_id", type="integer")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Error fetching stations",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Error fetching stations"),
     *         @OA\Property(property="error", type="string")
     *     )
     *   )
     * )
     */
    public function getStationsByHub()
    {
        $hubId = request('hubId');
        try {
            $stations = Station::where('hub_id', $hubId)->get();

            return response()->json([
                'success' => true,
                'data' => $stations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching stations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete a specific hub

    /**
     * Delete hub
     *
     * @OA\Post(
     *   path="/hubs/delete",
     *   tags={"WMS"},
     *   summary="Delete hub",
     *   description="Delete a hub by its ID",
     *   operationId="deleteHub",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Hub deletion data",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Hub deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Hub deleted successfully.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Hub not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Hub not found")
     *     )
     *   )
     * )
     */
    public function delete(Request $request)
    {
        $hub = Hub::find($request->id);

        if (facility("id") == $hub->id && facility("type") == Hub::class) {
            $request->user()->currentAccessToken()->delete();
        }


        if (!$hub) {
            return sendResponse("Error Occurred.", [], ["Hub not found."], 404);
        }
        try {
            Hub::where('id', $request->id)->delete();
            activityLog('Hub Deleted',"hub deleted with name : { $hub->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], ["Hub not found."], 404);
        }
        return sendResponse("Hub deleted successfully.", []);
    }

    /**
     * Export hubs
     *
     * @OA\Post(
     *   path="/hubs/export",
     *   tags={"WMS"},
     *   summary="Export hubs",
     *   description="Export hubs to CSV or PDF format",
     *   operationId="exportHubs",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Export parameters",
     *     @OA\JsonContent(
     *       required={"format"},
     *       @OA\Property(
     *         property="format",
     *         type="string",
     *         enum={"csv", "pdf"},
     *         example="csv"
     *       ),
     *       @OA\Property(
     *         property="columns",
     *         type="array",
     *         description="Columns to include in export",
     *         @OA\Items(type="string", enum={"id", "name", "location", "contact_number", "country.name", "governorate.en_name", "state.en_name", "place.en_name", "city.en_name", "lat", "lng", "address", "created_at", "updated_at"})
     *       ),
     *       @OA\Property(
     *         property="from_date",
     *         type="string",
     *         format="date",
     *         description="Start date for filtering"
     *       ),
     *       @OA\Property(
     *         property="to_date",
     *         type="string",
     *         format="date",
     *         description="End date for filtering"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Export successful",
     *     @OA\Header(
     *         header="Content-Disposition",
     *         description="File name",
     *         @OA\Schema(type="string")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Invalid format or parameters",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Invalid format specified")
     *     )
     *   )
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format specified', [], false, null, 422);
        }

        // Get columns to export
        $availableColumns = [
            'id',
            'name',
            'location',
            'contact_number',
            'country.name',
            'governorate.en_name',
            'state.en_name',
            'place.en_name',
            'city.en_name',
            'lat',
            'lng',
            'address',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', [
            'id',
            'name',
            'location',
            'contact_number',
            'country.name',
            'governorate.en_name',
            'state.en_name',
            'place.en_name',
            'city.en_name',
            'lat',
            'lng',
            'address',
            'created_at',
            'updated_at'
        ]);

        // Convert string input to array
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }

        // Ensure only valid columns are selected
        $columns = array_intersect($availableColumns, $selectedColumns);

        // Handle relationships
        $relationships = [];
        foreach ($availableColumns as $column) {
            if (strpos($column, '.') !== false) {
                $parts = explode('.', $column);
                array_pop($parts);
                if (!empty($parts)) {
                    $relationships[] = implode('.', $parts);
                }
            }
        }

        $query = Hub::with(array_unique($relationships));

        $query = Hub::query();
        if (!empty($relationships)) {
            $query->with($relationships);
        }

        // Date filtering
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }

        $hubs = $query->get();
        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Station",
                'rows' => $hubs,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'hubs.' . $format;
        return Excel::download(new GeneralExport($hubs, $columns), $name);
    }
}
