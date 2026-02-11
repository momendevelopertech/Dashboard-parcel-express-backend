<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\StateExport;
use App\Http\Requests\StoreStateRequest;
use App\Http\Requests\UpdateStateRequest;
use App\Http\Resources\StateResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\State;
use App\Traits\Searchable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class StateController extends Controller
{
    use Searchable;

    protected function modelQuery()
    {
        return State::query()->select('id', 'en_name', 'ar_name', 'country_id', 'governorate_id');
    }

    /**
     * List states
     * 
     * @OA\Get(
     *   path="/states",
     *   tags={"WMS"},
     *   summary="Get paginated list of states with search",
     *   description="Get a list of states with optional search query and pagination",
     *   operationId="getStatesList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for state name (English or Arabic)",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     description="Number of items per page",
     *     required=false,
     *     @OA\Schema(
     *         type="integer",
     *         default=10
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="States retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="en_name", type="string"),
     *             @OA\Property(property="ar_name", type="string"),
     *             @OA\Property(property="country", type="object"),
     *             @OA\Property(property="governorate", type="object"),
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
     *     path="/api/states/index",
     *     summary="Get list of states with pagination",
     *     tags={"States"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for state names (English or Arabic)",
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
     *         description="States retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index()
    {
        $perPage = max(1, min((int) request()->input('per_page', 8), 100));
        $searchQuery = trim((string) request()->input('query', ''));
        $availableColumns = array_flip(Schema::getColumnListing('states'));
        $stateColumns = array_values(array_filter([
            'id',
            'station_id',
            'country_id',
            'governorate_id',
            'en_name',
            'ar_name',
            'lat',
            'lng',
            'created_at',
            'updated_at',
            'isActive',
        ], fn($column) => isset($availableColumns[$column])));

        $states = State::query()
            ->select($stateColumns)
            ->with([
                'country:id,name',
                'governorate:id,country_id,en_name,ar_name'
            ])
            ->when($searchQuery !== '', function ($query) use ($searchQuery) {
                return $query->where(function ($innerQuery) use ($searchQuery) {
                    $innerQuery->where('en_name', 'like', "%{$searchQuery}%")
                        ->orWhere('ar_name', 'like', "%{$searchQuery}%");
                });
            })
            ->when(!request()->has('show_inactive'), function ($query) {
                return $query->where('isActive', 1);
            })
            ->latest('id')
            ->paginate($perPage);

        if ($states->isEmpty()) {
            return sendResponse("No states found.", [], false, ['No states found']);
        }

        return sendResponse(
            "States retrieved successfully.",
            $states,
            true,
            []
        );
    }

    /**
     * Create state
     * 
     * @OA\Post(
     *   path="/states/store",
     *   tags={"WMS"},
     *   summary="Create a new state",
     *   description="Create a new state with specified details",
     *   operationId="createState",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="State creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "label",
     *         "description"
     *       },
     *       @OA\Property(property="label", type="string", example="State Label", maxLength=255),
     *       @OA\Property(property="description", type="string", example="State description", maxLength=255)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="State created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="State created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="en_name", type="string"),
     *         @OA\Property(property="ar_name", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="governorate_id", type="integer"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
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
    public function store(StoreStateRequest $request)
    {
        $request->validated();
        try {
            $data = $request->all();
            if (!isset($data['isActive'])) {
                $data['isActive'] = 1;
            }
            $state = State::create($data);
            activityLog('state created',"state created called {$state->en_name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("State created successfully.", new StateResource($state));
    }

    /**
     * Update state
     * 
     * @OA\Post(
     *   path="/states/update",
     *   tags={"WMS"},
     *   summary="Update state details",
     *   description="Update an existing state's details",
     *   operationId="updateState",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="State update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id",
     *         "label",
     *         "description"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="label", type="string", example="Updated State Label", maxLength=255),
     *       @OA\Property(property="description", type="string", example="Updated State description", maxLength=255)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="State updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="State updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="en_name", type="string"),
     *         @OA\Property(property="ar_name", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="governorate_id", type="integer"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
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
    public function update(UpdateStateRequest $request)
    {
        $request->validated();
        try {
            $state = State::find($request->id);
            $data = $request->all();
            $state->update($data);
            activityLog('state updated',"state updated called {$state->en_name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("State updated successfully.", new StateResource($state));
    }

    /**
     * Delete state
     * 
     * @OA\Post(
     *   path="/states/delete",
     *   tags={"WMS"},
     *   summary="Delete a state",
     *   description="Delete a state by its ID",
     *   operationId="deleteState",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="State deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="State deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="State deleted successfully."),
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
           $state = State::findOrFail($request->id);
           $state->delete();
           activityLog('state deleted',"state deleted called {$state->en_name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("State deleted successfully.", []);
    }

    /**
     * Get all states
     * 
     * @OA\Get(
     *   path="/states/all",
     *   tags={"WMS"},
     *   summary="Get all states",
     *   description="Retrieve all states without pagination",
     *   operationId="getAllStates",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="States retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="States"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="en_name", type="string"),
     *             @OA\Property(property="ar_name", type="string"),
     *             @OA\Property(property="governorate_id", type="integer"),
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
    $states = State::query()
        ->where('isActive', 1) // 👈 only active states
        ->with('governorate:id,en_name,ar_name,country_id');

    return sendResponse("States", $states->get());
}


    // public function all()
    // {
    //     return sendResponse("States", State::select('id', 'en_name', 'ar_name', 'governorate_id', 'country_id', 'updated_at')->with('governorate:id,en_name,ar_name,country_id')->get());
    // }

    /**
     * Get single state details
     * 
     * @OA\Get(
     *   path="/states/show/{id}",
     *   tags={"WMS"},
     *   summary="Get state details",
     *   description="Get detailed information about a specific state",
     *   operationId="getStateDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="State ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="State retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="State"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="en_name", type="string"),
     *         @OA\Property(property="ar_name", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="governorate_id", type="integer"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="State not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="State not found."),
     *       @OA\Property(property="success", type="boolean", example=false)
     *     )
     *   )
     * )
     */
    public function show($id)
    {
        $state = State::find($id);
        return sendResponse("State", $state);
    }

    // public function export(Request $request)
    // {
    //     $format = $request->input('format'); 
    //     if (!in_array($format, ['csv', 'pdf'])) {
    //         return sendResponse('Invalid format specified', [], false, null, 422);
    //     }

    //     // Get columns to export
    //     $availableColumns = ['id', 'en_name', 'ar_name', 'governorate.en_name', 'governorate.ar_name', 'created_at', 'updated_at'];
    //     $selectedColumns = $request->input('columns', $availableColumns);

    //     // Convert string input to array
    //     if (is_string($selectedColumns)) {
    //         $selectedColumns = explode(',', $selectedColumns);
    //     }

    //     // Ensure only valid columns are selected
    //     $columns = array_intersect($availableColumns, $selectedColumns);

    //     // relationships
    //     $relationships = [];
    //     foreach ($availableColumns as $column) {
    //         if (strpos($column, '.') !== false) {
    //             list($relation) = explode('.', $column);
    //             $relationships[] = $relation;
    //         }
    //     }
    //     $relationships = array_unique($relationships);
    //     // Handle date filtering if present
    //     $query = State::query();
    //     if (!empty($relationships)) {
    //         $query->with($relationships);
    //     }

    //     if ($request->has('from_date') && $request->has('to_date')) {
    //         $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
    //     }
    //     $states = $query->get();

    //     $name = 'states.' . $format;

    //     if ($format === 'pdf') {
    //         // Create PDF using DomPDF
    //         $html = view('exports.states', compact('columns', 'states'))->render();
    //         return response()->json(['html' => $html]);
    //         // $pdf = PDF::loadView('exports.states', [
    //         //     'states' => $states,
    //         //     'columns' => $columns,
    //         //     'date' => date('Y-m-d H:i:s')
    //         // ]);

    //         // return $pdf->download('states_' . date('Y-m-d') . '.pdf');
    //     }
    //     // Export the data
    //     return Excel::download(new StateExport($states, $columns), $name);
    // }

    /**
     * Export states
     * 
     * @OA\Post(
     *   path="/states/export",
     *   tags={"WMS"},
     *   summary="Export states data",
     *   description="Export states data in CSV or PDF format with optional filters",
     *   operationId="exportStates",
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
     *       @OA\Property(
     *         property="columns",
     *         type="array",
     *         description="Columns to export",
     *         @OA\Items(type="string")
     *       ),
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

        $availableColumns = [
            'id',
            'en_name',
            'ar_name',
            'governorate.en_name',
            'governorate.ar_name',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);
        if (is_string($selectedColumns))
            $selectedColumns = explode(',', $selectedColumns);
        $columns = array_values(array_intersect($availableColumns, $selectedColumns));

        $relationships = ['governorate'];

        $query = State::query()->with($relationships);

        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay(),
            ]);
        }

        // احضر الداتا مرة واحدة جاهزة للتصدير
        $states = $query->get();

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "States",
                'rows' => $states,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'states.csv'; // ثبّتنا الاسم لأننا نسمح بـ csv فقط هنا
        return Excel::download(new \App\Exports\StateExport($states, $columns), $name);
    }


    public function edit($id)
    {
        $state = State::find($id);
        $state->zones = $state->zones();
        $state->polygon_geojson = $state->polygon_geojson();
        info("state", ["state" => $state->polygon_geojson]);
        return sendResponse("State", $state);
    }
}
