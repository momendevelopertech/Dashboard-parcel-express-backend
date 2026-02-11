<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\UnitExport;
use App\Models\Unit;
use Illuminate\Http\Request;
use App\Http\Resources\UnitResource;
use App\Http\Requests\StoreUnitRequest;
use Illuminate\Database\QueryException;
use App\Http\Requests\UpdateUnitRequest;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class UnitController extends Controller
{
    /**
     * @OA\Tag(
     *     name="WMS",
     *     description="Warehouse Management System API Endpoints"
     * )
     */

    /**
     * List all units
     * 
     * @OA\Get(
     *   path="/units",
     *   tags={"WMS"}, 
     *   summary="List all units",
     *   description="Get paginated list of units with optional search",
     *   operationId="getUnitsList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for unit name",
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
     *       @OA\Property(property="message", type="string", example="Units fetched successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", example=2),
     *             @OA\Property(property="name", type="string", example="Kg"),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="owner_type", type="string", example="App\\Models\\Hub"),
     *             @OA\Property(property="owner_id", type="integer", example=1),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unauthorized"),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */

    /**
     * @OA\Schema(
     *     schema="Unit",
     *     type="object",
     *     @OA\Property(property="id", type="integer", format="int64"),
     *     @OA\Property(property="name", type="string"),
     *     @OA\Property(property="description", type="string", nullable=true),
     *     @OA\Property(property="owner_type", type="string"),
     *     @OA\Property(property="owner_id", type="integer", format="int64"),
     *     @OA\Property(property="created_at", type="string", format="date-time"),
     *     @OA\Property(property="updated_at", type="string", format="date-time")
     * )
     */
      /**
     * @OA\Get(
     *     path="/api/units",
     *     summary="Get list of units with pagination",
     *     tags={"Units"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for unit name",
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
     *         description="Units retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 8);
        $searchQuery = $request->input('query');
        
        $units = Unit::query()
            ->when($searchQuery, function ($query) use ($searchQuery) {
                return $query->where('name', 'like', "%{$searchQuery}%");
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
            
        if ($units->isEmpty()) {
            return sendResponse("No units found.", [], false, ['No units found']);
        }
        
        return sendResponse("Units retrieved successfully.", new UnitResource($units));
    }

    /**
     * Create a new unit
     * 
     * @OA\Post(
     *   path="/units/store",
     *   tags={"WMS"},
     *   summary="Create a new unit",
     *   description="Create a new unit with specified name and description",
     *   operationId="createUnit",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Unit creation data",
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name", type="string", example="Kg"),
     *       @OA\Property(property="description", type="string", nullable=true)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Unit created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Unit created successfully."),
     *       @OA\Property(property="data", ref="#/components/schemas/Unit")
     *     )
     *   ),
     *   @OA\Response(
     *     response=400,
     *     description="Bad Request",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Validation error"),
     *         @OA\Property(property="errors", type="object")
     *     )
     *   )
     * )
     */
    public function store(StoreUnitRequest $request)
    {
        $validated = $request->validated();
        $unit = Unit::create($validated);
        activityLog('unit created',"new unit created with name : {$unit->name}");

        return sendResponse("Unit created successfully.", new UnitResource($unit));
    }

    /**
     * Get single unit details
     * 
     * @OA\Get(
     *   path="/units/getSingle",
     *   tags={"WMS"},
     *   summary="Get single unit details",
     *   description="Get detailed information about a specific unit",
     *   operationId="getUnitDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="query",
     *     description="Unit ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Unit fetched successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Unit fetched successfully."),
     *       @OA\Property(property="data", ref="#/components/schemas/Unit")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Unit not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unit not found")
     *     )
     *   )
     * )
     */
    public function getSingle()
    {
        try {
            $unitId = request()->id;

            $unit = Unit::where('id', $unitId)
                ->first();

            if (!$unit) {
                return sendResponse("unit not found.", [], false, [], 404);
            }
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching the shelf.", [], false, [$e->getMessage()], 422);
        }

        return sendResponse("unit fetched successfully.", new UnitResource($unit));
    }

    /**
     * Update unit information
     * 
     * @OA\Post(
     *   path="/units/update",
     *   tags={"WMS"},
     *   summary="Update unit information",
     *   description="Update existing unit's name",
     *   operationId="updateUnit",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Unit update data",
     *     @OA\JsonContent(
     *       required={"id", "name"},
     *       @OA\Property(property="id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", example="Kg")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Unit updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Unit updated successfully."),
     *       @OA\Property(property="data", ref="#/components/schemas/Unit")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Unit not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unit not found")
     *     )
     *   )
     * )
     */
    public function update(UpdateUnitRequest $request)
    {
        try {
            $unit = Unit::findOrFail($request->id);
            $unit->name = $request->name;
            $unit->save();
            activityLog('unit updated',"unit updated with name : {$unit->name}");
            return sendResponse("unit updated successfully.", new UnitResource($unit));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating unit.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * Delete unit
     * 
     * @OA\Post(
     *   path="/units/delete",
     *   tags={"WMS"},
     *   summary="Delete unit",
     *   description="Delete a unit by its ID",
     *   operationId="deleteUnit",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Unit deletion data",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Unit deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Unit deleted successfully.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Unit not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unit not found")
     *     )
     *   )
     * )
     */
    public function delete(Request $request)
    {
        try {
           $unit =Unit::findOrFail($request->id);
           $unit->delete();
           activityLog('unit deleted',"unit deleted with name : {$unit->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Unit deleted successfully.", []);
    }

    /**
     * Get all units
     * 
     * @OA\Get(
     *   path="/units/all",
     *   tags={"WMS"},
     *   summary="Get all units",
     *   description="Retrieve all units without pagination",
     *   operationId="getAllUnits",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Units retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Units retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/Unit")
     *       )
     *     )
     *   )
     * )
     */
    public function all()
    {
        return sendResponse("Units", new UnitResource(Unit::all()));
    }

    /**
     * Export units
     * 
     * @OA\Post(
     *   path="/units/export",
     *   tags={"WMS"},
     *   summary="Export units",
     *   description="Export units to CSV or PDF format",
     *   operationId="exportUnits",
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
     *         @OA\Items(type="string", enum={"id", "name", "created_at", "updated_at"})
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
     *         @OA\Property(property="message", type="string", example="Invalid format")
     *     )
     *   )
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format', [], false, null, 422);
        }

        $availableColumns = [
            'id',
            'name',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);

        // Validate and filter columns
        $columns = is_string($selectedColumns)
            ? array_intersect($availableColumns, explode(',', $selectedColumns))
            : array_intersect($availableColumns, $selectedColumns);

        // Query builder with date filtering
        $query = Unit::query();
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }
        $units = $query->get();

        if ($format === 'pdf') {
            $html = view('exports.units', compact('columns', 'units'))->render();
            return response()->json(['html' => $html]);
        }

        return Excel::download(
            new UnitExport($units, $columns),
            'units.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }
}
