<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Exports\GeneralExport;
use App\Http\Requests\StoreDeliveryExceptionRequest;
use App\Http\Requests\UpdateDeliveryExceptionRequest;
use App\Http\Resources\DeliveryExceptionResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\DeliveryException;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(name="OMS", description="Delivery Exception Management")
 * @OA\Controller(description="Manage delivery exceptions.")
 */
class DeliveryExceptionController extends Controller
{
    protected $driver_id = null;
    public function __construct(Request $request)
    {
        $driver_id = $request->driver_id;
    }

    /**
     * @OA\Get(
     *     path="/delivery_exceptions",
     *     summary="Get a list of delivery exceptions.",
     *     description="Retrieve a list of delivery exceptions.  Optionally search using the 'query' parameter.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for delivery exception name.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery Exceptions retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index()
    {
        $perPage = request()->query('per_page', 10); // Default to 10 items per page
        $query = request()->input('query');
        $exceptions = [];

        // Get paginated system exceptions
        $dbExceptions = DeliveryException::query();

        if ($query) {
            $dbExceptions = $dbExceptions
                ->where(function ($q) use ($query) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                        ->orWhereRaw('LOWER(description) LIKE ?', ['%' . strtolower($query) . '%']);
                });
        }

        // Get paginated system exceptions
        $paginatedSystem = $dbExceptions->orderBy('id', 'desc')->paginate($perPage);

        // Get all static exceptions
        $static = system_delivery_exceptions();

        if ($query) {
            $static = array_filter($static, function ($exception) use ($query) {
                return stripos($exception['name'], $query) !== false ||
                    stripos($exception['description'], $query) !== false;
            });
        }
        $static = array_values($static);

        // Calculate pagination for static exceptions
        $staticPerPage = $perPage - $paginatedSystem->count();
        $staticPage = request()->input('static_page', 1);
        $staticOffset = ($staticPage - 1) * $staticPerPage;
        $paginatedStatic = array_slice($static, $staticOffset, $staticPerPage);

        // Format system exceptions
        $exceptions['system'] = $paginatedSystem->map(function ($exception) {
            return [
                'id' => $exception->id,
                'name' => $exception->name,
                'description' => $exception->description ?? '',
                'action' => $exception->action,
                'proof_required' => $exception->proof_required,
                'move_to_crm' => $exception->move_to_crm,
            ];
        });
        // Add static exceptions
        $exceptions['static'] = array_map(function ($exception) {
            return [
                'id' => null, 
                'name' => $exception['label'],
                'description' => $exception['description'] ?? '',
                'action' => "",
                'proof_required' => $exception['proof_required'],
                'move_to_crm' => $exception['move_to_crm'],
            ];
        }, $static);

        // Add pagination metadata
        $pagination = [
            'current_page' => $paginatedSystem->currentPage(),
            'from' => $paginatedSystem->firstItem() ?? 0,
            'last_page' => $paginatedSystem->lastPage(),
            'per_page' => (int)$perPage,
            'to' => $paginatedSystem->lastItem() ?? 0,
            'total' => $paginatedSystem->total() + count($static),
            'static_total' => count($static),
            'system_total' => $paginatedSystem->total(),
            'links' => $paginatedSystem->links() ?: []
        ];

        return sendResponse(
            "Delivery Exceptions retrieved successfully.",
            [
                'data' => $exceptions,
                'pagination' => $pagination
            ],
            true
        );
    }

    /**
     * @OA\Post(
     *     path="/delivery_exceptions/store",
     *     summary="Create a new delivery exception.",
     *     description="Create a new delivery exception.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="Name of the delivery exception"),
     *             @OA\Property(property="description", type="string", description="Description of the delivery exception"),
     *             @OA\Property(property="action", type="string", description="Action to be taken"),
     *             @OA\Property(property="proof_required", type="boolean", description="Whether proof is required"),
     *             @OA\Property(property="move_to_crm", type="boolean", description="Whether to move to CRM"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery Exception created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred while creating delivery exception."
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(StoreDeliveryExceptionRequest $request)
    {
        try {
            $request->validated();
            $data = $request->all();
            $data['proof_required'] = $request->proof_required == 'on' ? true : false;
            $data['move_to_crm'] = $request->move_to_crm == 'on' ? true : false;
            $delivery_exception = DeliveryException::create($data);
            activityLog('delivery exption create',"new delivery exception created called {$delivery_exception->name}");
            return sendResponse("Delivery Exception created successfully.", new DeliveryExceptionResource($delivery_exception));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating delivery_exception.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/delivery_exceptions/update",
     *     summary="Update a delivery exception.",
     *     description="Update an existing delivery exception.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the delivery exception"),
     *             @OA\Property(property="name", type="string", description="Name of the delivery exception"),
     *             @OA\Property(property="description", type="string", description="Description of the delivery exception"),
     *             @OA\Property(property="action", type="string", description="Action to be taken"),
     *             @OA\Property(property="proof_required", type="boolean", description="Whether proof is required"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery Exception updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred while updating delivery exception."
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(UpdateDeliveryExceptionRequest $request)
    {
        try {
            $delivery_exception = DeliveryException::findOrFail($request->id);
            $data = $request->all();
            $data['proof_required'] = $request->proof_required;
            $delivery_exception->update($data);
            activityLog('delivery exption update',"delivery exception with name {$delivery_exception->name} updated");
            return sendResponse("Delivery Exception updated successfully.", new DeliveryExceptionResource($delivery_exception));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating delivery_exception.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/delivery_exceptions/delete",
     *     summary="Delete a delivery exception.",
     *     description="Delete an existing delivery exception.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the delivery exception")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery Exception deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting delivery exception."
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function delete(Request $request)
    {
        try {
            $deliveyexption=DeliveryException::findOrFail($request->id);
            $deliveyexption->delete();
            activityLog('delivery exption delete',"delivery exception with name {$deliveyexption->name} deleted");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Delivery Exception deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/delivery_exceptions/all",
     *     summary="Get all delivery exceptions.",
     *     description="Retrieve all delivery exceptions.",
     *     tags={"OMS"},
     *     @OA\Response(
     *         response=200,
     *         description="Delivery Exceptions"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function all()
    {
        $statuses = DeliveryException::get()->map(function ($status) {
            return [
                'id' => $status->id,
                'name' => $status->name,
                'type' => 'system',
                'description' => $status->description ?? '',
                "action" => $status->action,
                "proof_required" => $status->proof_required,
                "move_to_crm" => $status->move_to_crm,
            ];
        })->toArray();

        $staticStatuses = array_values(system_delivery_exceptions());
        $staticStatuses = array_map(function ($status) {
            $status['type'] = 'static';
            return $status;
        }, $staticStatuses);

        return sendResponse("Delivery Exceptions", array_merge($statuses, $staticStatuses));
    }

    /**
     * @OA\Get(
     *     path="/delivery_exceptions/all_delivery_exceptions",
     *     summary="Get all delivery exceptions for driver app.",
     *     description="Retrieve all delivery exceptions for driver app.",
     *     tags={"OMS"},
     *     @OA\Response(
     *         response=200,
     *         description="Delivery Exceptions"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function all_delivery_exceptions()
    {
        $statuses = DeliveryException::get()->map(function ($status) {
            return [
                'id' => $status->id,
                'name' => $status->name,
                'type' => 'system',
                'description' => $status->description ?? '',
                "action" => $status->action,
                "proof_required" => $status->proof_required,
                "move_to_crm" => $status->move_to_crm,
            ];
        })->toArray();

        $staticStatuses = array_values(system_delivery_exceptions());
        $staticStatuses = array_map(function ($status) {
            $status['type'] = 'static';
            return $status;
        }, $staticStatuses);

        $exceptions = [
            'system' => $statuses,
            'static' => $staticStatuses
        ];

        return sendResponse("Delivery Exceptions", $exceptions);
    }

    /**
     * @OA\Post(
     *     path="/delivery_exceptions/export",
     *     summary="Export delivery exceptions.",
     *     description="Export delivery exceptions in CSV or PDF format.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Export format (csv or pdf)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         description="Selected columns to export (comma-separated)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery exceptions exported successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified."
     *     ),
     *     security={{ "bearerAuth": {} }}
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
            'name',
            'description',
            'action',
            'move_to_crm',
            'proof_required',
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
        $query = DeliveryException::with(array_unique($relationships));
        $query = DeliveryException::query();
        if (!empty($relationships)) {
            $query->with($relationships);
        }
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }
        $exceptions = $query->get();
        if ($format === 'pdf') {
            $html = view('exports.general_export', [
                'name' => "Exceptions",
                'rows' => $exceptions,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }
        $name = 'exceptions.' . $format;
        return Excel::download(new GeneralExport($exceptions, $columns), $name);
    }
}
