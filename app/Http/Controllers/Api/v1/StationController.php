<?php

namespace App\Http\Controllers\Api\v1;

use App\Exports\GeneralExport;
use App\Models\Role;
use App\Models\Station;
use App\Models\Zone;
use Database\Seeders\FacilityRoleSeeder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\StateResource;
use App\Http\Resources\StationResource;
use App\Models\FacilityAccount;
use App\Models\StationUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System API Endpoints"
 * )
 */
/**
 * @OA\Schema(
 *     schema="Station",
 *     type="object",
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="location", type="string"),
 *     @OA\Property(property="contact_number", type="string", nullable=true),
 *     @OA\Property(property="hub", type="object",
 *         @OA\Property(property="name", type="string")
 *     ),
 *     @OA\Property(property="lat", type="number", format="float"),
 *     @OA\Property(property="lng", type="number", format="float"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class StationController extends Controller
{
    /**
     * List all stations
     * 
     * @OA\Get(
     *   path="/stations",
     *   tags={"WMS"},
     *   summary="List all stations",
     *   description="Get paginated list of stations with optional search",
     *   operationId="getStationsList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for station name",
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
     *       @OA\Property(property="message", type="string", example="Stations retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="contact_number", type="string", nullable=true),
     *             @OA\Property(property="hub", type="object",
     *                 @OA\Property(property="name", type="string")
     *             ),
     *             @OA\Property(property="lat", type="number", format="float"),
     *             @OA\Property(property="lng", type="number", format="float"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/stations",
     *     summary="Get list of stations with pagination",
     *     tags={"Stations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for station names",
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
     *         description="Stations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stations retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Station")),
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

        $stations = Station::query()
            ->with(['hub'])
            ->when($searchQuery, function ($query) use ($searchQuery) {
                return $query->where('name', 'like', "%{$searchQuery}%");
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        if ($stations->isEmpty()) {
            return sendResponse("No stations found.", [], false, ['No stations found']);
        }

        return sendResponse(
            "Stations retrieved successfully.",
            $stations,
            true,
            []
        );
    }

    /**
     * Create a new station
     * 
     * @OA\Post(
     *   path="/stations/store",
     *   tags={"WMS"},
     *   summary="Create a new station",
     *   description="Create a new station with specified details",
     *   operationId="createStation",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Station creation data",
     *     @OA\JsonContent(
     *       required={"hub_id", "name", "country_id", "governorate_id", "state_id", "lat", "lng", "location"},
     *       @OA\Property(property="hub_id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", example="Main Station"),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="governorate_id", type="integer", example=1),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="lat", type="number", format="float", example=37.7749),
     *       @OA\Property(property="lng", type="number", format="float", example=-122.4194),
     *       @OA\Property(property="location", type="string", example="Central Location"),
     *       @OA\Property(property="contact_number", type="string", nullable=true, example="+1234567890")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Station created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Station created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="hub", type="object",
     *             @OA\Property(property="name", type="string")
     *         ),
     *         @OA\Property(property="lat", type="number", format="float"),
     *         @OA\Property(property="lng", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Error Occurred."),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'hub_id' => 'required|exists:hubs,id',
            'name' => 'required|string|max:255',
            'country_id' => 'required',
            'governorate_id' => 'required',
            'state_id' => 'required',
            'lat' => 'required',
            'lng' => 'required',
            'location' => 'required|string',
            'contact_number' => 'nullable|string|max:15',
        ]);

        DB::beginTransaction();
        try {

            $phoneParts = splitPhoneNumber($request->input('phone'));
            $countryCode = $phoneParts['country_code'];
            $nationalNumber = $phoneParts['national_number'];

            $data = $request->except('phone');
            $data['country_code'] = ltrim($countryCode, '+');
            $data['contact_number'] = $nationalNumber;

            $station = Station::create($data);
            $superAdmins = User::role('Super Admin')->select('id')->get();
            foreach ($superAdmins as $sa) {
                StationUser::firstOrCreate([
                    'user_id' => $sa->id,
                    'station_id' => $station->id,
                ]);
            }
          
            FacilityRoleSeeder::createRoles(Station::class, $station->id);
            activityLog('Station created ',"new station created wiht name: $station->name");
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        DB::commit();
        return sendResponse("Station created successfully.", new StateResource($station));
    }

    /**
     * Get specific station details
     * 
     * @OA\Get(
     *   path="/stations/show/{id}",
     *   tags={"WMS"},
     *   summary="Get specific station details",
     *   description="Get detailed information about a specific station",
     *   operationId="getStationDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Station ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Station fetched successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Station fetched successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="hub", type="object",
     *             @OA\Property(property="name", type="string")
     *         ),
     *         @OA\Property(property="lat", type="number", format="float"),
     *         @OA\Property(property="lng", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Station not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Station not found")
     *     )
     *   )
     * )
     */
    public function show($id)
    {
        $station = Station::with([
            'country',
            'governorate',
            'state',
            'place',
            'city',
            'hub'
        ])->find($id);

        if (!$station) {
            return response()->json(['message' => 'station not found.'], 404);
        }

        return response()->json($station, 200);
    }

    /**
     * Get station for editing
     * 
     * @OA\Get(
     *   path="/stations/edit/{id}",
     *   tags={"WMS"},
     *   summary="Get station for editing",
     *   description="Get station details with relationships for editing",
     *   operationId="editStation",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Station ID",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Station data retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Station data retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="address", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="governorate_id", type="integer"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="place_id", type="integer"),
     *         @OA\Property(property="city_id", type="integer"),
     *         @OA\Property(property="hub_id", type="integer"),
     *         @OA\Property(property="lat", type="number", format="float"),
     *         @OA\Property(property="lng", type="number", format="float"),
     *         @OA\Property(property="country", type="object"),
     *         @OA\Property(property="governorate", type="object"),
     *         @OA\Property(property="state", type="object"),
     *         @OA\Property(property="place", type="object"),
     *         @OA\Property(property="city", type="object"),
     *         @OA\Property(property="hub", type="object"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Station not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Station not found")
     *     )
     *   )
     * )
     */
    public function edit($id)
    {
        $station = Station::with(['country', 'governorate', 'state', 'place', 'city', 'hub'])->find($id);

        if (!$station) {
            return response()->json(['message' => 'station not found.'], 404);
        }

        return sendResponse("Station data retrieved successfully.", $station);
    }

    /**
     * Update station information
     * 
     * @OA\Post(
     *   path="/stations/update",
     *   tags={"WMS"},
     *   summary="Update station information",
     *   description="Update existing station's details",
     *   operationId="updateStation",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Station update data",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", example=1),
     *       @OA\Property(property="hub_id", type="integer", nullable=true, example=1),
     *       @OA\Property(property="name", type="string", nullable=true, example="Main Station"),
     *       @OA\Property(property="location", type="string", nullable=true, example="Central Location"),
     *       @OA\Property(property="contact_number", type="string", nullable=true, example="+1234567890")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Station updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Station updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="location", type="string"),
     *         @OA\Property(property="contact_number", type="string", nullable=true),
     *         @OA\Property(property="hub", type="object",
     *             @OA\Property(property="name", type="string")
     *         ),
     *         @OA\Property(property="lat", type="number", format="float"),
     *         @OA\Property(property="lng", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Station not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Station not found")
     *     )
     *   )
     * )
     */
    public function update(Request $request)
    {
        $station = Station::find($request->id);

        if (!$station) {
            return response()->json(['message' => 'station not found.'], 404);
        }

        $validated = $request->validate([
            'id' => 'required|exists:stations,id',
            'hub_id' => 'required|exists:hubs,id',
            'name' => 'required|string|max:255',
            'location' => 'required|string',
            'address' => 'required|string',
            'contact_number' => 'nullable|string|max:15',
            'country_id' => 'nullable|exists:countries,id',
            'governorate_id' => 'nullable|exists:governorates,id',
            'state_id' => 'nullable|exists:states,id',
            'place_id' => 'nullable|exists:places,id',
            'city_id' => 'nullable|exists:cities,id',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $data = $validated;
        unset($data['id']);

        $station->update($data);
        activityLog('Station updated ',"station updated wiht name: $station->name");

        return sendResponse("Station updated successfully.", new StationResource($station));
    }

    /**
     * Delete station
     * 
     * @OA\Post(
     *   path="/stations/delete",
     *   tags={"WMS"},
     *   summary="Delete station",
     *   description="Delete a station by its ID",
     *   operationId="deleteStation",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Station deletion data",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Station deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Station deleted successfully.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Station not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Station not found")
     *     )
     *   )
     * )
     */
    public function delete(Request $request)
    {
        DB::beginTransaction();
        $station = Station::find($request->id);

        if (facility("id") == $station->id && facility("type") == Station::class) {
            $request->user()->currentAccessToken()->delete();
        }

        if (!$station) {
            return sendResponse("Error Occurred.", [], ["station not found."], 404);
        }

        try {
            $station->station_users()->delete();
            $station->delete();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
        activityLog('Station deleted ',"station deleted wiht name: $station->name");
        DB::commit();
        return sendResponse("Station deleted successfully.", []);
    }

    /**
     * Export stations
     * 
     * @OA\Post(
     *   path="/stations/export",
     *   tags={"WMS"},
     *   summary="Export stations",
     *   description="Export stations to CSV or PDF format",
     *   operationId="exportStations",
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
     *         @OA\Items(type="string", enum={"id", "name", "location", "hub.name", "contact_number", "created_at", "updated_at"})
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
            'hub.name',
            'contact_number',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);

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

        $query = Station::with(array_unique($relationships));

        $query = Station::query();
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

        $stations = $query->get();
        info($stations);
        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Station",
                'rows' => $stations,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'stations.' . $format;
        return Excel::download(new GeneralExport($stations, $columns), $name);
    }

    /**
     * Get all stations
     * 
     * @OA\Get(
     *   path="/stations/all",
     *   tags={"WMS"},
     *   summary="Get all stations",
     *   description="Retrieve all stations without pagination",
     *   operationId="getAllStations",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Stations retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Stations"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="contact_number", type="string", nullable=true),
     *             @OA\Property(property="hub", type="object",
     *                 @OA\Property(property="name", type="string")
     *             ),
     *             @OA\Property(property="lat", type="number", format="float"),
     *             @OA\Property(property="lng", type="number", format="float"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function all()
    {
        $stations = Station::get();
        return sendResponse("Stations", new StationResource($stations));
    }
}
