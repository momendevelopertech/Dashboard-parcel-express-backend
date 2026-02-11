<?php

namespace App\Http\Controllers\Api\v1;

use App\Exports\GeneralExport;
use App\Exports\UnitExport;
use Illuminate\Database\QueryException;
use App\Models\Governorate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGovernorateRequest;
use App\Http\Requests\UpdateGovernorateRequest;
use App\Http\Resources\GovernorateResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class GovernorateController extends Controller
{
    /**
     * List governorates
     * 
     * @OA\Get(
     *   path="/governorates",
     *   tags={"WMS"},
     *   summary="Get paginated list of governorates with search",
     *   description="Get a list of governorates with optional search query",
     *   operationId="getGovernoratesList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for governorate name (English or Arabic)",
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
     *       @OA\Property(property="message", type="string", example="Governorates retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="en_name", type="string"),
     *             @OA\Property(property="ar_name", type="string"),
     *             @OA\Property(property="country", type="object"),
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
     *     path="/api/governorates/index",
     *     summary="Get list of governorates with pagination",
     *     tags={"Governorates"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for governorate names (English or Arabic)",
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
     *         description="Governorates retrieved successfully"
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
        $search = $request->input('query');
        
        $governorates = Governorate::select('id', 'en_name', 'ar_name', 'country_id','isActive')
            ->with('country:id,name')
            ->when($search, function ($query) use ($search) {
                return $query->where('en_name', 'like', "%{$search}%")
                    ->orWhere('ar_name', 'like', "%{$search}%");
            })
            ->when(!$request->has('show_inactive'), function ($query) {
                return $query->where('isActive', 1);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        if ($governorates->isEmpty()) {
            return sendResponse("No governorates found.", [], false, ['No governorates found']);
        }

        return sendResponse(
            "Governorates retrieved successfully.",
            $governorates,
            true,
            []
        );
    }


    /**
     * Create governorate
     * 
     * @OA\Post(
     *   path="/governorates/store",
     *   tags={"WMS"},
     *   summary="Create a new governorate",
     *   description="Create a new governorate with specified details",
     *   operationId="createGovernorate",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Governorate creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "en_name",
     *         "ar_name",
     *         "country_id"
     *       },
     *       @OA\Property(property="en_name", type="string", example="Governorate Name", maxLength=255),
     *       @OA\Property(property="ar_name", type="string", example="اسم المحافظة", maxLength=255),
     *       @OA\Property(property="country_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Governorate created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Governorate created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="en_name", type="string"),
     *         @OA\Property(property="ar_name", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error occurred while creating governorate."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store(StoreGovernorateRequest $request)
    {
        try {
            // Create a new governorate
            $governorate = Governorate::create([
                'en_name' => $request->en_name,
                'ar_name' => $request->ar_name,
                'country_id' => $request->country_id,
                'isActive' => $request->isActive ?? 1,
            ]);
             activityLog('governorate created', "new governorate created with name : {$governorate->en_name}");
       
            return sendResponse(
                "Governorate created successfully.",
                new GovernorateResource($governorate),
                true
            );
        } catch (QueryException $e) {
            return sendResponse(
                "An error occurred while creating the governorate.",
                [],
                [$e->getMessage()],
                500
            );
        }
    }

    /**
     * Update governorate
     * 
     * @OA\Post(
     *   path="/governorates/update",
     *   tags={"WMS"},
     *   summary="Update governorate details",
     *   description="Update an existing governorate's details",
     *   operationId="updateGovernorate",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Governorate update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id",
     *         "en_name",
     *         "ar_name"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="en_name", type="string", example="Updated Governorate Name", maxLength=255),
     *       @OA\Property(property="ar_name", type="string", example="اسم المحافظة المحدث", maxLength=255),
     *       @OA\Property(property="country_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Governorate updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Governorate updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="en_name", type="string"),
     *         @OA\Property(property="ar_name", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error occurred while updating governorate."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
public function update(UpdateGovernorateRequest $request)
{
    try {
        $governorate = Governorate::findOrFail($request->id);

        if ($request->filled('en_name')) {
            $governorate->en_name = $request->en_name;
        }

        if ($request->filled('ar_name')) {
            $governorate->ar_name = $request->ar_name;
        }

        if ($request->has('isActive')) {
            $governorate->isActive = $request->isActive;
        }

        $governorate->save();

        activityLog(
            'governorate updated',
            "governorate updated with name : {$governorate->en_name}"
        );

        return sendResponse(
            "Governorate updated successfully.",
            new GovernorateResource($governorate)
        );

    } catch (QueryException $e) {
        return sendResponse(
            "Error occurred while updating the governorate.",
            [],
            [$e->getMessage()],
            422
        );
    }
}


    /**
     * Delete governorate
     * 
     * @OA\Post(
     *   path="/governorates/delete",
     *   tags={"WMS"},
     *   summary="Delete a governorate",
     *   description="Delete a governorate by its ID",
     *   operationId="deleteGovernorate",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Governorate deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Governorate deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Governorate deleted successfully."),
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
     *     description="Validation error",
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
            $governorate = Governorate::findOrFail($request->id);
            $governorate->delete();
            activityLog('governorate deleted', "governorate deleted with name : {$governorate->en_name}");
        } catch (QueryException $e) {
            return sendResponse("Error occurred while deleting the governorate.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Governorate deleted successfully.", []);
    }

    /**
     * Export governorates
     * 
     * @OA\Post(
     *   path="/governorates/export",
     *   tags={"WMS"},
     *   summary="Export governorates data",
     *   description="Export governorates data in CSV or PDF format with optional filters",
     *   operationId="exportGovernorates",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Export parameters",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "format"
     *       },
     *       @OA\Property(property="format", type="string", example="csv"),
     *       @OA\Property(property="from_date", type="string", format="date", example="2025-01-01"),
     *       @OA\Property(property="to_date", type="string", format="date", example="2025-12-31")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Export successful",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Export successful"),
     *       @OA\Property(
     *         property="data",
     *         type="string",
     *         description="Exported content (for PDF) or filename (for CSV)"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Invalid format",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Invalid format specified"),
     *       @OA\Property(property="success", type="boolean", example=false)
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
            'en_name',
            'ar_name',
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

        $query = Governorate::with(array_unique($relationships));

        $query = Governorate::query();
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

        $governorates = $query->get();

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Governorates",
                'rows' => $governorates,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'governorates.' . $format;
        return Excel::download(new GeneralExport($governorates, $columns), $name);
    }

    /**
     * Get all governorates
     * 
     * @OA\Get(
     *   path="/governorates/all",
     *   tags={"WMS"},
     *   summary="Get all governorates",
     *   description="Retrieve all governorates without pagination",
     *   operationId="getAllGovernorates",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Governorates retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Governorates"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="en_name", type="string"),
     *             @OA\Property(property="ar_name", type="string"),
     *             @OA\Property(property="country_id", type="integer"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
public function all(Request $request)
{
    $governorates = Governorate::query()
        ->where('isActive', 1); // 👈 only active governorates

    if ($request->timestamp) {
        $governorates = $governorates->where('updated_at', '<', $request->timestamp);
        if ($governorates->count() < 0) {
            return sendResponse("Governorates", []);
        }
    }
    
    return sendResponse(
        "Governorates",
        $governorates->select(
            'id',
            'en_name',
            'ar_name',
            'country_id',
            'updated_at'
        )->get()
    );
}



    /**
     * Get single governorate details
     * 
     * @OA\Get(
     *   path="/governorates/show/{id}",
     *   tags={"WMS"},
     *   summary="Get governorate details",
     *   description="Get detailed information about a specific governorate",
     *   operationId="getGovernorateDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Governorate ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Governorate retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Governorate"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="en_name", type="string"),
     *         @OA\Property(property="ar_name", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Governorate not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Governorate not found."),
     *       @OA\Property(property="success", type="boolean", example=false)
     *     )
     *   )
     * )
     */
    public function show($id)
    {
        $governorate = Governorate::find($id);
        return sendResponse("Governorate", $governorate);
    }

    public function edit($id)
    {
        $governorate = Governorate::find($id);
        $governorate->polygon_geojson = $governorate->polygon_geojson();
        $governorate->zones = $governorate->zones();
        return sendResponse("Governorate", $governorate);
    }
}
