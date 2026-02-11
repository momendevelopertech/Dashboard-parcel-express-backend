<?php

namespace App\Services\ReversePickup;

use App\Models\ReverseShipment;
use App\Models\Shipment;
use App\Models\Consignee;
use App\Models\ReversePickupRequest;
use App\Models\ReversePickupTask;
use App\Models\ReversePickupShipment;
use App\Enums\ReverseShipmentStatusEnum;
use App\Enums\ReversePickupRequestStatusEnum;
use App\Enums\ReversePickupTaskStatusEnum;
use App\Events\MerchantChatMessageSent;
use App\Models\MerchantChatMessage;
use App\Models\MerchantChatSession;
use App\Models\User;
use App\Services\MerchantPickupWhatsappMessegingService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReverseShipmentService
{
    public function __construct(
        private ReversePickupPricingService $pricingService,
        private ReversePickupFinancialService $financialService
    ) {}

    /**
     * Create reverse pickup request with shipments
     * New format: details array with count per customer
     *
     * @param array $data Request data with details array containing count per customer
     * @return ReversePickupRequest
     */
    public function createReversePickupRequest(array $data): ReversePickupRequest
    {
        return DB::transaction(function () use ($data) {
            $merchantId = $data['merchant_id'];
            $details = $data['details'] ?? [];
            $noOfShipments = $data['shipments_count'];

            // Create the reverse pickup request
            $request = ReversePickupRequest::create([
                'merchant_id' => $merchantId,
                'no_of_shipments' => $noOfShipments,
                'picked_shipments_no' => 0,
                'scheduled_at' => $data['scheduled_at'] ?? now()->addDay(),
                'status' => ReversePickupRequestStatusEnum::PENDING,
                'note' => $data['note'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'owner_type' => $data['owner_type'] ?? null,
            ]);

            // Create reverse shipments for each detail (count times)
            foreach ($details as $detail) {
                $count = $detail['count'] ?? 1;
                $trackingNo = $detail['original_tracking_no'] ?? null;
                $originalShipment = null;

                // Try to find original shipment if tracking number provided
                if ($trackingNo) {
                    $originalShipment = Shipment::where('tracking_no', $trackingNo)->first();
                }

                // Get addresses and IDs from original shipment if exists
                $customerAddress = null;
                $merchantAddress = null;
                $senderId = null;
                $senderAddressId = null;
                $receiverAddressId = null;

                if ($originalShipment) {
                    $customerAddress = $originalShipment->deliveryAddress;
                    $merchantAddress = $originalShipment->pickupAddress;
                    $senderId = $originalShipment->consignee_id ?? $originalShipment->customer_id;
                    $senderAddressId = $customerAddress?->id;
                    $receiverAddressId = $merchantAddress?->id;
                }

                // Auto-create or find consignee based on customer data
                $consigneeId = null;
                if ($detail['customer_name'] && $detail['customer_phone']) {
                    $consignee = Consignee::updateOrCreate(
                        [
                            'cellphone' => $detail['customer_phone'],
                            'owner_id' => $data['owner_id'] ?? null,
                            'owner_type' => $data['owner_type'] ?? null,
                        ],
                        [
                            'name' => $detail['customer_name'],
                            'latitude' => $detail['latitude'] ?? null,
                            'longitude' => $detail['longitude'] ?? null,
                            'location_url' => $detail['location_url'] ?? null,
                            'streetAddress' => $detail['customer_address'] ?? null,
                            'country_id' => $detail['country_id'] ?? null,
                            'governorate_id' => $detail['governorate_id'] ?? null,
                            'state_id' => $detail['state_id'] ?? null,
                        ]
                    );
                    $consigneeId = $consignee->id;
                    // $senderId = $senderId ?? $consigneeId ?? null;
                }

                // Calculate pricing
                $pricing = [];
                $driverCommission = 0;
                if ($customerAddress && $customerAddress->state_id) {
                    $pricing = $this->pricingService->calculateReturnFee($merchantId, $customerAddress->state_id);
                    $driverCommission = $this->pricingService->calculateDriverCommission(0, $customerAddress->state_id);
                }

                // Create multiple shipments based on count
                for ($i = 0; $i < $count; $i++) {
                    $shipment = ReverseShipment::create([
                        'parent_shipment_id' => $originalShipment?->id,
                        'original_tracking_no' => $trackingNo,
                        'type' => 'reverse_pickup',
                        'sender_id' => $senderId,
                        'customer_name' => $detail['customer_name'] ?? null,
                        'customer_phone' => $detail['customer_phone'] ?? null,
                        'customer_address' => $detail['customer_address'] ?? null,
                        'consignee_id' => $consigneeId,
                        'latitude' => $detail['latitude'] ?? null,
                        'longitude' => $detail['longitude'] ?? null,
                        'location_url' => $detail['location_url'] ?? null,
                        'receiver_id' => $merchantId,
                        'merchant_id' => $merchantId,
                        'sender_address_id' => $senderAddressId,
                        'receiver_address_id' => $receiverAddressId,
                        'status' => ReverseShipmentStatusEnum::REVERSE_CREATED,
                        'pricing_calculated' => $pricing,
                        'driver_commission' => $driverCommission,
                        'merchant_fee_charged' => false,
                        'reverse_pickup_request_id' => $request->id,
                    ]);
                    $user = Auth::user();
                    shipmentHistory([
                        'status' => ReverseShipmentStatusEnum::REVERSE_CREATED,
                        'description' => 'Reverse Shipment created by merchant name:' . $user->name,
                        'shipment_id' => $shipment->id,
                        'created_at' => now(),
                        // proof is added by orchestrator
                    ]);
                }
            }
            $this->notify('request', $request);
            $this->sendAppMessage($request);
            return $request->load('reverseShipments');
        });
    }

    /**
     * Assign reverse shipments to driver (create task - similar to forward flow)
     *
     * @param int $requestId
     * @param int $driverId
     * @return ReversePickupTask
     */
    public function assignToDriver(int $requestId, int $driverId): ReversePickupTask
    {
        return DB::transaction(function () use ($requestId, $driverId) {
            $request = ReversePickupRequest::with('reverseShipments')->whereHas('reverseShipments', function ($query) {
                $query->where('status', ReverseShipmentStatusEnum::REVERSE_CREATED);
            })->findOrFail($requestId);

            // Create reverse pickup task
            $task = ReversePickupTask::create([
                'merchant_id' => $request->merchant_id,
                'driver_id' => $driverId,
                'no_of_shipments' => $request->no_of_shipments,
                'picked_shipments_no' => 0,
                'note' => $request->note,
                'status' => ReversePickupTaskStatusEnum::TO_PICKUP,
                'reverse_pickup_request_id' => $request->id,
                'owner_id' => $request->owner_id,
                'owner_type' => $request->owner_type,
                'scheduled_at' => $request->scheduled_at,
                'assigned_by' => Auth::id(),
            ]);

            // Create reverse pickup shipments (link to task)
            foreach ($request->reverseShipments as $reverseShipment) {
                ReversePickupShipment::create([
                    'reverse_pickup_task_id' => $task->id,
                    'reverse_pickup_request_id' => $request->id,
                    'reverse_shipment_id' => $reverseShipment->id,
                    'driver_id' => $driverId,
                    'merchant_id' => $request->merchant_id,
                    'status' => 'to_pickup',
                ]);

                // Update reverse shipment status
                $reverseShipment->update([
                    'status' => ReverseShipmentStatusEnum::REVERSE_ASSIGNED,
                ]);
                $driver = User::find($driverId);
                $user = Auth::user();
                shipmentHistory([
                    'status' => ReverseShipmentStatusEnum::REVERSE_ASSIGNED,
                    'description' => 'Reverse Shipment assigned to driver name:' . $driver->name . ' by' . $user->name,
                    'shipment_id' => $reverseShipment->id,
                    'created_at' => now(),
                    // proof is added by orchestrator
                ]);
            }

            // Update request status to ASSIGNED
            $request->update([
                'status' => ReversePickupRequestStatusEnum::ASSIGNED,
            ]);

            // Update task status to TO_PICKUP (ready for driver to start)
            $task->update([
                'status' => ReversePickupTaskStatusEnum::TO_PICKUP,
            ]);

            $this->notify('task', null, $task);
            $this->notifyDriverPickupAssigned($driverId);
            $this->sendWhatSappMessage($task);
            return $task->load(['shipments.reverseShipment', 'driver', 'merchant']);
        });
    }

    /**
     * Assign reverse shipments to driver with flexible grouping
     * Creates multiple tasks based on grouping field
     *
     * @param int $requestId
     * @param int $driverId
     * @param string $groupBy Field to group by (customer_phone, customer_name, consignee_id, customer_address)
     * @return array Array of created tasks
     */
    public function assignToDriverPerCustomer(int $requestId, int $driverId, string $groupBy = 'customer_phone'): array
    {
        return DB::transaction(function () use ($requestId, $driverId) {
            $request = ReversePickupRequest::with('reverseShipments')->findOrFail($requestId);

            // Group shipments by customer (using phone number as unique identifier)
            $shipmentsByCustomer = $request->reverseShipments->groupBy('customer_phone');

            $tasks = [];

            foreach ($shipmentsByCustomer as $customerPhone => $shipments) {
                // Create one task per customer
                $task = ReversePickupTask::create([
                    'merchant_id' => $request->merchant_id,
                    'driver_id' => $driverId,
                    'no_of_shipments' => $shipments->count(),
                    'picked_shipments_no' => 0,
                    'note' => $request->note . " (Customer: {$shipments->first()->customer_name})",
                    'status' => ReversePickupTaskStatusEnum::PENDING,
                    'reverse_pickup_request_id' => $request->id,
                    'owner_id' => $request->owner_id,
                    'owner_type' => $request->owner_type,
                    'scheduled_at' => $request->scheduled_at,
                    'assigned_by' => Auth::id(),
                ]);

                // Link shipments to this task
                foreach ($shipments as $reverseShipment) {
                    ReversePickupShipment::create([
                        'reverse_pickup_task_id' => $task->id,
                        'reverse_pickup_request_id' => $request->id,
                        'reverse_shipment_id' => $reverseShipment->id,
                        'driver_id' => $driverId,
                        'merchant_id' => $request->merchant_id,
                        'status' => 'to_pickup',
                    ]);

                    // Update reverse shipment status
                    $reverseShipment->update([
                        'status' => ReverseShipmentStatusEnum::REVERSE_ASSIGNED,
                    ]);
                    $driver = User::find($driverId);
                    $user = Auth::user();
                    shipmentHistory([
                        'status' => ReverseShipmentStatusEnum::REVERSE_ASSIGNED,
                        'description' => 'Reverse Shipment assigned to driver name:' . $driver->name . ' by' . $user->name,
                        'shipment_id' => $reverseShipment->id,
                        'created_at' => now(),
                        // proof is added by orchestrator
                    ]);
                }

                $tasks[] = $task->load(['shipments.reverseShipment', 'driver', 'merchant']);
            }

            // Update request status to IN_PROGRESS
            $request->update([
                'status' => ReversePickupRequestStatusEnum::IN_PROGRESS,
            ]);

            $this->notify('task', null, $task);
            $this->notifyDriverPickupAssigned($driverId);
            $this->sendWhatSappMessage($task);

            return $tasks;
        });
    }

    /**
     * Assign specific shipments to driver (manual selection)
     * Admin manually selects which shipments to assign
     *
     * @param int $requestId
     * @param int $driverId
     * @param array $reverseShipmentIds Array of reverse_shipment IDs to assign
     * @return ReversePickupTask
     */
    public function assignSpecificShipmentsToDriver(int $requestId, int $driverId, array $reverseShipmentIds): ReversePickupTask
    {
        return DB::transaction(function () use ($requestId, $driverId, $reverseShipmentIds) {
            $request = ReversePickupRequest::findOrFail($requestId);

            // Get the selected shipments
            $selectedShipments = ReverseShipment::whereIn('id', $reverseShipmentIds)
                ->where('reverse_pickup_request_id', $requestId)
                ->get();

            if ($selectedShipments->count() !== count($reverseShipmentIds)) {
                throw new \Exception('Some shipment IDs are invalid or do not belong to this request');
            }

            // Create reverse pickup task for selected shipments only
            $task = ReversePickupTask::create([
                'merchant_id' => $request->merchant_id,
                'driver_id' => $driverId,
                'no_of_shipments' => $selectedShipments->count(),
                'picked_shipments_no' => 0,
                'note' => $request->note . " (Manual selection: {$selectedShipments->count()} shipments)",
                'status' => ReversePickupTaskStatusEnum::PENDING,
                'reverse_pickup_request_id' => $request->id,
                'owner_id' => $request->owner_id,
                'owner_type' => $request->owner_type,
                'scheduled_at' => $request->scheduled_at,
                'assigned_by' => Auth::id(),
            ]);

            // Link only selected shipments to this task
            foreach ($selectedShipments as $reverseShipment) {
                ReversePickupShipment::create([
                    'reverse_pickup_task_id' => $task->id,
                    'reverse_pickup_request_id' => $request->id,
                    'reverse_shipment_id' => $reverseShipment->id,
                    'driver_id' => $driverId,
                    'merchant_id' => $request->merchant_id,
                    'status' => 'to_pickup',
                ]);

                // Update reverse shipment status
                $reverseShipment->update([
                    'status' => ReverseShipmentStatusEnum::REVERSE_ASSIGNED,
                ]);
                $driver = User::find($driverId);
                $user = Auth::user();
                shipmentHistory([
                    'status' => ReverseShipmentStatusEnum::REVERSE_ASSIGNED,
                    'description' => 'Reverse Shipment assigned to driver name:' . $driver->name . ' by' . $user->name,
                    'shipment_id' => $reverseShipment->id,
                    'created_at' => now(),
                    // proof is added by orchestrator
                ]);
            }

            // Update request status to IN_PROGRESS
            $request->update([
                'status' => ReversePickupRequestStatusEnum::IN_PROGRESS,
            ]);

            $this->notify('task', null, $task);
            $this->notifyDriverPickupAssigned($driverId);
            $this->sendWhatSappMessage($task);

            return $task->load(['shipments.reverseShipment', 'driver', 'merchant']);
        });
    }

    /**
     * Mark reverse shipment as returned to merchant (final delivery)
     * Step 5: This triggers merchant wallet debit
     *
     * @param int $reverseShipmentId
     * @return void
     * @throws \Exception
     */
    public function markAsReturnedToMerchant(int $reverseShipmentId): void
    {
        DB::transaction(function () use ($reverseShipmentId) {
            $reverseShipment = ReverseShipment::findOrFail($reverseShipmentId);

            // Check if already charged
            if ($reverseShipment->merchant_fee_charged) {
                throw new \Exception('Merchant has already been charged for this reverse shipment');
            }

            // Update status
            $reverseShipment->update([
                'status' => ReverseShipmentStatusEnum::RETURNED_TO_MERCHANT,
                'delivered_to_merchant_at' => now(),
            ]);
            $driver = Auth::user();

            shipmentHistory([
                'status' => ReverseShipmentStatusEnum::RETURNED_TO_MERCHANT,
                'description' => 'Reverse Shipment returned to merchant by' . $driver->name,
                'shipment_id' => $reverseShipment->id,
                'created_at' => now(),
                // proof is added by orchestrator
            ]);

            // IMPORTANT: Debit merchant wallet (Step 5)
            // $this->financialService->debitMerchantFee($reverseShipment->id);

            // Mark as charged
            $reverseShipment->update([
                'merchant_fee_charged' => true,
            ]);



            // Check if all shipments returned to merchant, mark task and request as COMPLETED
            $reversePickupShipment = ReversePickupShipment::where('reverse_shipment_id', $reverseShipment->id)->first();
            if ($reversePickupShipment) {
                // Update task if all shipments delivered
                if ($reversePickupShipment->reverse_pickup_task_id) {
                    $task = ReversePickupTask::find($reversePickupShipment->reverse_pickup_task_id);
                    if ($task) {
                        $totalShipments = $task->no_of_shipments;
                        $deliveredCount = ReversePickupShipment::where('reverse_pickup_task_id', $task->id)
                            ->whereHas('reverseShipment', function ($q) {
                                $q->where('status', ReverseShipmentStatusEnum::RETURNED_TO_MERCHANT);
                            })
                            ->count();

                        // If all shipments delivered, mark task as COMPLETED
                        if ($deliveredCount === $totalShipments) {
                            $task->update(['status' => ReversePickupTaskStatusEnum::COMPLETED]);
                        }
                    }
                }

                // Update request if all shipments delivered
                if ($reversePickupShipment->reverse_pickup_request_id) {
                    $request = ReversePickupRequest::find($reversePickupShipment->reverse_pickup_request_id);
                    if ($request) {
                        $totalShipments = $request->no_of_shipments;
                        $deliveredCount = $request->reverseShipments()
                            ->where('status', ReverseShipmentStatusEnum::RETURNED_TO_MERCHANT)
                            ->count();

                        // If all shipments delivered, mark request as COMPLETED
                        if ($deliveredCount === $totalShipments) {
                            $request->update(['status' => ReversePickupRequestStatusEnum::COMPLETED]);
                        }
                    }
                }
            }
        });
    }

    /**
     * Cancel a reverse shipment
     *
     * @param int $reverseShipmentId
     * @param string|null $reason
     * @return void
     */
    public function cancelReverseShipment(int $reverseShipmentId, ?string $reason = null): void
    {
        DB::transaction(function () use ($reverseShipmentId, $reason) {
            $reverseShipment = ReverseShipment::findOrFail($reverseShipmentId);

            $reverseShipment->update([
                'status' => ReverseShipmentStatusEnum::REVERSE_CANCELLED,
            ]);

            // Create shipment history
            if (function_exists('shipmentHistory')) {
                shipmentHistory([
                    'status' => 'Cancelled',
                    'description' => 'Reverse shipment cancelled by' . Auth::user()->name . '. Reason: ' . ($reason ?? 'No reason provided'),
                    'shipment_id' => $reverseShipment->id,
                ]);
            }
        });
    }

    private function notify($type, $reversePickupRequest = null, $task = null)
    {
        $user = $type == 'request' ? Auth::user() : $task->merchant;
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
        }
        if ($type == 'request') {
            notify_workspace_users(
                $merchant->owner_id,
                $merchant->owner_type,
                ['Assign Pickup Task access'],
                'New Reverse Pickup Request',
                'A new reverse pickup request has been created by ' . $user->name,
                [
                    'type' => 'reverse_pickup_request',
                    'reverse_pickup_request_id' => $reversePickupRequest->id,
                    'merchant_name' => $user->name,
                    'merchant_id' => $user->id,
                    'facility_name' => $facilityName,
                    'facility_type' => $facilityType,
                    'facility_id' => $merchant->owner_id,
                    'scheduled_at' => $reversePickupRequest->scheduled_at,
                    'shipments_count' => $reversePickupRequest->no_of_shipments,
                    'note' => $reversePickupRequest->note,
                ],
                'reverse_pickup_request',
                false
            );
        } elseif ($type == 'task') {

            notify_workspace_users(
                $merchant->owner_id,
                $merchant->owner_type,
                ['Pickup Task access'],
                'New Reverse Pickup Task',
                'A new reverse pickup task has been assigned from merchant ' . $user->name,
                [
                    'type' => 'reverse_pickup_task_created',
                    'reverse_pickup_task_id' => $task->id,
                    'merchant_name' => $user->name,
                    'facility_name' => $facilityName,
                    'facility_type' => $facilityType,
                    'facility_id' => $merchant->owner_id,
                    'scheduled_date' => $task->created_at,
                    'shipments_count' => $task->no_of_shipments
                ],
                'reverse_pickup_task',
                false
            );
        }
    }
    private function sendAppMessage($reversePickupRequest)
    {
        $user = Auth::user();
        $merchantId = $user->id;
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
            'type' => 'reverse_pickup_task',
            'data' => $reversePickupRequest->toArray(),
            'timestamp' => now()->toISOString()
        ]);
        $message = MerchantChatMessage::create([
            'merchant_chat_session_id' => $session->id,
            'sender_type' => 'MERCHANT',
            'sender_id' => $merchantId,
            'sender_name' => $user->name,
            'message' => $messageContent,
            'message_type' => 'CARD'
        ]);
        $message->load('chatSession.merchant');
        broadcast(new MerchantChatMessageSent($message));
        $session->touch();
    }
    private function notifyDriverPickupAssigned($driverId)
    {
        $tasksQuery = ReversePickupTask::where('driver_id', $driverId)
            ->where('status', ReversePickupTaskStatusEnum::TO_PICKUP);

        $tasksCount = $tasksQuery->count();

        // 2. Get the latest assigned task details
        $latestTask = $tasksQuery->latest()->first();
        $pickupService = resolve(\App\Services\DriverRealtimePickupTaskService::class);
        $pickupService->sendFcmNotification($driverId, $tasksCount, $latestTask);
    }
    private function sendWhatSappMessage($task)
    {
        $whatsappMessagingService = new MerchantPickupWhatsappMessegingService(new WhatsAppService());
        $whatsappMessagingService->renserse_pickup_task_whatsapp_notification($task);
    }
}
