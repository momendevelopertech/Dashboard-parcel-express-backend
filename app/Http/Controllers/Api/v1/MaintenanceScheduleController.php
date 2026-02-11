<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\MaintenanceScheduleResource;
use App\Models\MaintenanceSchedule;
use App\Models\Truck;
use App\Traits\Searchable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Other", description="Maintenance Schedule Management")
 * @OA\Controller(description="API endpoints for managing maintenance schedules.")
 */
class MaintenanceScheduleController extends Controller
{
    use Searchable;
    protected function modelQuery()
    {
        return MaintenanceSchedule::query()->select('id','truck_id','maintenance_type','scheduled_at','status','notes','created_at','updated_at');
    }
    /**
     * @OA\Get(
     *     path="/maintenance-schedules",
     *     summary="Get a list of maintenance schedules",
     *     description="Retrieves a list of maintenance schedules with pagination.  Supports filtering by truck_id, maintenance_type, status, date range, and a general query.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="truck_id",
     *         in="query",
     *         description="Filter by truck ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="maintenance_type",
     *         in="query",
     *         description="Filter by maintenance type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter by start date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter by end date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for maintenance type, status, or notes",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Maintenance schedules retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors occurred",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index(Request $request)
    {
        $this->updateOverdueSchedules();
        $query = MaintenanceSchedule::with('truck:id,barcode,number_plate,company');
        if ($request->filled('truck_id')) {
            $query->where('truck_id', $request->input('truck_id'));
        }
        if ($request->filled('maintenance_type')) {
            $query->where('maintenance_type', $request->input('maintenance_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = Carbon::parse($request->input('date_from'))->startOfDay();
            $to = Carbon::parse($request->input('date_to'))->endOfDay();
            $query->whereBetween('scheduled_at', [$from, $to]);
        }
        if ($request->has('query')) {
            $searchTerm = $request->input('query');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('maintenance_type', 'like', "%{$searchTerm}%")
                    ->orWhere('status', 'like', "%{$searchTerm}%")
                    ->orWhere('notes', 'like', "%{$searchTerm}%");
            });
            $schedules = $query->orderBy('scheduled_at', 'asc')->get();
        } else {
            $perPage = $request->input('per_page', 15);
            $schedules = $query->orderBy('scheduled_at', 'asc')->paginate($perPage);
        }

        return sendResponse("Maintenance schedules retrieved successfully.", new MaintenanceScheduleResource($schedules), []);
    }
    private function updateOverdueSchedules()
    {
        MaintenanceSchedule::where('status', 'pending')
            ->where('scheduled_at', '<', now())
            ->update(['status' => 'overdue']);
    }
    /**
     * @OA\Post(
     *     path="/maintenance-schedules/store",
     *     summary="Create a new maintenance schedule",
     *     description="Creates a new maintenance schedule.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="truck_id", type="integer", description="ID of the truck"),
     *             @OA\Property(property="maintenance_type", type="string", description="Type of maintenance"),
     *             @OA\Property(property="scheduled_at", type="string", format="date", description="Scheduled date and time"),
     *             @OA\Property(property="status", type="string", description="Status of the schedule"),
     *             @OA\Property(property="notes", type="string", description="Notes for the schedule")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Maintenance schedule created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors occurred or error occurred",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(Request $request)
    {
        $rules = [
            'truck_id' => 'required|exists:trucks,id',
            'maintenance_type' => 'required|in:oil_change,tire_replacement,brake_service,general_inspection,filter_change,others',
            'scheduled_at' => 'required|date|after_or_equal:now',
            'status' => 'sometimes|in:pending,completed,overdue',
            'notes' => 'sometimes|string|max:500'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return sendResponse("Validation errors occurred.", [], [], $validator->errors(), 422);
        }
        try {
            $schedule = MaintenanceSchedule::create([
                'truck_id' => $request->input('truck_id'),
                'maintenance_type' => $request->input('maintenance_type'),
                'scheduled_at' => Carbon::parse($request->input('scheduled_at')),
                'status' => $request->input('status', 'pending'),
                'notes' => $request->input('notes', null)
            ]);
            $schedule->load('truck:id,barcode,number_plate,company');
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [], [$e->getMessage()], 422);
        }
        return sendResponse("Maintenance schedule created successfully.", new MaintenanceScheduleResource($schedule));
    }
    /**
     * @OA\Post(
     *     path="/maintenance-schedules/update",
     *     summary="Update a maintenance schedule",
     *     description="Updates an existing maintenance schedule.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the maintenance schedule"),
     *             @OA\Property(property="truck_id", type="integer", description="ID of the truck"),
     *             @OA\Property(property="maintenance_type", type="string", description="Type of maintenance"),
     *             @OA\Property(property="scheduled_at", type="string", format="date", description="Scheduled date and time"),
     *             @OA\Property(property="status", type="string", description="Status of the schedule"),
     *             @OA\Property(property="notes", type="string", description="Notes for the schedule")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Maintenance schedule updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors occurred or error occurred",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(Request $request)
    {
        $rules = ['id' => 'required|exists:maintenance_schedules,id','truck_id' => 'sometimes|required|exists:trucks,id','maintenance_type' => 'sometimes|required|in:oil_change,tire_replacement,brake_service,general_inspection,filter_change,others','scheduled_at' => 'sometimes|required|date','status' => 'sometimes|required|in:pending,completed,overdue','notes' => 'sometimes|string|max:500'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return sendResponse("Validation errors occurred.", [], $validator->errors(), 422);
        }
        try {
            $schedule = MaintenanceSchedule::find($request->id);
            $schedule->update($request->all());
            $schedule->load('truck:id,barcode,number_plate,company');
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [], [$e->getMessage()], 422);
        }
        return sendResponse("Maintenance schedule updated successfully.", new MaintenanceScheduleResource($schedule));
    }
    /**
     * @OA\Post(
     *     path="/maintenance-schedules/delete",
     *     summary="Delete a maintenance schedule",
     *     description="Deletes a maintenance schedule.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the maintenance schedule")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Maintenance schedule deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function delete(Request $request)
    {
        try {
            MaintenanceSchedule::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [], [$e->getMessage()], 422);
        }
        return sendResponse("Maintenance schedule deleted successfully.", []);
    }
    /**
     * @OA\Get(
     *     path="/maintenance-schedules/edit/{id}",
     *     summary="Get a maintenance schedule by ID",
     *     description="Retrieves a single maintenance schedule by its ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the maintenance schedule",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Maintenance schedule retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Maintenance schedule not found",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function edit($id)
    {
        $schedule = MaintenanceSchedule::with('truck:id,barcode,number_plate,company')->find($id);
        return sendResponse("Maintenance schedule", $schedule);
    }
    /**
     * @OA\Get(
     *     path="/maintenance-schedules/all",
     *     summary="Get all maintenance schedules",
     *     description="Retrieves all maintenance schedules.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Maintenance schedules retrieved successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function all()
    {
        $schedules = MaintenanceSchedule::with('truck:id,barcode,number_plate,company')->get();
        return sendResponse("Maintenance schedules", new MaintenanceScheduleResource($schedules));
    }
    /**
     * @OA\Post(
     *     path="/maintenance-schedules/mark-complete",
     *     summary="Mark a maintenance schedule as complete",
     *     description="Marks a maintenance schedule as completed.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the maintenance schedule"),
     *             @OA\Property(property="notes", type="string", description="Notes for the schedule")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Maintenance schedule marked as completed",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors occurred or error occurred",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function markComplete(Request $request)
    {
        $rules = ['id' => 'required|exists:maintenance_schedules,id','notes' => 'sometimes|string|max:500'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return sendResponse("Validation errors occurred.", [], $validator->errors(), 422);
        }
        try {
            $schedule = MaintenanceSchedule::find($request->id);
            $schedule->status = 'completed';
            if ($request->filled('notes')) {
                $schedule->notes = $request->input('notes');
            }
            $schedule->save();
            $schedule->load('truck:id,barcode,number_plate,company');
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [], [$e->getMessage()], 422);
        }
        return sendResponse("Maintenance schedule marked as completed.", new MaintenanceScheduleResource($schedule));
    }
    /**
     * @OA\Get(
     *     path="/maintenance-schedules/trucks",
     *     summary="Get a list of trucks",
     *     description="Retrieves a list of trucks.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Trucks retrieved successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function getTrucks()
    {
        $trucks = Truck::select('id', 'barcode', 'number_plate', 'company')->get();
        return sendResponse("Trucks retrieved successfully.", $trucks);
    }
    /**
     * @OA\Post(
     *     path="/maintenance-schedules/export",
     *     summary="Export maintenance schedules",
     *     description="Exports maintenance schedules. Supports filtering by truck_id, maintenance_type, status, date range, and a general query.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="truck_id",
     *         in="query",
     *         description="Filter by truck ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="maintenance_type",
     *         in="query",
     *         description="Filter by maintenance type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter by start date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter by end date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for maintenance type, status, or notes",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Maintenance schedules exported successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function export(Request $request)
    {
        $query = MaintenanceSchedule::with('truck:id,barcode,number_plate,company');
        if ($request->filled('truck_id')) {
            $query->where('truck_id', $request->input('truck_id'));
        }
        if ($request->filled('maintenance_type')) {
            $query->where('maintenance_type', $request->input('maintenance_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = Carbon::parse($request->input('date_from'))->startOfDay();
            $to = Carbon::parse($request->input('date_to'))->endOfDay();
            $query->whereBetween('scheduled_at', [$from, $to]);
        }
        if ($request->has('query')) {
            $searchTerm = $request->input('query');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('maintenance_type', 'like', "%{$searchTerm}%")
                    ->orWhere('status', 'like', "%{$searchTerm}%")
                    ->orWhere('notes', 'like', "%{$searchTerm}%");
            });
        }
        $schedules = $query->orderBy('scheduled_at', 'asc')->get();
        return sendResponse("Maintenance schedules exported successfully.", new MaintenanceScheduleResource($schedules));
    }
}
