<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\ShipmentStatusEnum;
use App\Enums\PickupRequestStatusEnum;
use App\Enums\MerchantPickupTaskStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMerchantPickupShipmentRequest;
use App\Http\Resources\MerchantPickupShipmentResource;
use App\Models\Branch;
use App\Models\Merchant;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantPickupTask;
use App\Models\PickupRequest;
use App\Models\PickupMissedShipmentsTransaction;
use App\Models\Hub;
use App\Models\Shipment;
use App\Models\Station;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Services\MerchantPickupWhatsappMessegingService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\MerchantPickupTaskService;
use App\Services\AdminCounterService;
use Illuminate\Support\Facades\Log;
/**
 * Controller handling merchant pickup shipment management and driver assignments
 *
 * Manages pickup task creation, shipment grouping, and delivery preparation. Features:
 * - Bulk shipment assignment to drivers
 * - Multi-level ownership scoping (branch/station/hub)
 * - Real-time shipment status tracking
 * - Transactional task creation
 * - Permanent activity audit trail
 *
 * Authorization: Authenticated user with pickup management permissions
 * Error handling: Database transactions with rollback on failure
 */
class MerchantPickupShipmentController extends Controller
{
         public $adminCounterService;
    public function __construct(AdminCounterService $adminCounterService)
    {
        $this->adminCounterService = $adminCounterService;
    }
    /**
     * Assign driver to existing pickup task with all merchant shipments
     *
     * @param StoreMerchantPickupShipmentRequest $request Contains merchant_id, driver_id, optional note
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Empty success response
     *   - 404 Not Found: No existing task for merchant
     *   - 422 Unprocessable: Validation errors
     *   - 500 Server Error: Transaction failure
     *
     * Business logic:
     *   - Uses existing MerchantPickupTask with status 'created' for the merchant
     *   - Assigns driver to both the task and all related pickup shipments
     *   - Updates task status to 'assigned'
     *   - Updates all shipment statuses to "TO_PICKUP"
     *   - Creates shipment history entries for audit trail
     *
     * Security:
     *   - Validates task ownership and status
     *   - Ensures shipments belong to the same merchant
     */
    public function store(StoreMerchantPickupShipmentRequest $request)
    {
        $request->validated();
        DB::beginTransaction();
        try {
            if ($request->filled('pickup_request_id')) {
                $pickupRequest = PickupRequest::lockForUpdate()->find($request->pickup_request_id);

                if (!$pickupRequest) {
                    DB::rollBack();
                    return sendResponse("Pickup request not found.", [], false, ["Invalid pickup request"], 404);
                }

                $pickupRequest->update([
                    'counted_by' => $request->driver_id,
                    'status' => PickupRequestStatusEnum::ASSIGNED,
                ]);

                activityLog("pickup_request_assigned", "Pickup request #{$pickupRequest->id} assigned to driver " . User::find($request->driver_id)->name);

                DB::commit();

                // Note: WhatsApp notification requires a task, so skip if no task is created

                return sendResponse("Pickup request assigned to driver successfully.", [
                    'pickup_request_id' => $pickupRequest->id,
                    'driver_id' => $request->driver_id,
                    'status' => $pickupRequest->status,
                ]);
            }

            // Always create a new task and its MerchantPickupShipment records for this merchant and their created shipments
            $service = new MerchantPickupTaskService();
            $shipments = \App\Models\Shipment::where('merchant_id', $request->merchant_id)
                ->where('status', ShipmentStatusEnum::CREATED)
                // ->where(function($q) {
                //     $q->whereNull('created_source')
                //       ->orWhere('created_source', '!=', 'dashboard');
                // })
                ->get();
            // Removed error on empty: allow task with zero shipments
            $data = [
                'merchant_id' => $request->merchant_id,
                'driver_id' => $request->driver_id,
                'no_of_shipments' => $shipments->count(),
                'note' => $request->note ?? null,
                'status' => MerchantPickupTaskStatusEnum::TO_PICKUP,
            ];
            $task = $service->createTaskAndShipments($data, [], MerchantPickupTaskStatusEnum::TO_PICKUP);

            // Fetch just-created pickup shipments
            $pickupShipments = \App\Models\MerchantPickupShipment::where('pickup_task_id', $task->id)
                ->where('status', MerchantPickupTaskStatusEnum::TO_PICKUP)
                ->get();

            // Now assign the driver to the task and pickup shipments
            $task->update([
                'driver_id' => $request->driver_id,
                'status' => MerchantPickupTaskStatusEnum::TO_PICKUP,
                'note' => $request->note ?? $task->note,
                'no_of_shipments' => $pickupShipments->count(),
            ]);

            foreach ($pickupShipments as $pickupShipment) {
                $pickupShipment->update([
                    'driver_id' => $request->driver_id,
                    'status' => MerchantPickupTaskStatusEnum::TO_PICKUP
                ]);

                // Update the related shipment status/history
                $shipment = \App\Models\Shipment::where('tracking_no', $pickupShipment->shipment_tracking_no)->first();
                if ($shipment) {
                    $sortStatus = ShipmentStatusEnum::TO_PICKUP;
                    $sortHistoryData = [
                        'status' => status($sortStatus)['label'],
                        'description' => 'Assigned by "' . Auth::user()->name . '" to "' . \App\Models\User::find($request->driver_id)->name . '" for pickup.',
                        'shipment_id' => $shipment->id,
                    ];
                    shipmentHistory($sortHistoryData);
                    updateShipmentStatus($shipment->id, $sortStatus);
                }
            }

            activityLog('pickup_task_assigned', 'Pickup task #' . $task->id . ' with ' . $pickupShipments->count() . ' shipments assigned to driver ' . \App\Models\User::find($request->driver_id)->name);

            DB::commit();

            $driver = \App\Models\User::find($request->driver_id);
            $merchant = \App\Models\Merchant::with('user')->where('user_id', $task->merchant_id)->first();

            $whatsappMessagingService = new MerchantPickupWhatsappMessegingService(new WhatsAppService());
            $whatsappMessagingService->pickup_task_whatsapp_notification($task);
            $this->notifyDriverPickupAssigned($request->driver_id, $task);
            return sendResponse('Pickup task assigned to driver successfully.', [
                'task_id' => $task->id,
                'shipments_count' => $pickupShipments->count(),
                'driver_name' => \App\Models\User::find($request->driver_id)->name
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse('An error occurred while assigning pickup task.', [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Assign driver to existing pickup task with all merchant shipments
     *
     * @param StoreMerchantPickupShipmentRequest $request Contains merchant_id, driver_id, optional note
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Empty success response
     *   - 404 Not Found: No existing task for merchant
     *   - 422 Unprocessable: Validation errors
     *   - 500 Server Error: Transaction failure
     *
     * Business logic:
     *   - Uses existing MerchantPickupTask with status 'created' for the merchant
     *   - Assigns driver to both the task and all related pickup shipments
     *   - Updates task status to 'assigned'
     *   - Updates all shipment statuses to "TO_PICKUP"
     *   - Creates shipment history entries for audit trail
     *
     * Security:
     *   - Validates task ownership and status
     *   - Ensures shipments belong to the same merchant
     */
   public function Assign_PickupTask_To_PickupRequest(StoreMerchantPickupShipmentRequest $request)
{
    $request->validated();

    try {

        DB::beginTransaction();

        $pickupRequest = null;

        if ($request->filled('pickup_request_id')) {

            $pickupRequest = PickupRequest::lockForUpdate()->find($request->pickup_request_id);

            if (!$pickupRequest) {
                DB::rollBack();
                return sendResponse("Pickup request not found.", [], false, ["Invalid pickup request"], 404);
            }

            $shipments = \App\Models\Shipment::where('merchant_id', $request->merchant_id)
                ->where('status', ShipmentStatusEnum::CREATED)
                ->get();

            $requestedShipmentsCount = (int) ($pickupRequest->shipments_count ?? 0);
            $noOfShipments = $requestedShipmentsCount > 0 ? $requestedShipmentsCount : $shipments->count();

            $task = MerchantPickupTask::create([
                'merchant_id' => $request->merchant_id,
                'driver_id' => $request->driver_id,
                'no_of_shipments' => $noOfShipments,
                'note' => $request->note ?? null,
                'status' => MerchantPickupTaskStatusEnum::TO_PICKUP,
                'pickup_request_id' => $pickupRequest->id,
            ]);

            if ($shipments->count() > 0) {
                foreach ($shipments as $shipment) {

                    MerchantPickupShipment::create([
                        'pickup_task_id' => $task->id,
                        'merchant_id' => $request->merchant_id,
                        'driver_id' => $request->driver_id,
                        'pickup_request_id' => $pickupRequest->id,
                        'shipment_id' => $shipment->id,
                        'shipment_tracking_no' => $shipment->tracking_no,
                        'pre_id' => $shipment->pre_id,
                        'status' => MerchantPickupTaskStatusEnum::TO_PICKUP,
                    ]);

                    $sortStatus = ShipmentStatusEnum::TO_PICKUP;
                    $sortHistoryData = [
                        'status' => status($sortStatus)['label'],
                        'description' => 'Assigned by "' . Auth::user()->name . '" to "' . User::find($request->driver_id)->name . '" for pickup.',
                        'shipment_id' => $shipment->id,
                    ];

                    shipmentHistory($sortHistoryData);
                    updateShipmentStatus($shipment->id, $sortStatus);

                    $shipment->pickup_request_id = $pickupRequest->id;
                    $shipment->save();
                }
            }

            $pickupRequest->merchant_pickup_task_id = $task->id;
            $pickupRequest->counted_by = $request->driver_id;
            $pickupRequest->status = PickupRequestStatusEnum::ASSIGNED;
            $pickupRequest->save();

            activityLog(
                "pickup_request_assigned",
                "Pickup request #{$pickupRequest->id} assigned to driver " . User::find($request->driver_id)->name
            );

            $whatsappMessagingService = new MerchantPickupWhatsappMessegingService(new WhatsAppService());
            $whatsappMessagingService->pickup_task_whatsapp_notification($task);

            $this->notifyDriverPickupAssigned($request->driver_id, $task);

            $this->notifyMerchantPickupAssigned($request->merchant_id, $task);

            try {
                $firebaseService = resolve(\App\Services\DriverRealtimePickupTaskService::class);
                $firebaseService->updateDriverTasks($request->driver_id);
            } catch (\Throwable $e) {
                Log::error('Firebase update failed: ' . $e->getMessage());
            }

            try {
                $firebaseService = resolve(\App\Services\MerchantPickupTaskService::class);
                $firebaseService->updateMerchantTasks($request->merchant_id);
            } catch (\Throwable $e) {
                Log::error('Firebase update failed: ' . $e->getMessage());
            }

            $this->adminCounterService->broadcastToAllAdmins();

            DB::commit();

            return sendResponse("Pickup request assigned to driver successfully.", [
                'pickup_request_id' => $pickupRequest->id,
                'driver_id' => $request->driver_id,
                'status' => $pickupRequest->status,
                'merchant_pickup_task_id' => $task->id,
            ]);
        }

        // ===== ELSE PART (بدون تغيير) =====

        $service = new MerchantPickupTaskService();

        $shipments = \App\Models\Shipment::where('merchant_id', $request->merchant_id)->get();

        $data = [
            'merchant_id' => $request->merchant_id,
            'driver_id' => $request->driver_id,
            'no_of_shipments' => $shipments->count(),
            'note' => $request->note ?? null,
            'status' => \App\Enums\MerchantPickupTaskStatusEnum::TO_PICKUP,
        ];

        $task = $service->createTaskAndShipments(
            $data,
            $shipments->pluck('id')->toArray(),
            \App\Enums\MerchantPickupTaskStatusEnum::TO_PICKUP
        );

        $pickupShipments = \App\Models\MerchantPickupShipment::where('pickup_task_id', $task->id)->get();

        $task->update([
            'driver_id' => $request->driver_id,
            'status' => \App\Enums\MerchantPickupTaskStatusEnum::TO_PICKUP,
            'note' => $request->note ?? $task->note,
            'no_of_shipments' => $pickupShipments->count(),
        ]);

        foreach ($pickupShipments as $pickupShipment) {
            $pickupShipment->update([
                'driver_id' => $request->driver_id
            ]);
        }

        $whatsappMessagingService = new MerchantPickupWhatsappMessegingService(new WhatsAppService());
        $whatsappMessagingService->pickup_task_whatsapp_notification($task);

        try {
            $firebaseService = resolve(\App\Services\DriverRealtimePickupTaskService::class);
            $firebaseService->updateDriverTasks($request->driver_id);
        } catch (\Throwable $e) {
            Log::error('Firebase update failed: ' . $e->getMessage());
        }

        DB::commit();

        return sendResponse('Pickup task assigned to driver successfully.', [
            'task_id' => $task->id,
            'shipments_count' => $pickupShipments->count(),
            'driver_name' => \App\Models\User::find($request->driver_id)->name
        ]);

    } catch (\Throwable $e) {

        DB::rollBack();

        Log::error('Assign Pickup Task Failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return sendResponse(
            'Something went wrong while assigning pickup task',
            [],
            false,
            [$e->getMessage()],
            500
        );
    }
}



    protected function notifyDriverPickupAssigned(int $driverId, \App\Models\MerchantPickupTask $task): void
    {
        try {
            $driver = User::with('driver')->find($driverId);
            if (!$driver || !$driver->driver) {
                Log::warning("Driver {$driverId} does not have driver profile, skip FCM notification.");
                return;
            }

            // Use DriverRealtimePickupTaskService to send FCM with task count
            $pickupService = resolve(\App\Services\DriverRealtimePickupTaskService::class);
            $pickupService->updateDriverTasks($driverId);

            Log::info("Pickup task notification sent for driver {$driverId}, task {$task->id}");
        } catch (\Throwable $e) {
            Log::error('FCM notifyDriverPickupAssigned failed: ' . $e->getMessage());
        }
    }

    protected function notifyMerchantPickupAssigned(int $merchantId, \App\Models\MerchantPickupTask $task): void
    {
        try {
            $merchant = User::with('merchant')->find($merchantId);
            if (!$merchant || !$merchant->merchant) {
                Log::warning("merchant {$merchantId} does not have merchant profile, skip FCM notification.");
                return;
            }

            // Use MerchantPickupTaskService to send FCM with task count
            $pickupService = resolve(\App\Services\MerchantPickupTaskService::class);
            $pickupService->updateMerchantTasks($merchantId);

            Log::info("Pickup task notification sent for merchant {$merchantId}, task {$task->id}");
        } catch (\Throwable $e) {
            Log::error('FCM notifyMerchantPickupAssigned failed: ' . $e->getMessage());
        }
    }

    /**
     * List merchants with pending pickup tasks and their shipments
     *
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Merchant list with pickup tasks and shipment details
     *   - 403 Forbidden: Invalid scope access
     *   - 500 Server Error: Ownership detection failure
     *
     * Returns pickup requests with status 'pending' within the current facility's scope.
     * Each entry includes merchant info and request details for assignment to drivers.
     * Data format matches frontend expectations for PickupAssignShipment.jsx component.
     */

    public function merchant_created_pickup_requests()
    {
        $pickupRequests = PickupRequest::byOwner()
            ->where('status', PickupRequestStatusEnum::PENDING)
            ->with([
                'merchant' => function ($query) {
                    $query->byOwner();
                },
                'shipments' => function ($query) {
                    $query->where('status', ShipmentStatusEnum::CREATED)
                        // ->where(function ($q) {
                        //     $q->whereNull('created_source')
                        //         ->orWhere('created_source', '!=', 'dashboard');
                        // })
                        ->with([
                            'consignee.country:id,name',
                            'consignee.governorate:id,en_name,ar_name,country_id',
                            'consignee.state:id,en_name,ar_name,country_id,governorate_id',
                            'consignee.place:id,en_name,ar_name,state_id',
                        ])
                        ->orderByDesc('created_at');
                },
            ])
            ->get()
            ->filter(function ($request) {
                return $request->merchant !== null;
            });

        $data = $pickupRequests->map(function ($request) {
            $shipments = $request->shipments->values();
            return [
                'merchant' => $request->merchant,
                'shipments_count' => $shipments->count(),
                'requested_shipments_count' => (int) ($request->shipments_count ?? 0),
                'shipments' => $shipments,
                'pickup_request' => $request,
                'pickup_request_id' => $request->id,
                'task_id' => null,
                'task_status' => null
            ];
        })->filter(function ($entry) {
            return ($entry['requested_shipments_count'] ?? 0) > 0;
        })->values();

        return sendResponse("Merchants with created shipments.", $data);
    }

    /**
     * List active pickup requests with their shipments.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function active_pickups()
    {
        try {
            $pickupRequests = PickupRequest::byOwner()
                ->with([
                    'merchant' => function ($query) {
                        $query->byOwner();
                    },
                    'driver',
                    'shipments' => function ($query) {
                        $query->with([
                            'consignee.country:id,name',
                            'consignee.governorate:id,en_name,ar_name,country_id',
                            'consignee.state:id,en_name,ar_name,country_id,governorate_id',
                            'consignee.place:id,en_name,ar_name,state_id',
                        ]);
                    },
                ])->get();

            $data = $pickupRequests->map(function ($request) {
                $missedShipmentTransactions = $request->merchant_pickup_task_missed_shipments;

                if ($missedShipmentTransactions instanceof \Illuminate\Database\Eloquent\Collection && $missedShipmentTransactions->isNotEmpty()) {
                    $missedShipmentTransactions->load(
                        'shipment.consignee.country',
                        'shipment.consignee.governorate',
                        'shipment.consignee.state',
                        'shipment.consignee.place'
                    );
                }
                $missedShipments = $missedShipmentTransactions
                    ->map(function ($transaction) {
                        return $transaction->shipment;
                    })
                    ->filter()
                    ->unique('id')
                    ->values();

                $shipments = $request->shipments->map(function ($shipment) {
                    return [
                        'id' => $shipment->id,
                        'shipment_tracking_no' => $shipment->tracking_no,
                        'status' => $shipment->status,
                        'pickup_request_id' => $shipment->pickup_request_id,
                        'shipment' => $shipment,
                    ];
                });
                $shipmentsCount = max(
                    $shipments->count(),
                    (int) ($request->shipments_count ?? 0)
                );

                return [
                    'pickup_request' => $request,
                    'pickup_request_id' => $request->id,
                    'merchant' => $request->merchant,
                    'driver' => $request->driver,
                    'shipments_count' => $shipmentsCount,
                    'shipments' => $shipments, // not wrapped, just the model array
                    'missed_shipments' => $missedShipments,
                    'has_missed_shipments' => $missedShipments->isNotEmpty(),
                ];
            })->values();

            return sendResponse("Active pickup requests.", $data);
        } catch (Exception $e) {
            return sendResponse("An error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    // {
    //     try {
    //         $user = Auth::user();

    //         $query = Shipment::query();

    //         $query->where('owner_type', facility("type"))
    //             ->where('owner_id', facility("id"));

    //         $merchants = $query->select('merchant_id', DB::raw('COUNT(*) as shipments_count'))
    //             ->whereHas('shipmentHistories', function ($q) {
    //                 $q->where('status', 'CREATED');
    //             })
    //             ->groupBy('merchant_id')
    //             ->with(['merchant', 'shipmentHistories'])
    //             ->get();

    //         // Transform data to match frontend expectations
    //         $merchants = $tasks->map(function ($task) {
    //             $shipments = $task->shipments->map(function ($pickupShipment) {
    //                 return $pickupShipment->shipment;
    //             })->filter(); // Remove any null shipments

    //             return [
    //                 'merchant' => $task->merchant,
    //                 'shipments_count' => $shipments->count(),
    //                 'shipments' => $shipments->values(), // Reset array keys
    //                 'task_id' => $task->id,
    //                 'task_status' => $task->status
    //             ];
    //         })->filter(function ($merchant) {
    //             return $merchant['shipments_count'] > 0; // Only return merchants with shipments
    //         })->values(); // Reset array keys

    //         return sendResponse("Merchants with created shipments.", $merchants);
    //     } catch (Exception $e) {
    //         return sendResponse("An error occurred.", [], false, [$e->getMessage()], 500);
    //     }
    // }

    /**
     * Retrieve detailed pending shipments for specific merchant
     *
     * @param int $merchant_id Target merchant ID
     * @return \Illuminate\Http\JsonResponse
     *   - 200 OK: Shipment collection with consignee details
     *   - 404 Not Found: Invalid merchant ID
     *   - 403 Forbidden: Merchant not in current scope
     * Relationships: consignee.governorate, consignee.state, consignee.place (eager-loaded)
     * Filters:
     *   - Only "CREATED" status shipments
     *   - Limited to current user's operational scope
     * Usage:
     *   - Driver preparation checklist
     *   - Pickup route planning
     */
    public function merchant_created_shipments($merchant_id)
    {
        try {
            $shipments = Shipment::where('status', ShipmentStatusEnum::CREATED)->where('merchant_id', $merchant_id)->with('consignee.governorate', 'consignee.state', 'consignee.place')->get();
            return sendResponse("Shipments.", new MerchantPickupShipmentResource($shipments));
        } catch (Exception $e) {
            return sendResponse("An error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    public function merchant_created_shipments_count($merchant_id)
    {
        try {
            $shipmentCount = Shipment::where('status', ShipmentStatusEnum::CREATED)->where('merchant_id', $merchant_id)->count();
            return sendResponse("Shipments count.", $shipmentCount);
        } catch (Exception $e) {
            return sendResponse("An error occurred.", [], false, [$e->getMessage()], 500);
        }
    }
}
