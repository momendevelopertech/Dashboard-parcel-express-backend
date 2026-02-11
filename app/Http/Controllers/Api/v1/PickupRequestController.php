<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Enums\PickupRequestStatusEnum;
use App\Enums\ShipmentStatusEnum;
use App\Enums\MerchantPickupTaskStatusEnum;
use App\Events\MerchantChatMessageSent;
use App\Events\MerchantTicketChanged;
use App\Events\MerchantTicketMessageSent;
use App\Models\MerchantChatMessage;
use App\Models\MerchantChatSession;
use App\Models\MerchantTicket;
use App\Models\MerchantTicketMessage;
use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use App\Services\AdminCounterService;

class PickupRequestController extends Controller
{
    public $adminCounterService;
    public function __construct(AdminCounterService $adminCounterService)
    {
        $this->adminCounterService = $adminCounterService;
    }
    /**
     * Create a pickup request by merchant
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function create_pickup_request_by_merchant(Request $request)
    // {
    //     $data = $request->validate([
    //         'scheduled_at' => 'required|date',
    //         'shipments_count' => 'required|integer|min:1',
    //         // 'merchant_id'   => 'required|exists:users,id',
    //         'note' => 'nullable|string',
    //     ]);

    //     $userId = $request->input('sender_id') ?? Auth::id();
    //     $user = User::findOrFail($userId);

    //     if (!$user->merchant) {
    //         return response()->json([
    //             'message' => 'Merchant not found for this user.',
    //             'success' => false,
    //         ], 404);
    //     }

    //     $lastPickupRequest = PickupRequest::where('merchant_user_id', $userId)
    //         ->latest('created_at')
    //         ->first();

    //     try {
    //         if ($lastPickupRequest && $lastPickupRequest->status === PickupRequestStatusEnum::TO_PICKUP) {
    //             [$pickupRequest, $linkedCount] = DB::transaction(function () use ($data, $userId, $lastPickupRequest) {

    //                 // أول حاجة نفك ربط الشحنات القديمة اللي لسه CREATED
    //                 Shipment::where('pickup_request_id', $lastPickupRequest->id)
    //                     ->where('status', ShipmentStatusEnum::CREATED)
    //                     ->update(['pickup_request_id' => null]);

    //                 // نحدّث بيانات الطلب نفسه
    //                 $lastPickupRequest->update([
    //                     'scheduled_at' => $data['scheduled_at'],
    //                     'shipments_count' => $data['shipments_count'],
    //                     'note' => $data['note'] ?? null,
    //                     // ممكن تسيب الـ status زي ما هو TO_PICKUP
    //                     // أو تعيده لـ PENDING لو ده المنطق عندك
    //                     // 'status'       => PickupRequestStatusEnum::PENDING,
    //                 ]);

    //                 // نجيب الشحنات الجديدة اللي هتتربط بالطلب (أحدث شحنات بـ status CREATED)
    //                 $shipments = Shipment::where('merchant_id', $userId)
    //                     ->whereNull('pickup_request_id')
    //                     ->where('status', ShipmentStatusEnum::CREATED)
    //                     ->latest()
    //                     ->take($lastPickupRequest->shipments_count)
    //                     ->get();

    //                 if ($shipments->isNotEmpty()) {
    //                     Shipment::whereIn('id', $shipments->pluck('id'))
    //                         ->update(['pickup_request_id' => $lastPickupRequest->id]);
    //                 }

    //                 return [$lastPickupRequest->fresh(), $shipments->count()];
    //             });
    //         } else {
    //             // الحالة العادية: مفيش TO_PICKUP → نعمل طلب جديد
    //             [$pickupRequest, $linkedCount] = DB::transaction(function () use ($data, $userId) {
    //                 $pickupRequest = PickupRequest::create([
    //                     'merchant_user_id' => $userId,
    //                     'scheduled_at' => $data['scheduled_at'],
    //                     'shipments_count' => $data['shipments_count'],
    //                     'note' => $data['note'] ?? null,
    //                     'status' => PickupRequestStatusEnum::PENDING,
    //                     // 'ref'           => PickupRequest::generateRef(),
    //                 ]);

    //                 $shipments = Shipment::where('merchant_id', $userId)
    //                     ->whereNull('pickup_request_id')
    //                     ->where('status', ShipmentStatusEnum::CREATED)
    //                     ->latest()
    //                     ->take($pickupRequest->shipments_count)
    //                     ->get();

    //                 if ($shipments->isNotEmpty()) {
    //                     Shipment::whereIn('id', $shipments->pluck('id'))
    //                         ->update(['pickup_request_id' => $pickupRequest->id]);
    //                 }

    //                 return [$pickupRequest, $shipments->count()];
    //             });
    //         }

    //         // Send notification to supervisors in the same workspace
    //         $merchant = $user->merchant;
    //         if ($merchant && $merchant->owner_id && $merchant->owner_type) {
    //             notify_workspace_users(
    //                 $merchant->owner_id,
    //                 $merchant->owner_type,
    //                 ['SuperVisor', 'Customer Service', 'Super Admin'],
    //                 'New Pickup Request',
    //                 'A new pickup request has been created/updated by ' . $user->name,
    //                 [
    //                     'type' => 'pickup_request',
    //                     'pickup_request_id' => $pickupRequest->id,
    //                     'merchant_name' => $user->name,
    //                     'merchant_id' => $userId,
    //                     'scheduled_at' => $pickupRequest->scheduled_at,
    //                     'shipments_count' => $pickupRequest->shipments_count,
    //                     'linked_shipments_count' => $linkedCount,
    //                     'note' => $pickupRequest->note,
    //                 ],
    //                 'pickup_request',
    //                 false
    //             );
    //         }
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'message' => 'Pickup request failed to create.',
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }

    //     return response()->json([
    //         'message' => 'Pickup request handled successfully.',
    //         'data' => $pickupRequest->toArray(),
    //         'linked_shipments_count' => $linkedCount,
    //     ]);
    // }

    public function create_pickup_request_by_merchant(Request $request)
    {
        $data = $request->validate([
            'scheduled_at' => 'required|date',
            'shipments_count' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        $userId = $request->input('sender_id') ?? Auth::id();
        $user = User::findOrFail($userId);

        if (!$user->merchant) {
            return response()->json([
                'message' => 'Merchant not found for this user.',
                'success' => false
            ], 404);
        }

        try {
            // Check if there's an existing MerchantPickupTask with status 'to_pickup' for this merchant
            $existingTask = \App\Models\MerchantPickupTask::where('merchant_id', $userId)
                ->where('status', MerchantPickupTaskStatusEnum::TO_PICKUP)
                ->latest('created_at')
                ->first();

            if ($existingTask) {
                // Update existing task instead of creating new pickup request
                [$pickupRequest, $linkedCount] = DB::transaction(function () use ($data, $userId, $existingTask) {

                    // Get the associated pickup request
                    $pickupRequest = $existingTask->pickupRequest;

                    if ($pickupRequest) {
                        // Unlink old shipments that are still CREATED
                        Shipment::where('pickup_request_id', $pickupRequest->id)
                            ->where('status', ShipmentStatusEnum::CREATED)
                            ->where(function($q) {
                                $q->whereNull('created_source')
                                  ->orWhere('created_source', '!=', 'dashboard');
                            })
                            ->update(['pickup_request_id' => null]);

                        // Update the pickup request
                        $pickupRequest->update([
                            'scheduled_at' => $data['scheduled_at'],
                            'shipments_count' => $data['shipments_count'],
                            'note' => $data['note'] ?? null,
                        ]);
                    } else {
                        // If no pickup request exists, create one
                        $pickupRequest = PickupRequest::create([
                            'merchant_user_id' => $userId,
                            'scheduled_at' => $data['scheduled_at'],
                            'shipments_count' => $data['shipments_count'],
                            'note' => $data['note'] ?? null,
                            'status' => PickupRequestStatusEnum::PENDING,
                        ]);

                        // Link the task to the new pickup request
                        $existingTask->update(['pickup_request_id' => $pickupRequest->id]);
                    }

                    // Update the task details
                    $existingTask->update([
                        'no_of_shipments' => $data['shipments_count'],
                        'note' => $data['note'] ?? null,
                    ]);

                    // Get new shipments to link
                    $shipments = Shipment::where('merchant_id', $userId)
                        ->whereNull('pickup_request_id')
                        ->where('status', ShipmentStatusEnum::CREATED)
                        ->where(function($q) {
                            $q->whereNull('created_source')
                              ->orWhere('created_source', '!=', 'dashboard');
                        })
                        ->latest()
                        ->take($data['shipments_count'])
                        ->get();

                    if ($shipments->isNotEmpty()) {
                        Shipment::whereIn('id', $shipments->pluck('id'))
                            ->update(['pickup_request_id' => $pickupRequest->id]);
                    }
                     $this->adminCounterService->broadcastToAllAdmins();
                    return [$pickupRequest->fresh(), $shipments->count()];
                });

                // Send notification about updated task
                $merchant = $user->merchant;
                if ($merchant && $merchant->owner_id && $merchant->owner_type) {
                    // Get facility info
                    $facilityName = null;
                    $facilityType = null;
                    try {
                        $facilityModel = $merchant->owner_type::find($merchant->owner_id);
                        if ($facilityModel) {
                            $facilityName = $facilityModel->name;
                            $facilityType = class_basename($merchant->owner_type);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Could not fetch facility name', ['error' => $e->getMessage()]);
                    }
                               
                    notify_workspace_users(
                        $merchant->owner_id,
                        $merchant->owner_type,
                        ['Assign Pickup Task access'],
                        'Pickup Request Updated',
                        'Pickup request has been updated by ' . $user->name,
                        [
                            'type' => 'pickup_request_updated',
                            'pickup_request_id' => $pickupRequest->id,
                            'pickup_task_id' => $existingTask->id,
                            'merchant_name' => $user->name,
                            'merchant_id' => $userId,
                            'scheduled_at' => $pickupRequest->scheduled_at,
                            'shipments_count' => $pickupRequest->shipments_count,
                            'linked_shipments_count' => $linkedCount,
                            'note' => $pickupRequest->note,
                            'facility_name' => $facilityName,
                            'facility_type' => $facilityType,
                            'facility_id' => $merchant->owner_id,
                        ],
                        'pickup_request',
                        false
                    );
                }
                     $this->adminCounterService->broadcastToAllAdmins();

                return response()->json([
                    'message' => 'Existing pickup task updated successfully.',
                    'data' => $pickupRequest->toArray(),
                    'pickup_task_id' => $existingTask->id,
                    'linked_shipments_count' => $linkedCount,
                    'updated' => true,
                ]);
            }

            // No existing to_pickup task - create new pickup request
            [$pickupRequest, $linkedCount] = DB::transaction(function () use ($data, $userId) {
                $pickupRequest = PickupRequest::create([
                    'merchant_user_id' => $userId,
                    'scheduled_at' => $data['scheduled_at'],
                    'shipments_count' => $data['shipments_count'],
                    'note' => $data['note'] ?? null,
                    'status' => PickupRequestStatusEnum::PENDING,
                ]);

                $shipments = Shipment::where('merchant_id', $userId)
                    ->whereNull('pickup_request_id')
                    ->whereIn('status', [ShipmentStatusEnum::CREATED, ShipmentStatusEnum::TO_PICKUP])
                    ->latest()
                    ->take($pickupRequest->shipments_count)
                    ->get();

                if ($shipments->isNotEmpty()) {
                    Shipment::whereIn('id', $shipments->pluck('id'))
                        ->update(['pickup_request_id' => $pickupRequest->id]);
                }

                return [$pickupRequest, $shipments->count()];
            });

            // Send notification to supervisors
            $merchant = $user->merchant;
            if ($merchant && $merchant->owner_id && $merchant->owner_type) {
                // Get facility info
                $facilityName = null;
                $facilityType = null;
                try {
                    $facilityModel = $merchant->owner_type::find($merchant->owner_id);
                    if ($facilityModel) {
                        $facilityName = $facilityModel->name;
                        $facilityType = class_basename($merchant->owner_type);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not fetch facility name', ['error' => $e->getMessage()]);
                }
                     $this->adminCounterService->broadcastToAllAdmins();

                notify_workspace_users(
                    $merchant->owner_id,
                    $merchant->owner_type,
                    ['Assign Pickup Task access'],
                    'New Pickup Request',
                    'A new pickup request has been created by ' . $user->name,
                    [
                        'type' => 'pickup_request',
                        'pickup_request_id' => $pickupRequest->id,
                        'merchant_name' => $user->name,
                        'merchant_id' => $userId,
                        'facility_name' => $facilityName,
                        'facility_type' => $facilityType,
                        'facility_id' => $merchant->owner_id,
                        'scheduled_at' => $pickupRequest->scheduled_at,
                        'shipments_count' => $pickupRequest->shipments_count,
                        'linked_shipments_count' => $linkedCount,
                        'note' => $pickupRequest->note,
                    ],
                    'pickup_request',
                    false
                );
            }

            return response()->json([
                'message' => 'Pickup request created successfully.',
                'data' => $pickupRequest->toArray(),
                'linked_shipments_count' => $linkedCount,
                'updated' => false,
            ]);

        } catch (Exception $e) {
            \Log::error('Pickup request failed', [
                'merchant_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Pickup request failed to create.',
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createTaskAndShipmentsFromPickupRequest(Request $request)
    {
        $data = $request->validate([
            'scheduled_at' => 'required|date',
            'shipments_count' => 'required|integer|min:1',
            'merchant_id' => 'required|exists:users,id',
            'driver_id' => 'required|exists:users,id',
            'note' => 'nullable|string',
            'pickup_request_id' => 'required|exists:pickup_requests,id',
        ]);

        $userId = $request->input('sender_id') ?? Auth::id();
        $user = User::findOrFail($userId);
        if (!$user->merchant) {
            return response()->json([
                'message' => 'Merchant not found for this user.',
                'success' => false
            ], 404);
        }

        $service = new \App\Services\MerchantPickupTaskService();
        try {
            $createdTask = $service->createTaskAndShipments([
                'merchant_id' => $data['merchant_id'],
                'driver_id' => $data['driver_id'] ?? null,
                'no_of_shipments' => $data['shipments_count'],
                'note' => $data['note'] ?? null,
                'pickup_request_id' => $data['pickup_request_id'],
                'status' => MerchantPickupTaskStatusEnum::PENDING,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Pickup task failed to create.',
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }

        // Optionally: create chat session/notification as required (link task not pickup request)
        $merchantId = $user->merchant->id;
        $senderName = $request->input('sender_name') ?? $user->name;
        $session = MerchantChatSession::where('merchant_id', $merchantId)
            ->where('status', 'ACTIVE')
            ->orderBy('created_at', 'desc')
            ->first();
        if (!$session) {
            $session = MerchantChatSession::create([
                'merchant_id' => $merchantId,
                'session_id' => 'CHAT' . now()->format('Ymd') . strtoupper(Str::random(8)),
                'subject' => 'General Chat',
                'priority' => 'MEDIUM',
                'status' => 'ACTIVE'
            ]);
        }
        $messageContent = json_encode([
            'type' => 'pickup_task',
            'data' => $createdTask->toArray(),
            'timestamp' => now()->toISOString()
        ]);
        $message = MerchantChatMessage::create([
            'merchant_chat_session_id' => $session->id,
            'sender_type' => 'MERCHANT',
            'sender_id' => $merchantId,
            'sender_name' => $senderName,
            'message' => $messageContent,
            'message_type' => 'CARD'
        ]);
        $message->load('chatSession.merchant');

        // Send notification to workspace users only
        $merchant = $user->merchant;
        if ($merchant && $merchant->owner_id && $merchant->owner_type) {
            // Get facility info
            $facilityName = null;
            $facilityType = null;
            try {
                $facilityModel = $merchant->owner_type::find($merchant->owner_id);
                if ($facilityModel) {
                    $facilityName = $facilityModel->name;
                    $facilityType = class_basename($merchant->owner_type);
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch facility name', ['error' => $e->getMessage()]);
            }

            notify_workspace_users(
                $merchant->owner_id,
                $merchant->owner_type,
                ['Pickup Task access'],
                'New Pickup Task',
                'A new pickup task has been assigned from merchant ' . $user->name,
                [
                    'type' => 'pickup_task_created',
                    'pickup_task_id' => $createdTask->id,
                    'merchant_name' => $user->name,
                    'facility_name' => $facilityName,
                    'facility_type' => $facilityType,
                    'facility_id' => $merchant->owner_id,
                    'scheduled_date' => $createdTask->created_at,
                    'shipments_count' => $createdTask->no_of_shipments
                ],
                'pickup_task',
                false
            );
        }

        broadcast(new MerchantChatMessageSent($message));
        $session->touch();

        return response()->json([
            'message' => 'Pickup task created successfully.',
            'data' => $createdTask->toArray(),
        ]);
    }
}