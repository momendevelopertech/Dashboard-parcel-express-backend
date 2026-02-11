<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\GeneralExport;
use App\Http\Requests\StoreTruckDriverRequest;
use App\Http\Requests\UpdateTruckDriverRequest;
use App\Http\Resources\TruckDriverResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\TruckDriver;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="Fleet & Driver Management",
 *     description="API endpoints for managing truck drivers."
 * )
 *
 * @OA\Server(url="api/")
 */
class TruckDriverController extends Controller
{
    /**
     * @OA\Get(
     *     path="/truck_drivers",
     *     summary="Get a list of truck drivers.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search term for driver name.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Truck drivers retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $trucks = TruckDriver::byOwner();
        $perPage = request()->input('per_page', 8);
        if (request()->has('query')) {
            $searchTerm = request()->input('query');
            $trucks = $trucks
                ->with('user')
                ->whereHas('user', function ($userQuery) use ($searchTerm) {
                    $userQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
                })
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $trucks = $trucks->with('user')->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("Truck Drivers reterived successfully.", new TruckDriverResource(resource: $trucks), []);
    }

    /**
     * @OA\Post(
     *     path="/truck_drivers/store",
     *     summary="Create a new truck driver.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id_card_number", type="string", description="ID card number", example="1234567890"),
     *             @OA\Property(property="phone_number", type="string", description="Phone number", example="+15551234567"),
     *             @OA\Property(property="name", type="string", description="Name", example="John Doe"),
     *             @OA\Property(property="email", type="string", description="Email", example="John@gmail.com"),
     *             @OA\Property(property="password", type="string", description="Password", example="12345678"),
     *             @OA\Property(property="company", type="string", description="Company", example="Company Name"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Truck driver created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreTruckDriverRequest $request)
    {
        $request->validated();
        DB::beginTransaction();
        try {
            $data = $request->all();
            if ($data['phone_number']) {
                $phoneSplit = splitPhoneNumber($data['phone_number']);
                $data['country_code'] = $phoneSplit['country_code'];
                $data['phone_number'] = $phoneSplit['national_number'];
                $user_data = $data;
                $user_data['country_code'] = $phoneSplit['country_code'];
                $user_data['phone'] = $phoneSplit['national_number'];
                $user = User::create($user_data);
            }
            $data['user_id'] = $user->id;
            $truck = TruckDriver::create($data);
            activityLog('truck driver create',"new truck driver created called {$truck->name}");
            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occured.", [], false, [$e->getMessage()], 422);
        }
        return sendResponse("Truck Driver created successfully.", new TruckDriverResource($truck));
    }

    /**
     * @OA\Post(
     *     path="/truck_drivers/update",
     *     summary="Update an existing truck driver.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the truck driver to update"),
     *             @OA\Property(property="id_card_number", type="string", description="ID card number", example="1234567890"),
     *             @OA\Property(property="phone_number", type="string", description="Phone number", example="+15551234567"),
     *             @OA\Property(property="name", type="string", description="Name", example="John Doe"),
     *             @OA\Property(property="email", type="string", description="Email", example="John@gmail.com"),
     *             @OA\Property(property="password", type="string", description="Password", example="12345678"),
     *             @OA\Property(property="company", type="string", description="Company", example="Company Name"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Truck driver updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateTruckDriverRequest $request)
    {
        $request->validated();
        DB::beginTransaction();
        try {
            $data = $request->all();
            $truck = TruckDriver::find($request->id);
            $user_data = $data;
            if ($data['phone_number']) {
                $phoneSplit = splitPhoneNumber($data['phone_number']);
                $data['country_code'] = $phoneSplit['country_code'];
                $data['phone_number'] = $phoneSplit['national_number'];
                $user_data['country_code'] = $phoneSplit['country_code'];
                $user_data['phone'] = $phoneSplit['national_number'];
            }
            $truck->update($data);
            $user = User::find($truck->user_id);
            $user->update($user_data);
            activityLog('truck driver update',"truck driver updated called {$truck->name}");
            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Truck Driver updated successfully.", new TruckDriverResource($truck));
    }

    /**
     * @OA\Post(
     *     path="/truck_drivers/delete",
     *     summary="Delete a truck driver.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the truck driver to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Truck driver deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
           $truck = TruckDriver::findOrFail($request->id);
           $truck->delete();
            activityLog('truck driver delete',"truck driver deleted called {$truck->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Truck Driver deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/truck_drivers/all",
     *     summary="Get all truck drivers.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Truck drivers retrieved successfully."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Truck Drivers", new TruckDriverResource(TruckDriver::byOwner()->with('user')->get()));
    }

    /**
     * @OA\Post(
     *     path="/truck_drivers/export",
     *     summary="Export truck drivers data.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Export format (csv, pdf)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Columns to export (comma-separated)",
     *         @OA\Schema(type="string")
     *     ),
     *      @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter from date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *      @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter to date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Truck drivers exported successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
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

        // Get columns to export
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

        $query = TruckDriver::with(array_unique($relationships));

        $query = TruckDriver::query();
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

        $drivers = $query->get();

        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Truck Driver",
                'rows' => $drivers,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'truck_drivers.' . $format;
        return Excel::download(new GeneralExport($drivers, $columns), $name);
    }
}
