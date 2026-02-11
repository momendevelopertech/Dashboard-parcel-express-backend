<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreTransferTaskRequest;
use App\Http\Requests\UpdateTransferTaskRequest;
use App\Http\Resources\TransferAreaResource;
use App\Http\Resources\TransferShipmentResource;
use App\Http\Resources\TransferTaskResource;
use App\Models\TransferDestination;
use App\Models\TransferShipment;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\TransferTask;
use App\Models\TransferTaskShipment;
use App\Models\Truck;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Other", description="Transfer Task Management")
 * @OA\Server(url="api/")
 */
class TransferTaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/transfer_tasks",
     *     summary="Get all transfer tasks",
     *     description="Retrieves a list of transfer tasks. Allows querying by truck number plate, color, driver company, driver phone number, origin name, status, or notes.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Query string for filtering",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="TransferTasks retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $transfer_tasks = TransferTask::byOwner();
        $perPage = request()->input('per_page', 8);
        if (request()->has('query')) {
            $query = request()->input('query');
            $transfer_tasks = $transfer_tasks
                ->with(['truck', 'truck_driver.user', 'origin'])
                ->where(function ($q) use ($query) {
                    $q->whereHas('truck', function ($truck) use ($query) {
                        $truck->whereRaw('LOWER(number_plate) LIKE ?', ['%' . strtolower($query) . '%'])
                            ->orWhereRaw('LOWER(color) LIKE ?', ['%' . strtolower($query) . '%']);
                    })
                        ->orWhereHas('truck_driver', function ($driver) use ($query) {
                            $driver->whereRaw('LOWER(company) LIKE ?', ['%' . strtolower($query) . '%'])
                                ->orWhereRaw('LOWER(phone_number) LIKE ?', ['%' . strtolower($query) . '%']);
                        })
                        ->orWhereHas('origin', function ($origin) use ($query) {
                            $origin->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
                        })
                        ->orWhere('status', 'LIKE', '%' . $query . '%')
                        ->orWhere('notes', 'LIKE', '%' . $query . '%');
                })
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $transfer_tasks = $transfer_tasks->with(['truck', 'truck_driver.user', 'origin'])->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("TransferTasks reterived successfully.", new TransferTaskResource($transfer_tasks), []);
    }

    public function getPendingTransferShipments(Request $request)
    {
        $perPage = $request->input('per_page', 8);
        $transferShipmentsQuery = TransferShipment::byOwner();
        $transferShipmentsQuery->pendingUnassigned();

        $transferShipmentsQuery->with([
            'shipment:id,tracking_no,status,consignee_id',
            'shipment.consignee:id,name,cellphone',
            'ownership',
            'owner', 
        ]);

        $transferShipments = $transferShipmentsQuery->orderByDesc('id')->paginate($perPage);

        if ($transferShipments->isEmpty() && $request->input('page', 1) == 1) {
            return sendResponse("No pending transfer shipments found matching criteria.", [], false, ['no record']);
        }
        $resourceCollection = TransferShipmentResource::collection($transferShipments);
        $paginatedData = $resourceCollection->toResponse($request)->getData(true);

        $linksForFrontend = $paginatedData['meta']['links'] ?? [];
        $responseData = [
            'data' => $paginatedData['data'],
            'links' => $linksForFrontend, 
            'meta' => $paginatedData['meta'] ?? [],
        ];

        return sendResponse(
            "Pending transfer shipments retrieved successfully.",
            $responseData, 
            []
        );
    }
    /**
     * @OA\Post(
     *     path="/transfer_tasks/store",
     *     summary="Create a new transfer task",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="truck_id", type="integer", description="ID of the truck (required, exists:trucks,id)", example=1),
     *             @OA\Property(property="truck_driver_id", type="integer", description="ID of the truck driver (required, exists:truck_drivers,id)", example=1),
     *             @OA\Property(property="destinations", type="array", description="Array of destinations (required)",
     *                 @OA\Items(
     *                     @OA\Property(property="owner_id", type="integer", example=1),
     *                     @OA\Property(property="owner_type", type="string", example="App\\Models\\Branch")
     *                 )
     *             ),
     *             @OA\Property(property="notes", type="string", description="Notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="TransferTask created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or Error Occurred."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error. You do not belongs to any facility you cannot create transfers."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreTransferTaskRequest $request)
    {
        $request->validated();
        $origin_id = facility("id");
        $origin_type = facility("type");

        if ($origin_id == null || $origin_type == null) {
            return sendResponse("", [], false, ["You do not belongs to any facility you cannot create transfers."], 500);
        }

        DB::beginTransaction();
        try {
            $truck = Truck::find($request->truck_id);
            $task = TransferTask::create([
                'truck_driver_id' => $request->truck_driver_id,
                'truck_id' => $truck->id,
                'origin_id' => $origin_id,
                'origin_type' => $origin_type,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            $destinations = json_decode($request->destinations);
            foreach ($destinations as $destination) {
                $transfer_destination = TransferDestination::create([
                    'transfer_task_id' => $task->id,
                    'origin_id' => Auth::user()->owner->id ?? null,
                    'origin_type' => Auth::user()->owner ? get_class(Auth::user()->owner) : null,
                    'destination_id' => $destination->owner_id,
                    'destination_type' => $destination->owner_type,
                ]);

                // Fix: Use byOwner() scope to get only transfer shipments owned by current facility
                // and filter by destination and status, and exclude already assigned shipments
                $transfer_shipments = TransferShipment::byOwner()
                    ->pendingUnassigned()
                    ->where('owner_id', $destination->owner_id)
                    ->where('owner_type', $destination->owner_type)
                    ->get();

                foreach ($transfer_shipments as $transfer_shipment) {
                    TransferTaskShipment::create([
                        'transfer_shipment_id' => $transfer_shipment->id,
                        'transfer_task_id' => $task->id,
                        'transfer_destination_id' => $transfer_destination->id,
                        'shipment_tracking_no' => $transfer_shipment->shipment_tracking_no,
                        'truck_barcode' => $truck->barcode,
                    ]);

                    $transfer_shipment->update([
                        'status' => 'assigned',
                    ]);
                }
            }

            // Notify Warehouse Supervisors at the origin facility
            $users = collect();
            \Log::info('Transfer Task Created - Looking for Warehouse Supervisors', [
                'origin_type' => $origin_type,
                'origin_id' => $origin_id,
                'task_id' => $task->id
            ]);

            if ($origin_type === \App\Models\Branch::class) {
                $users = \App\Models\User::whereHas('branch_user', function ($q) use ($origin_id) {
                    $q->where('branch_id', $origin_id);
                })->role('Warehouse Supervisor')->get();
            } elseif ($origin_type === \App\Models\Station::class) {
                $users = \App\Models\User::whereHas('station_user', function ($q) use ($origin_id) {
                    $q->where('station_id', $origin_id);
                })->role('Warehouse Supervisor')->get();
            } elseif ($origin_type === \App\Models\Hub::class) {
                $users = \App\Models\User::whereHas('hub_user', function ($q) use ($origin_id) {
                    $q->where('hub_id', $origin_id);
                })->role('Warehouse Supervisor')->get();
            }

            \Log::info('Warehouse Supervisors Found', [
                'count' => $users->count(),
                'user_ids' => $users->pluck('id')->toArray()
            ]);

            if ($users->count() > 0) {
                \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\TransferTaskCreatedNotification($task));
                \Log::info('Notification sent to Warehouse Supervisors', [
                    'task_id' => $task->id,
                    'recipients' => $users->count()
                ]);
            } else {
                \Log::warning('No Warehouse Supervisors found for origin facility', [
                    'origin_type' => $origin_type,
                    'origin_id' => $origin_id
                ]);
            }


            $responseData = [
                'task' => $task,
                'truck_barcode' => $truck->barcode ?? null,
                'transfer_destination_id' => $transfer_destination->id ?? null
            ];
            DB::commit();
            return sendResponse("TransferTask created successfully.", $responseData);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error Occured.", $e->getMessage(), false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/transfer_tasks/edit/{id}",
     *     summary="Get a transfer task by ID",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the transfer task",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function edit($id)
    {
        $task = TransferTask::with(
            'truck',
            'truck_driver.user',
            'destinations.origin',
            'destinations.destination',
            'destinations.destinationShipments',
            'destinations.destinationShipments.shipment.consignee',
            'destinations.destinationShipments.shipment.consignee.country:id,name',
            'destinations.destinationShipments.shipment.consignee.governorate:id,en_name,ar_name',
            'destinations.destinationShipments.shipment.consignee.state:id,en_name,ar_name',
            'destinations.destinationShipments.shipment.consignee.place:id,en_name,ar_name',
            'origin'
        )->find($id);
        return sendResponse("Role", new TransferTaskResource($task));
    }

    /**
     * @OA\Post(
     *     path="/transfer_tasks/update",
     *     summary="Update a transfer task",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the transfer task to update (required)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="TransferTask updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or Error Occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateTransferTaskRequest $request)
    {
        $request->validated();
        try {
            $transfer_task = TransferTask::find($request->id);
            $transfer_task->update($request->all());
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("TransferTask updated successfully.", new TransferTaskResource($transfer_task));
    }

    /**
     * @OA\Post(
     *     path="/transfer_tasks/delete",
     *     summary="Delete a transfer task",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the transfer task to delete (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="TransferTask deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or Error Occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            TransferTask::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("TransferTask deleted successfully.", []);
    }


    /**
     * @OA\Get(
     *     path="/transfer_tasks/areas",
     *     summary="Get transfer shipment areas",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Zone"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function areas()
    {
        $shipments = TransferShipment::byOwner()
            ->pendingUnassigned()
            ->select('owner_id', 'owner_type', DB::raw('COUNT(*) as shipments_count'))
            ->groupBy('owner_id', 'owner_type')
            ->get();
        $shipments->load('owner');

        $result = $shipments->map(function ($shipment) {
            return [
                'owner' => $shipment->owner ? $shipment->owner->name : null,
                'shipments_count' => $shipment->shipments_count,
            ];
        });

        return sendResponse("Zone", $result);
    }

    /**
     * @OA\Get(
     *     path="/transfer_tasks/by_status/{status}",
     *     summary="Get transfer tasks by status",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the transfer tasks",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function transfer_tasks_by_status($status)
    {
        $tasks = TransferTask::where('status', $status)->with('truck', 'truck_driver.user')->get();
        return sendResponse("Tasks", $tasks);
    }

    /**
     * @OA\Get(
     *     path="/transfer_tasks/destinations/{transfer_task_id}/{status}",
     *     summary="Get transfer task destinations by status",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="transfer_task_id",
     *         in="path",
     *         description="ID of the transfer task",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the transfer destinations",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tasks"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function transfer_tasks_destinations($transfer_task_id, $status)
    {
        $tasks = TransferDestination::where('transfer_task_id', $transfer_task_id)
            ->where('status', $status)
            ->whereHas('destinationShipments', function ($query) {
                $query->where('status', 'pending');
            })
            ->with(['destination', 'destinationShipments'])
            ->withCount([
                'destinationShipments as pending_shipments_count' => function ($query) {
                    $query->where('status', 'pending');
                },
            ])
            ->get();

        return sendResponse("Tasks", $tasks);
    }

    /**
     * @OA\Post(
     *     path="/transfer_tasks/add_destination",
     *     summary="Add a destination to a transfer task",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="transfer_task_id", type="integer", description="ID of the transfer task (required, exists:transfer_tasks,id)"),
     *             @OA\Property(property="owner_id", type="integer", description="ID of the destination owner (required)"),
     *             @OA\Property(property="owner_type", type="string", description="Type of the destination owner (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Destination added successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or Destination already exists."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function add_destination(Request $request)
    {
        $request->validate([
            'transfer_task_id' => 'required|exists:transfer_tasks,id',
        ]);

        if (TransferDestination::where('transfer_task_id', $request->transfer_task_id)->where('destination_id', $request->owner_id)->where('destination_type', $request->owner_type)->exists()) {
            return sendResponse(
                "Destination already exists.",
                [],
                false,
                ["A destination for this transfer task already exists."],
                422
            );
        }

        DB::beginTransaction();
        try {
            $destination = TransferDestination::create([
                'transfer_task_id' => $request->transfer_task_id,
                'origin_id' => Auth::user()->owner->id ?? null,
                'origin_type' => Auth::user()->owner ? get_class(Auth::user()->owner) : null,
                'destination_id' => $request->owner_id,
                'destination_type' => $request->owner_type,
            ]);

            // Fix: Use byOwner() scope to get only transfer shipments owned by current facility
            // and filter by destination and status, and exclude already assigned shipments
            $transfer_shipments = TransferShipment::byOwner()
                ->pendingUnassigned()
                ->where('owner_id', $request->owner_id)
                ->where('owner_type', $request->owner_type)
                ->get();

            foreach ($transfer_shipments as $transfer_shipment) {
                TransferTaskShipment::create([
                    'transfer_shipment_id' => $transfer_shipment->id,
                    'transfer_task_id' => $request->transfer_task_id,
                    'transfer_destination_id' => $destination->id,
                    'shipment_tracking_no' => $transfer_shipment->shipment_tracking_no,
                ]);
            }

            DB::commit();
            return sendResponse("Destination added successfully.", []);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/transfer_tasks/incoming_trucks",
     *     summary="Get incoming trucks with parcels",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Incoming trucks with parcels"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or You do not belong to any facility."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function incoming_trucks()
    {
        $owner = Auth::user()->owner;
        if (!$owner) {
            return sendResponse("Error", [], false, ["You do not belong to any facility."], 422);
        }

        $destinations = TransferDestination::where('destination_id', $owner->id)
            ->with(['task.truck', 'task.truck_driver', 'destinationShipments'])
            ->withCount([
                'destinationShipments as shipments_count' => function ($query) {
                    $query->where('status', 'loaded');
                }
            ])
            ->get();

        $data = $destinations->filter(function ($destination) {
            return $destination->shipments_count > 0;
        })->map(function ($destination) {
            return [
                'transfer_task_id' => $destination->transfer_task_id,
                'truck' => $destination->task->truck,
                'truck_driver' => $destination->task->truck_driver,
                'shipments_count' => $destination->shipments_count,
                'shipments' => $destination->destinationShipments,
            ];
        });

        return sendResponse("Incoming trucks with parcels", $data);
    }
    public function transfer_areas()
    {
        $shipments = TransferShipment::byOwner()
            ->select('ownership_id', 'ownership_type', 'owner_id', 'owner_type', DB::raw('COUNT(*) as shipments_count'))
            ->groupBy('ownership_id', 'ownership_type', 'owner_id', 'owner_type')
            ->get();

        $shipments->load('owner', 'ownership');

        return response()->json([
            'message' => 'Transfer Areas with shipments data',
            'data' => TransferAreaResource::collection($shipments),
        ]);
    }
}
