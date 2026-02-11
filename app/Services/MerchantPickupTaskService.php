<?php

namespace App\Services;

use App\Models\MerchantPickupTask;
use App\Models\MerchantPickupShipment;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Enums\MerchantPickupTaskStatusEnum;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;


class MerchantPickupTaskService
{

    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }
    /**
     * Create a pickup task, its shipments, and update shipment statuses
     *
     * @param array $data               Task payload [merchant_id, driver_id, no_of_shipments, note, (optional) pickup_request_id]
     * @param array $shipmentIds        The IDs or tracking numbers of the shipments
     * @param string $assignStatus      The status label to assign e.g., 'ASSIGNED_FOR_PICKUP'
     * @return MerchantPickupTask|null  Returns the created task or null on failure
     * @throws Exception
     */
    public function createTaskAndShipments(array $data, array $shipmentIds = [], $assignStatus = 'ASSIGNED_FOR_PICKUP', $pickupRequestId = null)
    {
        return DB::transaction(function () use ($data, $shipmentIds, $assignStatus, $pickupRequestId) {
            $taskData = [
                'merchant_id' => $data['merchant_id'],
                'driver_id' => $data['driver_id'],
                'no_of_shipments' => $data['no_of_shipments'],
                'note' => $data['note'] ?? null,
                'status' => $data['status'] ?? MerchantPickupTaskStatusEnum::PENDING,
            ];
            if (!empty($pickupRequestId)) {
                $taskData['pickup_request_id'] = $pickupRequestId;
            }
            $task = MerchantPickupTask::create($taskData);
            // Find merchant user_id by merchant model id (merchant_id)
            $merchant = \App\Models\Merchant::with('user')->find($data['merchant_id']);
            $shipments = collect();
            if ($merchant && $merchant->user) {
                $shipments = $merchant->user
                    ->createdShipments()
                    ->where('status', 'CREATED')
                    ->select(['id', 'tracking_no', 'status', 'merchant_id'])
                    ->get();
            }
            foreach ($shipments as $shipment) {
                MerchantPickupShipment::create([
                    "pickup_task_id" => $task->id,
                    "pickup_request_id" => $pickupRequestId,
                    "merchant_id" => $data['merchant_id'],
                    "driver_id" => $data['driver_id'],
                    "shipment_tracking_no" => $shipment->tracking_no,
                    "shipment_id" => $shipment->id,
                    "pre_id" => $shipment->pre_id,
                ]);
                $shipment->update([
                    'status' => $assignStatus,
                ]);
                shipmentHistory([
                    "status" => status($assignStatus)['label'] ?? $assignStatus,
                    "description" => status($assignStatus)['description'] ?? $assignStatus,
                    "shipment_id" => $shipment->id,
                ]);

                if ($pickupRequestId) {
                    $shipment->pickup_request_id = $pickupRequestId;
                    $shipment->save();
                }
            }
            return $task;
        });
    }
    public function updateMerchantTasks(int $merchantId): void
    {
        try {
            // 1. Calculate the current number of active pickup tasks (TO_PICKUP)
            $tasksQuery = MerchantPickupTask::where('merchant_id', $merchantId)
                ->where('status', MerchantPickupTaskStatusEnum::TO_PICKUP);

            $tasksCount = $tasksQuery->count();

            // 2. Get the latest assigned task details
            $latestTask = $tasksQuery->latest()->first();

            // 3. Send FCM Notification
            $this->sendFcmNotification($merchantId, $tasksCount, $latestTask);
        } catch (\Throwable $e) {
            Log::error("Failed to send FCM for merchant {$merchantId}: " . $e->getMessage());
            // We do NOT re-throw, so we don't block the API response if FCM fails.
        }
    }

    public function sendFcmNotification(int $merchantId, int $tasksCount, $latestTask = null): void
    {
        try {
            $user = \App\Models\User::with('deviceTokens')->find($merchantId);

            if (!$user || $user->deviceTokens->isEmpty()) {
                Log::warning("No device tokens found for merchant {$merchantId}, skipping FCM.");
                return;
            }

            // Prepare data payload
            $data = [
                'type' => 'pickup_task_update',
                'tasks_count' => (string) $tasksCount,
                'merchant_id' => (string) $merchantId,
                'timestamp' => (string) now()->timestamp,
            ];

            // Add latest task info if available
            if ($latestTask) {
                $data['latest_task_id'] = (string) $latestTask->id;
                $data['merchant_id'] = (string) $latestTask->merchant_id;
                $data['no_of_shipments'] = (string) $latestTask->no_of_shipments;
            }

            // Create notification for display when app is in background
            $title = 'مهمة استلام جديدة';
            $body = $tasksCount > 1
                ? "لديك {$tasksCount} مهام استلام نشطة"
                : "تم انشاء مهمة استلام جديدة لك";

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            // Send to all merchant devices
            $tokens = $user->deviceTokens->pluck('token')->toArray();

            if (!empty($tokens)) {
                $report = $this->messaging->sendMulticast($message, $tokens);
                Log::info("FCM sent to merchant {$merchantId}. Success: {$report->successes()->count()}, Failures: {$report->failures()->count()}", [
                    'tasks_count' => $tasksCount,
                    'data' => $data,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send FCM to merchant {$merchantId}: " . $e->getMessage());
        }
    }
}
