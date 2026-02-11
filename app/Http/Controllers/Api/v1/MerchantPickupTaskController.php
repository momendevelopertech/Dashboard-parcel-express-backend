<?php

namespace App\Http\Controllers\Api\v1;

use Exception;


use App\Models\User;
use App\Models\Merchant;
use App\Models\Shipment;
use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use App\Services\MerchantPickupWhatsappMessegingService;
use App\Models\MerchantPickupTask;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MerchantPickupShipment;
use Illuminate\Database\QueryException;
use App\Http\Resources\MerchantPickupTaskResource;
use App\Enums\MerchantPickupTaskStatusEnum;
use App\Enums\ShipmentStatusEnum;
use App\Models\MerchantCommission;
use App\Models\PickupRequest;
use App\Models\State;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\GuestMerchant;


class MerchantPickupTaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pickup_tasks",
     *     summary="Get pickup tasks with filters and pagination",
     *     tags={"Pickup Tasks"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pickup tasks retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index()
    {
        try {
            $perPage = request()->input('per_page', 8);
            $tasks = MerchantPickupTask::byOwner();
            $tasks = $tasks->with([
                'merchant',
                'driver',
                'shipments.shipment.consignee.country',
                'shipments.shipment.consignee.state',
                'shipments.shipment.consignee.governorate',
                'shipments.shipment.consignee.place',
                'shipments.merchant'
            ]);
            if (request()->has('query') && !empty(request()->input('query'))) {
                $query = request()->input('query');
                $tasks->whereRaw('LOWER(note) LIKE ?', ['%' . strtolower($query) . '%']);
            }

            // filter by merchant
            if (request()->has('merchant_id') && !empty(request()->input('merchant_id'))) {
                $query = request()->input('merchant_id');
                $tasks->where('merchant_id', request()->input('merchant_id'));
            }

            // filter by driver
            if (request()->has('driver_id') && !empty(request()->input('driver_id'))) {
                $query = request()->input('driver_id');
                $tasks->where('driver_id', request()->input('driver_id'));
            }

            if(facility()->id && facility()->type){
                $tasks->where("owner_id", facility()->id)
                    ->where("owner_type", facility()->type);
            }

            // Add Status filtering
            $status = request()->input('status', null);
            $validStatuses = [
                MerchantPickupTaskStatusEnum::TO_PICKUP,
                MerchantPickupTaskStatusEnum::PICKED,
                MerchantPickupTaskStatusEnum::PICKUP_COMPLETED,
                MerchantPickupTaskStatusEnum::CANCELLED
            ];
            if ($status && in_array($status, $validStatuses)) {
                $tasks->where('status', $status);
            }

            // Filter by pickup_ref
            if (request()->has('pickup_ref') && !empty(request()->input('pickup_ref'))) {
                $pickupRef = request()->input('pickup_ref');
                $tasks->where('ref', 'like', "%{$pickupRef}%");
            }

            $tasks = $tasks->orderBy('id', 'desc')
                ->paginate($perPage);
            $tasksArray = $tasks->toArray();
            $links = $tasksArray['links'];
            $formattedLinks = [];
            foreach ($links as $link) {
                $formattedLinks[] = [
                    'url' => $link['url'],
                    'label' => $link['label'],
                    'active' => $link['active'],
                ];
            }
            $response = [
                'data' => [
                    'data' => $tasksArray['data'],
                    'current_page' => $tasksArray['current_page'],
                    'from' => $tasksArray['from'],
                    'last_page' => $tasksArray['last_page'],
                    'per_page' => $tasksArray['per_page'],
                    'to' => $tasksArray['to'],
                    'total' => $tasksArray['total'],
                    'links' => $formattedLinks
                ],
                'success' => [
                    'count' => $tasksArray['total']
                ]
            ];
            return response()->json([
                'data' => $response,
                'message' => 'Merchant pickup tasks retrieved successfully.',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => null,
                'message' => 'Error retrieving pickup tasks.',
                'success' => false,
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Create a new pickup task and assign shipments to it.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'merchant_id' => 'required|exists:users,id',
            'driver_id' => 'required|exists:users,id',
            'no_of_shipments' => 'required|integer|min:1',
            'note' => 'nullable|string',
            'status' => 'sometimes|in:pending,completed,cancelled,to_pickup,pickup_completed,  picked',
            'pickup_request_id' => 'nullable|exists:pickup_requests,id',
        ]);

        DB::beginTransaction();
        try {
            $validated['status'] = $validated['status'] ?? MerchantPickupTaskStatusEnum::TO_PICKUP;
            $validated['owner_id'] = facility()->id;
            $validated['owner_type'] = facility()->type;
            $task = MerchantPickupTask::create($validated);
            $this->attachShipmentsToTask($task);
            activityLog('merchant pickup task created', "new merchant pickup task created for merchant with username : {$task->merchant->username}");
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('MerchantPickupTask store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return sendResponse(
                "An error occurred.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }


        $whatsappMessagingService = new MerchantPickupWhatsappMessegingService(new WhatsAppService());
        $whatsappMessagingService->pickup_task_whatsapp_notification($task);

        return sendResponse("Task Created Successfully.", new MerchantPickupTaskResource($task));
    }

    public function show($id)
    {
        $task = MerchantPickupTask::byOwner()
            ->with(['merchant', 'driver'])
            ->find($id);

        if (!$task) {
            return sendResponse("Error Occurred.", [], ["Task not found."], 404);
        }

        return sendResponse("Merchant pickup task retrieved successfully.", new MerchantPickupTaskResource($task));
    }

    public function update(Request $request)
    {
        $task = MerchantPickupTask::byOwner()->find($request->id);

        if (!$task) {
            return sendResponse("Error Occurred.", [], ["Task not found."], 404);
        }

        $validated = $request->validate([
            'merchant_id' => 'sometimes|exists:users,id',
            'driver_id' => 'sometimes|exists:users,id',
            'no_of_shipments' => 'sometimes|string',
            'note' => 'nullable|string',
            'status' => 'sometimes|in:pending,completed,cancelled',
        ]);

        $task->update($validated);
        activityLog('merchant pickup task updated', "merchant pickup task updated for merchant with username : {$task->merchant->username}");
        return sendResponse("Merchant pickup task updated successfully.", new MerchantPickupTaskResource($task));
    }

    public function delete(Request $request)
    {
        $task = MerchantPickupTask::find($request->id);

        if (!$task) {
            return sendResponse("Error Occurred.", [], ["Task not found."], 404);
        }

        try {
            $task->delete();
            activityLog('merchant pickup task deleted', "merchant pickup task deleted for merchant with username : {$task->merchant->username}");
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }

        return sendResponse("Merchant pickup task deleted successfully.", []);
    }

    public function getTasksByMerchant()
    {
        $merchantId = request('merchantId');

        if (!$merchantId) {
            return response()->json([
                'success' => false,
                'message' => 'Merchant ID is required',
            ], 400);
        }

        try {
            $tasks = MerchantPickupTask::where('merchant_id', $merchantId)->get();

            if ($tasks->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tasks found for the given merchant.',
                ], 404);
            }

            return sendResponse("Tasks fetched successfully.", MerchantPickupTaskResource::collection($tasks));
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching tasks',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function change_status(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:merchant_pickup_tasks,id',
            'status' => 'required|in:pending,completed,cancelled,picked',
        ]);
        try {
            $task = MerchantPickupTask::find($request->id);
            $status = $request->status == 'completed' ? 'pickup_completed' : $request->status;
            $task->update([
                'status' => $status
            ]);
            return sendResponse("Complaint status updated successfully.", new MerchantPickupTaskResource($task), true, []);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()]);
        }
    }

    public function all()
    {
        $tasks = MerchantPickupTask::byOwner()
            ->with(['merchant', 'driver'])
            ->get();
        return sendResponse("All merchant pickup tasks retrieved successfully.", MerchantPickupTaskResource::collection($tasks));
    }

    public function assign_task()
    {
        $tasks = MerchantPickupTask::byOwner()
            ->with(['merchant', 'driver'])
            ->get();
        return sendResponse("All merchant pickup tasks retrieved successfully.", MerchantPickupTaskResource::collection($tasks));
    }
    public function updatePickedShipments(Request $request, MerchantPickupTask $task)
    {
        $driverId = Auth::id();

        if ($task->driver_id !== $driverId) {
            return sendResponse(
                "You are not allowed to update this task.",
                [],
                false,
                [],
                403
            );
        }

        $data = $request->validate([
            'picked_shipments_no' => 'required|integer|min:0',
            'note' => 'nullable|string',
        ]);

        $totalShipmentsNo = (int) $task->no_of_shipments;
        $registeredShipmentsNo = (int) $task->shipments()->count();
        $pickedShipmentsNo = (int) $data['picked_shipments_no'];

        if ($pickedShipmentsNo > $totalShipmentsNo) {
            return sendResponse(
                "Picked shipments cannot be greater than total shipments.",
                [],
                false,
                [],
                422
            );
        }

        $task->update([
            'picked_shipments_no' => $pickedShipmentsNo,
            'note' => $data['note'] ?? $task->note,
        ]);

        // 👈 هنا هنجيب ref من جدول merchant_pickup_tasks
        $pickupRequestRef = $task->ref;

        return sendResponse("Pickup task updated successfully.", [
            'task_id' => $task->id,
            'registered_shipments_no' => $registeredShipmentsNo,
            'total_shipments_no' => $totalShipmentsNo,
            'picked_shipments_no' => $pickedShipmentsNo,

            'pickup_request_id' => $task->pickup_request_id,
            'ref' => $pickupRequestRef,
        ]);
    }

    public function createByDriver(Request $request): JsonResponse
    {
        $driver = Auth::user();

        if (!$driver) {
            return sendResponse("Unauthorized.", [], false, [], 401);
        }

        $validated = $request->validate([
            'no_of_shipments' => 'required|integer|min:1',
            'note' => 'nullable|string',

            'merchant_id' => 'nullable|exists:users,id',

            'merchant_name' => 'required_without:merchant_id|string',
            'merchant_phone' => 'required_without:merchant_id|string',

            'sender_country_id' => 'nullable|integer',
            'sender_governorate_id' => 'nullable|integer',
            'sender_state_id' => 'nullable|integer',
            'sender_place_id' => 'nullable|integer',
            'sender_city_id' => 'nullable|integer',
            'sender_streetAddress' => 'nullable|string',
            'sender_latitude' => 'nullable|numeric',
            'sender_longitude' => 'nullable|numeric',
            'sender_email' => 'nullable|email',
        ]);

        DB::beginTransaction();

        try {
            $passedMerchantId = $request->input('merchant_id');

            // 1) Registered merchant
            if ($passedMerchantId) {

                $merchantRow = Merchant::withoutGlobalScopes()->where('user_id', $passedMerchantId)->first();
                Log::info('Merchant EXISTS : ' . $merchantRow->user->name . ' MERCHANT !!');

                Log::info('--------------------------------------------------------');
                Log::info('merchant_id input: ' . $passedMerchantId);
                Log::info('merchant row: ' . optional($merchantRow)->toJson());
                Log::info('merchant name: ' . optional(optional($merchantRow)->user)->name);
                Log::info('--------------------------------------------------------');

                if (!$merchantRow) {
                    DB::rollBack();
                    return sendResponse("Invalid merchant ID.", [], false, [
                        "merchant_id ({$passedMerchantId}) not found in merchants table"
                    ], 422);
                }

                // Merchant user_id
                $merchantUserId = $merchantRow->user_id;

                // Update merchant location fields
                $merchantUpdateData = [
                    'country_id' => $request->sender_country_id ?? $merchantRow->country_id,
                    'governorate_id' => $request->sender_governorate_id ?? $merchantRow->governorate_id,
                    'state_id' => $request->sender_state_id ?? $merchantRow->state_id,
                    'place_id' => $request->sender_place_id ?? $merchantRow->place_id,
                    'city_id' => $request->sender_city_id ?? $merchantRow->city_id,
                    'address' => $request->sender_streetAddress ?? $merchantRow->address,
                    'lat' => $request->sender_latitude ?? $merchantRow->lat,
                    'lng' => $request->sender_longitude ?? $merchantRow->lng,
                ];

                if (collect($merchantUpdateData)->diffAssoc($merchantRow->only(array_keys($merchantUpdateData)))->isNotEmpty()) {
                    $merchantRow->update($merchantUpdateData);
                }
            } else {
                Log::info('Merchant doesnt exist : GUEST MERCHANT !!');

                $phoneSplit = splitPhoneNumber($request->merchant_phone);
                $merchantEmail = $request->sender_email ?? strtolower(preg_replace('/\s+/', '_', $request->merchant_name)) . '@guest.local';
                $countryCode = $phoneSplit['country_code'] ?? null;
                $phoneNumber = $phoneSplit['national_number'] ?? null;

                // A) Check if a user already exists WITH a merchant
                $user = User::whereHas('merchant')
                    ->where(function ($q) use ($merchantEmail, $countryCode, $phoneNumber) {
                        $q->where('email', $merchantEmail)
                            ->orWhere(function ($q) use ($countryCode, $phoneNumber) {
                                $q->where('country_code', $countryCode)
                                    ->where('phone', $phoneNumber);
                            });
                    })
                    ->first();

                // If user exists → merchant already exists

                // ❌ Merchant already exists
                if ($user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Merchant already exists with this email or phone number.',
                        'errors' => [
                            'merchant' => ['Merchant already exists.']
                        ]
                    ], 422);
                }

                // B) User doesn't exist, create a new guest user
                $user = User::create([
                    'name' => $request->merchant_name,
                    'email' => $merchantEmail,
                    'country_code' => $countryCode,
                    'phone' => $phoneNumber,
                    'password' => bcrypt(uniqid()), // Random password for guest users
                    'role' => 'merchant',
                    'is_guest' => true,
                ]);

                // C) Create the merchant and link to the newly created user
                $merchant = Merchant::create([
                    'country_code' => $countryCode,
                    'contact_no' => $phoneNumber,
                    'country_id' => $request->sender_country_id,
                    'governorate_id' => $request->sender_governorate_id,
                    'state_id' => $request->sender_state_id,
                    'place_id' => $request->sender_place_id,
                    'address' => $request->sender_streetAddress ?? null,
                    'lat' => $request->sender_latitude,
                    'lng' => $request->sender_longitude,
                    'user_id' => $user->id,
                    'is_guest' => true,
                ]);

                $merchantUserId = $user->id;
            }


            $task = MerchantPickupTask::create([
                'merchant_id' => $merchantUserId,
                'driver_id' => $driver->id,
                'no_of_shipments' => $validated['no_of_shipments'],
                'note' => $validated['note'] ?? null,
                'status' => MerchantPickupTaskStatusEnum::TO_PICKUP,
                'pickup_request_id' => $validated['pickup_request_id'] ?? null,
            ]);

            $this->attachShipmentsToTask($task);

            DB::commit();

            $task->load(['merchant', 'driver']);

            $whatsappMessagingService = new MerchantPickupWhatsappMessegingService(new WhatsAppService());
            $whatsappMessagingService->pickup_task_whatsapp_notification($task);

            return sendResponse("Pickup task created successfully.", [
                'task' => new MerchantPickupTaskResource($task),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while creating pickup task.", [], false, [$e->getMessage()], 500);
        }
    }

    protected function attachShipmentsToTask(MerchantPickupTask $task): void
    {
        $shipments = Shipment::moveCreatedShipmentsToPickupForMerchant($task->merchant_id);

        foreach ($shipments as $shipment) {



            MerchantPickupShipment::updateOrCreate(
                [
                    "pickup_task_id" => $task->id,
                    "shipment_id" => $shipment->id,
                ],
                [
                    "merchant_id" => $task->merchant_id,
                    "driver_id" => $task->driver_id,
                    "shipment_tracking_no" => $shipment->tracking_no,
                    "pre_id" => $shipment->pre_id,
                    "status" => MerchantPickupTaskStatusEnum::TO_PICKUP,
                ]
            );
        }
    }


    public function updateCachedShipmentStepper(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pickup_task_id' => 'required|exists:merchant_pickup_tasks,id',
            'count' => 'required|integer|min:0',
        ]);

        try {
            $task = MerchantPickupTask::findOrFail($validated['pickup_task_id']);

            $task->update([
                'cached_shipment_step' => $validated['count'],
            ]);

            $task->refresh();

            return response()->json([
                'no_of_shipments' => $task->no_of_shipments,
                'picked_shipments_no' => $task->picked_shipments_no,
                'extra_shipments_no' => $task->extra_shipments_no,
                'cached_shipment_step' => $task->cached_shipment_step,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating cached shipment step.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Merchant Pickup Tasks
     *
     * @OA\Get(
     *     path="/merchant/pickup-tasks",
     *     summary="Get pickup tasks for authenticated merchant",
     *     description="
     * Retrieve paginated list of pickup tasks for the authenticated merchant with filtering capabilities.
     *
     * **Features:**
     * - Pagination support
     * - Status filtering
     * - Date range filtering
     * - Driver information included
     *
     * **Security:**
     * - Merchant authentication required
     * - Only returns tasks for authenticated merchant
     * ",
     *     operationId="getMerchantPickupTasks",
     *     tags={"Merchant Portal"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by task status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "in_progress", "completed", "cancelled", "to_pickup", "picked", "pickup_completed"})
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Filter tasks from date (created_at)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-01")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="Filter tasks to date (created_at)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-08")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pickup tasks retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pickup tasks retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function indexForMerchant(Request $request)
    {
        try {
            $merchantId = Auth::id();
            $perPage = (int) $request->input('per_page', 15);

            // Build query with eager loading
            $query = MerchantPickupTask::query()
                ->with([
                    'driver:id,name,phone,country_code',
                    'merchant:id,name,email'
                ])
                ->withCount('shipments')
                ->where('merchant_id', $merchantId)
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->filled('status')) {
                $status = $request->input('status');
                $query->where('status', $status);
            }

            // Filter by date range (from)
            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from)->startOfDay());
            }

            // Filter by date range (to)
            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to)->endOfDay());
            }

            // Filter by pickup_ref
            if ($request->filled('pickup_ref')) {
                $status = $request->input('pickup_ref');
                $query->where('ref', 'like', "%{$status}%");
            }

            // Paginate results
            $tasks = $query->paginate($perPage);

            return sendResponse(
                "Pickup tasks retrieved successfully.",
                MerchantPickupTaskResource::collection($tasks)->response()->getData(true)
            );
        } catch (\Exception $e) {
            return sendResponse(
                "An error occurred while fetching pickup tasks.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }
}
