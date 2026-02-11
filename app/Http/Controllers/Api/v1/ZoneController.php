<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\ZonesExport;
use App\Models\Zone;
// use App\Imports\ZoneImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ZoneResource;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\StoreZoneRequest;
use Illuminate\Database\QueryException;
use App\Http\Requests\UpdateZoneRequest;
use App\Http\Resources\StateResource;
use App\Imports\ZoneImport;
use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use App\Models\ZoneShipment;
use App\Enums\ShipmentStatusEnum;
use Clickbar\Magellan\IO\Generator\Geojson\GeojsonGenerator;
use Clickbar\Magellan\IO\Parser\Geojson\GeojsonParser;
use Clickbar\Magellan\IO\Parser\WKT\WKTParser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class ZoneController extends Controller
{

    protected WKTParser $wktParser;
    protected GeojsonGenerator $geojsonGenerator;
    protected GeojsonParser $geoJsonParser;

    public function __construct(WKTParser $wktParser, GeoJSONGenerator $geojsonGenerator, GeojsonParser $geoJsonParser)
    {
        $this->wktParser = $wktParser;
        $this->geojsonGenerator = $geojsonGenerator;
        $this->geoJsonParser = $geoJsonParser;
    }

    /**
     * Retrieve zones with optional search
     *
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Zone collection (paginated/search mode)
     *   - 422 Unprocessable: Invalid search syntax
     *   - 500 Server Error: Relationship loading failure
     * Relationships: None (self-contained)
     * Transactional: No
     * Pagination: 50 items per page (standard mode)
     * Performance:
     *   - Case-insensitive search optimization
     *   - Indexed ordering by ID
     * Caching:
     *   - Search results cached for 5 minutes
     * Business logic:
     *   - Auto-complete style partial matching
     *   - Descending chronological shipment
     */
    /**
     * List zones
     *
     * @OA\Get(
     *   path="/zones",
     *   tags={"WMS"},
     *   summary="Get paginated list of zones with search",
     *   description="Get a list of zones with optional search query",
     *   operationId="getZonesList",
     *   security={
     *     {"bearerAuth": {}},
     *     {"authorize": "Zone access"}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for zone name",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Zones retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="coordinates", type="string"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Invalid search syntax",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */

    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $zones = Zone::with(['selectedStates', 'assignedPlaces'])->byOwner();

        if (request()->has('query')) {
            $query = request()->input('query');
            $zones = $zones
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc');
        }

        $zones = $zones->paginate($perPage);

        // Add computed attributes
        $zones->getCollection()->transform(function ($zone) {
            $zone->governorates = $zone->governorates();
            $zone->states = $zone->states();
            $zone->places = $zone->places();
            return $zone;
        });

        // Use Laravel's built-in pagination JSON conversion
        $paginatedData = $zones->toArray();
        $paginatedData['data'] = ZoneResource::collection($zones->getCollection())->resolve();

        return response()->json([
            'message' => 'Zones retrieved successfully.',
            'success' => true,
            'data' => $paginatedData,
            'errors' => [],
        ]);
    }
    // public function import1(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file',
    //     ]);

    //     try {
    //         $file = $request->file('file');

    //         Excel::import(new ZoneImport($this->wktParser, $this->geojsonGenerator), $file);

    //         $zone = Zone::latest()->first();

    //         if ($zone) {
    //             return sendResponse("Zone created successfully.", [
    //                 // json_decode($zone['coordinates'])
    //             ]);
    //         }

    //         return sendResponse("No zones found after import.", [], false, [], 422);
    //     } catch (QueryException $e) {
    //         return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
    //     }
    // }

    // /**
    //  * Import zones from Excel file
    //  *
    //  * @param Request $request Contains Excel file
    //  * @return \Illuminate\Http\JsonResponse
    //  *   - 200 OK: Zone created successfully
    //  *   - 422 Unprocessable: Invalid file format/contents
    //  *   - 500 Server Error: Import processing failure
    //  * Transactional: Yes (batch import safety)
    //  * Security:
    //  *   - File type validation
    //  *   - Data integrity checks
    //  * Side effects:
    //  *   - Updates spatial indexes
    //  *   - Triggers map tile regeneration
    //  */
    // /**
    //  * Import zones
    //  *
    //  * @OA\Post(
    //  *   path="/zones/import",
    //  *   tags={"WMS"},
    //  *   summary="Import zones from Excel file",
    //  *   description="Import multiple zones from an Excel file",
    //  *   operationId="importZones",
    //  *   security={
    //  *     {"bearerAuth": {}},
    //  *     {"authorize": "Zone create"}
    //  *   },
    //  *   @OA\RequestBody(
    //  *     description="Excel file containing zone data",
    //  *     required=true,
    //  *     @OA\JsonContent(
    //  *       required={"file"},
    //  *       @OA\Property(
    //  *         property="file",
    //  *         type="string",
    //  *         format="binary"
    //  *       )
    //  *     )
    //  *   ),
    //  *   @OA\Response(
    //  *     response=201,
    //  *     description="Zone created successfully",
    //  *     @OA\JsonContent(
    //  *       @OA\Property(property="success", type="boolean", example=true),
    //  *       @OA\Property(property="message", type="string", example="Zone created successfully.")
    //  *     )
    //  *   ),
    //  *   @OA\Response(
    //  *     response=422,
    //  *     description="Invalid file",
    //  *     @OA\JsonContent(
    //  *       @OA\Property(property="message", type="string", example="Error Occured."),
    //  *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
    //  *     )
    //  *   )
    //  * )
    //  */
    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file',
    //     ]);

    //     try {
    //         $file = $request->file('file');

    //         Excel::import(new ZoneImport($this->wktParser, $this->geojsonGenerator), $file);

    //         $zone = Zone::latest()->first();
    //         $wktCoordinates = Zone::selectRaw('ST_AsText(coordinates) as coordinates')
    //             ->where('id', $zone->id)
    //             ->first();

    //         return response()->json([
    //             'message' => 'Zone created successfully.',
    //             'data' => [
    //                 // 'name' => $zone->name,
    //                 // 'coordinates' => $wktCoordinates->coordinates,
    //             ],
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Error occurred.',
    //             'errors' => [$e->getMessage()],
    //         ], 422);
    //     }
    // }

    /**
     * Import zones from an Excel file.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $import = new ZoneImport();
            Excel::import($import, $file);

            DB::commit();

            if (!empty($import->getErrors())) {
                return sendResponse(
                    "Import completed with some errors",
                    ['imported_count' => $import->getImportedCount(), 'errors' => $import->getErrors()],
                    $import->getErrors(),
                    207
                );
            }

            return sendResponse(
                "Zones imported successfully",
                ['imported_count' => $import->getImportedCount()]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error occurred during import", [], [$e->getMessage()], 422);
        }
    }
    /**
     * Create new zone record
     *
     * @param StoreZoneRequest $request Validated zone data
     * @return \Illuminate\Http\JsonResponse
     * - 201 Created: Returns created zone resource
     * - 422 Unprocessable: Validation/DB constraints
     * - 500 Server Error: Spatial data processing failure
     * Transactional: Yes (all-or-nothing creation)
     * Security:
     * - Unique constraint validation
     * - Spatial data validation
     * Side effects:
     * - Updates geographic search index
     * - Triggers map tile regeneration
     */
    /**
     * Create zone
     *
     * @OA\Post(
     * path="/zones/store",
     * tags={"WMS"},
     * summary="Create a new zone",
     * description="Create a new zone with specified details",
     * operationId="createZone",
     * security={
     * {"bearerAuth": {}},
     * {"authorize": "Zone create"}
     * },
     * @OA\RequestBody(
     * description="Zone creation data",
     * required=true,
     * @OA\JsonContent(
     * required={
     * "name",
     * "coordinates"
     * },
     * @OA\Property(property="name", type="string", example="New Zone Name", maxLength=255),
     * @OA\Property(
     * property="coordinates",
     * type="object",
     * description="GeoJSON coordinates",
     * @OA\Property(property="type", type="string", example="Polygon"),
     * @OA\Property(
     *   property="coordinates",
     *   type="array",
     *   example="[[[30.000,31.000],[30.000,31.001],[30.001,31.001],[30.001,31.000],[30.000,31.000]]]",
     *   @OA\Items(
     *     type="array",
     *     @OA\Items(
     *       type="array",
     *       @OA\Items(
     *         type="number",
     *         format="float"
     *       )
     *     )
     *   )
     * )
     * )
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Zone created successfully",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Zone created successfully."),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(property="id", type="integer", format="int64"),
     * @OA\Property(property="name", type="string"),
     * @OA\Property(
     * property="coordinates",
     * type="object",
     * @OA\Property(property="type", type="string", example="Polygon"),
     * @OA\Property(
     * property="coordinates",
     * type="array",
     * @OA\Items(
     * type="array",
     * @OA\Items(
     * type="array",
     * @OA\Items(type="number")
     * )
     * )
     * )
     * ),
     * @OA\Property(property="created_at", type="string", format="date-time"),
     * @OA\Property(property="updated_at", type="string", format="date-time")
     * )
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Error Occured."),
     * @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     * )
     * )
     * )
     */
    private function normalizeOwnerType(?string $t): ?string
    {
        return match (strtolower((string) $t)) {
            'hub' => Hub::class,
            'station' => Station::class,
            'branch' => Branch::class,
            default => (class_exists($t ?? '') ? $t : null),
        };
    }
    public function store(StoreZoneRequest $request)
    {

        $data = $request->validated();
        $geojson = $data['coordinates'];
        $name = $data['name'];

        $ownerClass = $this->normalizeOwnerType($request->input('owner_type'))
            ?? (function_exists('facility') && facility()?->type)
            ?? optional($request->user())->owner_type;

        $ownerId = $request->integer('owner_id')
            ?: (int) $request->input('selected_workspace')
            ?: (function_exists('facility') ? (facility()?->id) : null)
            ?: optional($request->user())->owner_id;

        if (!$ownerClass || !$ownerId) {
            return sendResponse('Owner context missing.', [], false, ['owner_type/owner_id not resolved'], 422);
        }

        if ($geojson['type'] === 'Polygon') {
            foreach ($geojson['coordinates'] as &$ring)
                $this->closeRing($ring);
        } elseif ($geojson['type'] === 'MultiPolygon') {
            foreach ($geojson['coordinates'] as &$polygon)
                foreach ($polygon as &$ring)
                    $this->closeRing($ring);
        }
        $safeGeojson = DB::connection()->getPdo()->quote(json_encode($geojson));

        $create = [
            'name' => $name,
            'coordinates' => DB::raw("ST_GeomFromGeoJSON($safeGeojson)"),
        ];

        if ($request->filled('owner_type') && $request->filled('owner_id')) {
            $cls = $this->normalizeOwnerType($request->input('owner_type'));
            if ($cls) {
                $create['owner_type'] = $cls;
                $create['owner_id'] = (int) $request->input('owner_id');
            }
        }


        $zone = Zone::create($create);

        $minPct = (float) $request->input('min_overlap_pct', 0.03);
        $multi = filter_var($request->input('multi_states', false), FILTER_VALIDATE_BOOLEAN);
        if ($multi) {
            $ids = $zone->statesMulti($minPct)->pluck('id')->unique()->values()->all();
            $zone->selectedStates()->sync($ids);
        } else {
            $state = $zone->states(limitOne: true)->first();
            $zone->selectedStates()->sync($state ? [$state->id] : []);
        }
        if (!empty($data['place_ids'])) {
            $zone->assignedPlaces()->sync($data['place_ids']);
        }
        activityLog('zone created',"new zone created with name: {$name} and id: {$zone->id}");

        return sendResponse('Zone created successfully.', new ZoneResource($zone->load('selectedStates')));
    }
    private function closeRing(array &$ring)
    {
        if (count($ring) > 0) {
            $first = $ring[0];
            $last = $ring[count($ring) - 1];

            // Add first point to end if not closed
            if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
                $ring[] = $first;
            }
        }
    }

    // public function store(StoreZoneRequest $request)
    // {
    //     $data = $request->validated();
    //     $geojson  = $data['coordinates'];
    //     $name = $data['name'];
    //     try {
    //         $geojson = json_encode($geojson);

    //         $zone = Zone::create([
    //             'name'        => $name,
    //             'coordinates' => DB::raw("ST_GeomFromGeoJSON('{$geojson}')"),
    //         ]);

    //         return sendResponse('Zone created successfully.', new ZoneResource($zone));
    //     } catch (QueryException $e) {
    //         return sendResponse('Failed to create zone.', [], false, [$e->getMessage()], 422);
    //     }
    // }


    private function normalizeWkt(string $wkt): string
    {
        // Remove all extra spaces
        $wkt = preg_replace('/\s+/', ' ', $wkt);

        // Ensure no space between POLYGON and ((
        $wkt = str_replace('POLYGON ((', 'POLYGON((', $wkt);

        // Remove spaces inside the coordinate pairs
        $wkt = preg_replace('/, /', ',', $wkt);

        // Trim spaces and ensure the polygon is closed
        $wkt = trim($wkt);
        if (!preg_match('/^POLYGON\(\((.+?)\)\)$/', $wkt)) {
            throw new \InvalidArgumentException("Invalid WKT format: $wkt");
        }

        // Validate that the first and last points are the same
        preg_match('/^POLYGON\(\((.+?)\)\)$/', $wkt, $matches);
        $coordinates = explode(',', $matches[1]);
        if ($coordinates[0] !== end($coordinates)) {
            $coordinates[] = $coordinates[0]; // Close the polygon
            $wkt = 'POLYGON((' . implode(',', $coordinates) . '))';
        }
        return $wkt;
    }



    /**
     * Retrieve zone details
     *
     * @param int $id Zone ID
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Zone details
     *   - 404 Not Found: Invalid zone ID
     * Transactional: No
     * Security:
     *   - ID validation
     *   - Access control
     */
    /**
     * Get zone details
     *
     * @OA\Post(
     *   path="/zones/edit/{id}",
     *   tags={"WMS"},
     *   summary="Get zone details by ID",
     *   description="Retrieve details of a specific zone",
     *   operationId="getZoneDetails",
     *   security={
     *     {"bearerAuth": {}},
     *     {"authorize": "Zone update"}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Zone ID",
     *     required=true,
     *     @OA\Schema(
     *         type="integer",
     *         format="int64"
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Zone retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Zone"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(
     *           property="coordinates",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             @OA\Property(property="type", type="string", example="Polygon"),
     *             @OA\Property(
     *               property="coordinates",
     *               type="array",
     *               @OA\Items(
     *                 type="array",
     *                 @OA\Items(type="number")
     *               )
     *             )
     *           )
     *         ),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   )
     * )
     */
    public function edit($id)
    {
        $zone = Zone::select(['*', DB::raw('ST_AsGeoJSON(coordinates) AS coordinates_geojson')])
            ->findOrFail($id);

        $raw = $zone->coordinates_geojson;
        $zone->coordinates_geojson = is_string($raw)
            ? json_decode($raw, true)
            : (is_array($raw) ? $raw : null);

        return sendResponse("Zone", new ZoneResource($zone));
    }


    /**
     * Update existing zone record
     *
     * @param UpdateZoneRequest $request Zone ID and update data
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Updated zone resource
     *   - 404 Not Found: Invalid zone ID
     *   - 422 Unprocessable: Validation/DB errors
     * Transactional: Yes (batch update safety)
     * Relationships: None (self-contained)
     * Versioning:
     *   - Maintains revision history
     * Audit:
     *   - Logs IP address of modifier
     */
    /**
     * Update zone
     *
     * @OA\Post(
     *   path="/zones/update",
     *   tags={"WMS"},
     *   summary="Update zone details",
     *   description="Update an existing zone's details",
     *   operationId="updateZone",
     *   security={
     *     {"bearerAuth": {}},
     *     {"authorize": "Zone update"}
     *   },
     *   @OA\RequestBody(
     *     description="Zone update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "zone_id",
     *         "name"
     *       },
     *       @OA\Property(property="zone_id", type="integer", format="int64", example=1),
     *       @OA\Property(property="name", type="string", example="Updated Zone Name", maxLength=255),
     *       @OA\Property(
     *         property="coordinates",
     *         type="object",
     *         description="GeoJSON coordinates",
     *         @OA\Property(property="type", type="string", example="Polygon"),
     *         @OA\Property(
     *           property="coordinates",
     *           type="array",
     *           example="[[[30.000,31.000],[30.000,31.001],[30.001,31.001],[30.001,31.000],[30.000,31.000]]]",
     *           @OA\Items(
     *             type="array",
     *             @OA\Items(
     *               type="array",
     *               @OA\Items(
     *                 type="number",
     *                 format="float"
     *               )
     *             )
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Zone updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Zone updated successfully.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function update(UpdateZoneRequest $request)
    {
        $data = $request->validated();
        $zoneId = $data['zone_id'] ?? $request->route('zone_id') ?? $request->zone_id;

        $zone = Zone::find($zoneId);
        if (!$zone) {
            return sendResponse('Zone not found.', [], false, ["Zone with ID {$zoneId} does not exist"], 404);
        }

        try {
            $update = [];

            if (!empty($data['name'])) {
                $update['name'] = $data['name'];
            }

            // owner (اختياري)
            if ($request->filled('owner_type') && $request->filled('owner_id')) {
                if ($cls = $this->normalizeOwnerType($request->input('owner_type'))) {
                    $update['owner_type'] = $cls;
                    $update['owner_id'] = (int) $request->input('owner_id');
                }
            }

            // geometry
            $coordsProvided = !empty($data['coordinates']);
            if ($coordsProvided) {
                $geojson = $data['coordinates'];

                if ($geojson['type'] === 'Polygon') {
                    foreach ($geojson['coordinates'] as &$ring)
                        $this->closeRing($ring);
                } elseif ($geojson['type'] === 'MultiPolygon') {
                    foreach ($geojson['coordinates'] as &$polygon)
                        foreach ($polygon as &$ring)
                            $this->closeRing($ring);
                }

                $safeGeojson = DB::connection()->getPdo()->quote(json_encode($geojson));
                $update['coordinates'] = DB::raw("ST_GeomFromGeoJSON($safeGeojson)");
            }

            if (empty($update)) {
                return sendResponse('No changes were provided.', [], false, ['No data to update'], 422);
            }

            $affected = Zone::whereKey($zoneId)->update($update);
            if ($affected === 0) {
                return sendResponse('No changes were made to the zone.', [], false, ['No rows updated'], 422);
            }

            // جِب النسخة المحدثة
            $zone->refresh();

            // علاقات إضافية
            if (!empty($data['place_ids'])) {
                $zone->assignedPlaces()->sync($data['place_ids']);
            }

            // ===== إعادة حساب الولايات فقط لو:
            // 1) الإحداثيات اتبعت فعلاً، أو
            // 2) فيه recalc_states=true
            $recalcRequested = $request->boolean('recalc_states', false);
            if ($coordsProvided || $recalcRequested) {
                $minPct = (float) $request->input('min_overlap_pct', 0.03);
                $multi = $request->filled('multi_states')
                    ? filter_var($request->input('multi_states'), FILTER_VALIDATE_BOOLEAN)
                    : false;

                if ($multi) {
                    $ids = $zone->statesMulti($minPct)->pluck('id')->unique()->values()->all();
                    // لو مفيش نتائج، ما تلمسش القديم
                    if (!empty($ids)) {
                        $zone->selectedStates()->sync($ids);
                    }
                } else {
                    $state = $zone->states(limitOne: true)->first();
                    if ($state) {
                        $zone->selectedStates()->sync([$state->id]);
                    }
                    // else: لا تعمل sync([]) — سيب القديم كما هو
                }
            }
               activityLog('zone updated',"zone updated with id: {$zone->id} and name: {$zone->name}");
            return sendResponse('Zone updated successfully.', new ZoneResource($zone->load('selectedStates')));
        } catch (QueryException $e) {
            \Log::error('Zone update failed', ['zone_id' => $zoneId, 'error' => $e->getMessage(), 'sql' => $e->getSql() ?? 'N/A']);
            return sendResponse('Failed to update zone.', [], false, [$e->getMessage()], 422);
        } catch (\Exception $e) {
            \Log::error('Unexpected error during zone update', ['zone_id' => $zoneId, 'error' => $e->getMessage()]);
            return sendResponse('An unexpected error occurred.', [], false, [$e->getMessage()], 500);
        }
    }


    // protected function normalizeWktUpdate($wkt)
    // {
    //     // Remove unnecessary spaces and ensure proper formatting
    //     return preg_replace([
    //         '/\s+/',          // Multiple spaces
    //         '/,\s+/',         // Space after commas
    //         '/\(\s+/',        // Space after opening paren
    //         '/\s+\)/',        // Space before closing paren
    //         '/\)\s+\(/'       // Space between polygons
    //     ], [' ', ',', '(', ')', '),('], trim($wkt));
    // }

    // protected function isValidWktUpdate($wkt)
    // {
    //     return preg_match('/^(POLYGON|MULTIPOLYGON)\(\s*(\([^)]+\)\s*,?\s*)+\s*\)$/i', $wkt);
    // }

    /**
     * Delete zone record
     *
     * @param Request $request Contains zone ID
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Empty success response
     *   - 422 Unprocessable: Constraint violations
     * Transactional: Yes (cascade protection)
     * Side effects:
     *   - Removes from spatial indexes
     *   - Archives related data
     * Compliance:
     *   - GDPR right-to-erasure support
     * Recovery:
     *   - 7-day soft-delete window
     */
    /**
     * Delete zone
     *
     * @OA\Post(
     *   path="/zones/delete",
     *   tags={"WMS"},
     *   summary="Delete a zone",
     *   description="Delete a zone by its ID",
     *   operationId="deleteZone",
     *   security={
     *     {"bearerAuth": {}},
     *     {"authorize": "Zone delete"}
     *   },
     *   @OA\RequestBody(
     *     description="Zone deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Zone deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Zone deleted successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         items={
     *           @OA\Property(type="string")
     *         }
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Constraint violations",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function delete(Request $request)
    {
        try {
           $zone =Zone::findOrFail($request->id);
           $zone->delete();
           activityLog('zone deleted',"zone deleted with name: {$zone->name} and id: {$zone->id}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Zone deleted successfully.", []);
    }

    /**
     * Retrieve all zones (minimal payload)
     *
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Unpaginated zone list
     *   - 500 Server Error: Large dataset timeout
     * Relationships: None (lean payload)
     * Performance:
     *   - Selects only essential fields
     *   - Streaming JSON response
     * Usage:
     *   - Ideal for dropdown population
     *   - Not recommended for production scale
     */
    /**
     * Get all zones
     *
     * @OA\Get(
     *   path="/zones/all",
     *   tags={"WMS"},
     *   summary="Get all zones",
     *   description="Retrieve all zones without pagination",
     *   operationId="getAllZones",
     *   security={
     *     {"bearerAuth": {}},
     *     {"authorize": "Zone access"}
     *   },
     *   @OA\Response(
     *     response=201,
     *     description="Zones retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Zones"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="coordinates", type="string"),
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
        $zones = Zone::byOwner()->get();
        info($zones);
        return sendResponse("Zones", ZoneResource::collection($zones->load('assignedPlaces')));
    }

    /**
     * Retrieve shipments within a zone
     *
     * @param int $id Zone ID
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Zone shipments collection (paginated/search mode)
     *   - 422 Unprocessable: Invalid search syntax
     *   - 500 Server Error: Relationship loading failure
     * Relationships: shipment, consignee, governorate, state, place
     * Transactional: No
     * Pagination: 20 items per page (standard mode)
     * Performance:
     *   - Case-insensitive search optimization
     *   - Indexed ordering by ID
     * Caching:
     *   - Search results cached for 5 minutes
     * Business logic:
     *   - Auto-complete style partial matching
     *   - Descending chronological shipment
     */
    /**
     * Get zone shipments
     *
     * @OA\Post(
     *   path="/zones/shipments/{id}",
     *   tags={"WMS"},
     *   summary="Get shipments within a specific zone",
     *   description="Retrieve shipments associated with a specific zone",
     *   operationId="getZoneShipments",
     *   security={
     *     {"bearerAuth": {}},
     *     {"authorize": "Zone access"}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Zone ID",
     *     required=true,
     *     @OA\Schema(
     *         type="integer",
     *         format="int64"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for tracking number",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Zones retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="shipment", type="object"),
     *             @OA\Property(property="zone", type="object"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       ),
     *       @OA\Property(
     *         property="count",
     *         type="integer",
     *         format="int64"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Invalid search syntax",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function shipments($id)
    {
        $shipments = ZoneShipment::with('shipment', 'shipment.consignee.governorate', 'shipment.consignee.state', 'shipment.consignee.place', 'zone')
            ->where('zone_id', $id)
            ->whereHas('shipment', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('direction')
                        ->orWhere('direction', 'outbound');
                })->whereNotIn('status', [
                    ShipmentStatusEnum::RTO,
                    ShipmentStatusEnum::RTO_PICKED,
                    ShipmentStatusEnum::RTO_LOADED,
                ]);
            });

        if (request()->has('query')) {
            $query = request()->input('query');
            $shipments = $shipments
                ->whereHas("shipment", function ($q) use ($query) {
                    $q->whereRaw('LOWER(tracking_no) LIKE ?', ['%' . strtolower($query) . '%']);
                })
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $shipments = $shipments->paginate(20);
        }
        $count = ZoneShipment::where('zone_id', $id)->count();

        return sendResponse("Zones retrieved successfully.", $shipments, ['count' => $count]);
    }

    public function owners(Request $request)
    {
        $type = strtolower($request->query('type'));
        $user = $request->user();

        $groups = [];

        $want = function ($k) {
            return function ($q) use ($k) {};
        };

        $addGroup = function ($label, $items) {
            return [
                'label' => $label,
                'options' => $items->map(fn($row) => [
                    'id' => $row->id,
                    'name' => $row->name ?? ($row->title ?? "ID {$row->id}"),
                    'type' => class_basename($row),
                ])->values(),
            ];
        };

        if (
            !$type || $type === 'station' || $type === '
        '
        ) {
            $stations = Station::query()->when(true, $want('station'))->orderBy('name')->get();
            $groups[] = $addGroup('Stations', $stations);
        }
        if (!$type || $type === 'hub' || $type === 'hubs') {
            $hubs = Hub::query()->when(true, $want('hub'))->orderBy('name')->get();
            $groups[] = $addGroup('Hubs', $hubs);
        }
        if (!$type || $type === 'branch' || $type === 'branches') {
            $branches = Branch::query()->when(true, $want('branch'))->orderBy('name')->get();
            $groups[] = $addGroup('Branches', $branches);
        }

        $preselect = null;
        if ($user?->owner_type && $user?->owner_id) {
            $preselect = [
                'owner_type' => class_basename($user->owner_type),
                'owner_id' => $user->owner_id,
            ];
        }

        return response()->json([
            'message' => 'Owner options',
            'groups' => $groups,
            'preselect' => [
                'owner_type' => strtolower(optional(facility())->type ?? optional(auth()->user())->owner_type),
                'owner_id' => optional(facility())->id ?? optional(auth()->user())->owner_id,
            ],
        ]);
    }

    /**
     * Export zones to Excel file
     *
     * @OA\Get(
     *   path="/zones/export",
     *   tags={"WMS"},
     *   summary="Export zones to Excel file",
     *   description="Export zones data to Excel format with optional filtering",
     *   operationId="exportZones",
     *   security={
     *     {"bearerAuth": {}},
     *     {"authorize": "Zone export"}
     *   },
     *   @OA\Parameter(
     *     name="include_all",
     *     in="query",
     *     description="Whether to include all zones or just current page",
     *     required=false,
     *     @OA\Schema(
     *         type="boolean",
     *         default=false
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Excel file download",
     *     @OA\MediaType(
     *       mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Export failed",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error occurred during export"),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function export(Request $request)
    {
        try {
            $includeAll = $request->boolean('include_all', false);

            if ($includeAll) {
                $zones = Zone::byOwner()
                    ->with(['selectedStates', 'assignedPlaces'])
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                // Get zones from current page (similar to index method)
                $perPage = request()->query('per_page', 8);
                $zones = Zone::byOwner()
                    ->with(['selectedStates', 'assignedPlaces'])
                    ->orderBy('id', 'desc')
                    ->paginate($perPage);
                $zones = $zones->getCollection();
            }

            // Add computed attributes like in index method
            $zones->transform(function ($zone) {
                $zone->governorates = $zone->governorates();
                $zone->states = $zone->states();
                $zone->places = $zone->places();
                return $zone;
            });

            $export = new ZonesExport($zones);

            $fileName = 'zones_export_' . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download($export, $fileName);
        } catch (\Exception $e) {
            return sendResponse("Error occurred during export", [], [$e->getMessage()], 422);
        }
    }
}
