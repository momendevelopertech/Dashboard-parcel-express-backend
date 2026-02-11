<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\StockOutTaskResource;
use App\Models\AssignShipmentToShelf;
use App\Models\StockOutTask;
use App\Models\StockOutTaskShipment;
use App\Observers\StockOutTaskObserver;
use Exception;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Other", description="Stock Out Task Management")
 * @OA\Server(url="/api/v1")
 */
#[ObservedBy([StockOutTaskObserver::class])]
class StockOutTaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/stockout_tasks",
     *     summary="Get a list of Stock Out Tasks",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="StockOutTasks retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index()
    {
        $perPage = request()->input('per_page', 8);
        $tasks = StockOutTask::where('status', 'pending');
        if (request()->has('query')) {
            $query = request()->input('query');
            $tasks = $tasks
                ->whereRaw('LOWER(status) LIKE ?', ['%' . strtolower($query) . '%'])
                ->with(['created_by', 'shipments.shipment'])
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $tasks = $tasks->with(['created_by', 'shipments.shipment.consignee'])->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("StockOutTasks retrieved successfully.", new StockOutTaskResource($tasks), []);
    }


    /**
     * @OA\Post(
     *     path="/stockout_tasks/store",
     *     summary="Create a new Stock Out Task",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipments", type="array", description="Array of shipment tracking numbers", @OA\Items(type="string")),
     *             @OA\Property(property="notes", type="string", description="Optional notes for the task", maxLength=500)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock Out Task created successfully."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to create Stock Out Task."
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'shipments'    => 'required|array|min:1',
            'shipments.*'  => 'string|distinct|exists:shipments,tracking_no',
            'notes'     => 'sometimes|string|max:500'
        ]);

        DB::beginTransaction();
        try {

            $task = StockOutTask::create([
                'created_by'  => Auth::id(),
                'status'      => 'pending',
                'notes'       => $data['notes'] ?? null
            ]);

            foreach ($data['shipments'] as $trackingNo) {
                $assign = AssignShipmentToShelf::where('tracking_no', $trackingNo)->first();
                if (!$assign) {
                    throw new Exception("Parcel {$trackingNo} is not assigned to any shelf.");
                }

                $shipment = StockOutTaskShipment::create([
                    'stock_out_task_id' => $task->id,
                    'shipment_tracking_no' => $trackingNo,
                    'shelf_barcode'     => $assign->barcode,
                    'status'            => 'pending'
                ]);


                $sortStatus = "STOCKOUT_TASK_CREATED";
                shipmentHistory([
                    "status"      => $sortStatus,
                    "description" => "Stockout task for this shipment has been created",
                    "shipment_id"    => $shipment->shipment->id,
                ]);
            }


            DB::commit();

            return sendResponse("Stock Out Task created successfully.", new StockOutTaskResource($task->load('shipments')), true, [], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse('Failed to create Stock Out Task.', [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/stockout_tasks/tasks",
     *     summary="Get a list of Stock Out Tasks by status",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter tasks by status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function tasks(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = StockOutTask::query();

        if (!is_null($status)) {
            $query->where('status', $status);
        }

        $tasks = $query->get();

        return sendResponse("Tasks", StockOutTaskResource::collection($tasks));
    }
}
