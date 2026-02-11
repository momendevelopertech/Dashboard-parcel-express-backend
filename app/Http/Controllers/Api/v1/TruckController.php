<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\GeneralExport;
use App\Http\Requests\StoreTruckRequest;
use App\Http\Requests\UpdateTruckRequest;
use App\Http\Resources\TruckResource;
use App\Models\TransferTask;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Truck;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="Fleet & Driver Management",
 *     description="API endpoints for managing trucks and drivers."
 * )
 * @OA\Server(url="api/")
 */
class TruckController extends Controller
{
    /**
     * @OA\Get(
     *     path="/trucks",
     *     summary="Get a list of trucks.",
     *     description="Retrieves a list of trucks.  Allows querying by number plate.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for truck number plate.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Trucks retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $trucks = Truck::query(); //byOwner();
        $perPage = request()->input('per_page', 8);
        if (request()->has('query')) {
            $query = request()->input('query');
            $trucks = $trucks
                ->whereRaw('LOWER(number_plate) LIKE ?', ['%' . strtolower($query) . '%'])
                ->with('truck_driver')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

        } else {
            $trucks = $trucks->with('truck_driver')->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("Trucks reterived successfully.", new TruckResource(resource: $trucks), []);
    }

    /**
     * @OA\Post(
     *     path="/trucks/store",
     *     summary="Create a new truck.",
     *     description="Creates a new truck.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="number_plate", type="string", description="Truck number plate", example="ABC-123"),
     *             @OA\Property(property="type", type="string", description="Truck type (truck, van, mini_van, pickup)", example="truck"),
     *             @OA\Property(property="truck_driver_id", type="integer", description="ID of the truck driver (nullable)", example=1),
     *             @OA\Property(property="status", type="string", description="Truck status (active, inactive, maintenance)", example="active"),
     *             @OA\Property(property="company", type="string", description="Truck Company", example="BMW"),
     *             @OA\Property(property="color", type="string", description="Truck Color", example="red"),
     *             @OA\Property(property="notes", type="string", description="Truck notes", example="notes"),
     *             @OA\Property(property="barcode", type="string", description="Truck barcode", example="1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Truck created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreTruckRequest $request)
    {
        $request->validated();
        DB::beginTransaction();
        try {
            $truck = Truck::create($request->all());
            activityLog('truck created',"new truck created with number plate : {$truck->barcode}");
            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Truck created successfully.", new TruckResource($truck));
    }

    /**
     * @OA\Post(
     *     path="/trucks/update",
     *     summary="Update an existing truck.",
     *     description="Updates an existing truck.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the truck to update"),
     *             @OA\Property(property="number_plate", type="string", description="Truck number plate"),
     *             @OA\Property(property="type", type="string", description="Truck type (truck, van, mini_van, pickup)"),
     *             @OA\Property(property="driver_id", type="integer", description="ID of the truck driver (nullable)"),
     *             @OA\Property(property="status", type="string", description="Truck status (active, inactive, maintenance)", example="active"),
     *             @OA\Property(property="company", type="string", description="Truck Company", example="BMW"),
     *             @OA\Property(property="color", type="string", description="Truck Color", example="red"),
     *             @OA\Property(property="notes", type="string", description="Truck notes", example="notes"),
     *             @OA\Property(property="barcode", type="string", description="Truck barcode", example="1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Truck updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateTruckRequest $request)
    {
        $request->validated();
        DB::beginTransaction();
        try {
            $truck = Truck::find($request->id);
            $truck->update($request->all());
            activityLog('truck updated',"truck updated with number plate : {$truck->barcode}");
            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Truck updated successfully.", new TruckResource($truck));
    }

    /**
     * @OA\Post(
     *     path="/trucks/delete",
     *     summary="Delete a truck.",
     *     description="Deletes a truck.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the truck to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Truck deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            $truck = Truck::findOrFail($request->id);
            $truck->delete();
            activityLog('truck deleted',"truck deleted with number plate : {$truck->barcode}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Truck deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/trucks/all",
     *     summary="Get all trucks.",
     *     description="Retrieves all trucks.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Trucks retrieved successfully."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Trucks", new TruckResource(Truck::get()));
    }

    /**
     * @OA\Get(
     *     path="/trucks/view/{barcode}",
     *     summary="View truck details and associated shipments.",
     *     description="Retrieves details for a specific truck and its associated transfer tasks.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="barcode",
     *         in="path",
     *         description="Barcode of the truck.",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Loaded shipments retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Truck not found."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function view($barcode)
    {
        $truck = Truck::where('barcode', $barcode)->first();
        if (!$truck) {
            return sendResponse("Truck not found.", [], false, ["Invalid truck barcode."], 422);
        }
        $transferTasks = TransferTask::where('truck_id', $truck->id)
            ->with(['truck_driver', 'destinations.destinationShipments.shipment.consignee', 'destinations.destination', 'destinations.origin'])
            ->get();
        $data = [
            'truck' => $truck,
            'tasks' => $transferTasks
        ];
        Log::info($data);
        return sendResponse("Loaded shipments retrieved successfully.", new TruckResource($data));
    }

    /**
     * @OA\Get(
     *     path="/trucks/printTruckBarcode",
     *     summary="Print truck barcode.",
     *     description="Generates HTML for printing a truck barcode.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the truck.",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="HTML for truck barcode generated successfully."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function printTruckBarcode()
    {
        try {
            $id = request('id');
            $truck = $truck = Truck::findOrFail($id);
            $html = view('printTruckBarcode', compact('truck'))->render();
            Log::info("Log created successfully: $truck->barcode");
            return response()->json(['html' => $html]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/trucks/export",
     *     summary="Export trucks data.",
     *     description="Exports trucks data in CSV or PDF format.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Format of the exported data (csv, pdf).",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Columns to include in the export (comma-separated).",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for filtering (YYYY-MM-DD).",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date for filtering (YYYY-MM-DD).",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Trucks exported successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified."
     *     ),
     *     security={{"bearerAuth": {}}}
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
            'barcode',
            'number_plate',
            'color',
            'company',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }
        $columns = array_intersect($availableColumns, $selectedColumns);
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
        $query = Truck::with(array_unique($relationships));
        $query = Truck::query();
        if (!empty($relationships)) {
            $query->with($relationships);
        }
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }
        $trucks = $query->get();
        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Trucks",
                'rows' => $trucks,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }
        $name = 'trucks.' . $format;
        return Excel::download(new GeneralExport($trucks, $columns), $name);
    }
}
