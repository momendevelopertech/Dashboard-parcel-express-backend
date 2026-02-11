<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use App\Models\MerchantPickupTask;
use App\Enums\MerchantPickupTaskStatusEnum;
use Illuminate\Support\Facades\Log;

class DriverRealtimePickupTaskService
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Send FCM notification to driver when task count changes.
     *
     * @param int $driverId
     * @return void
     */
    public function updateDriverTasks(int $driverId): void
    {
        try {
            // 1. Calculate the current number of active pickup tasks (TO_PICKUP)
            $tasksQuery = MerchantPickupTask::where('driver_id', $driverId)
                ->where('status', MerchantPickupTaskStatusEnum::TO_PICKUP);

            $tasksCount = $tasksQuery->count();

            // 2. Get the latest assigned task details
            $latestTask = $tasksQuery->latest()->first();

            // 3. Send FCM Notification
            $this->sendFcmNotification($driverId, $tasksCount, $latestTask);
        } catch (\Throwable $e) {
            Log::error("Failed to send FCM for driver {$driverId}: " . $e->getMessage());
            // We do NOT re-throw, so we don't block the API response if FCM fails.
        }
    }

    /**
     * Send FCM Data Message to the driver.
     *
     * @param int $driverId
     * @param int $tasksCount
     * @param \App\Models\MerchantPickupTask|null $latestTask
     * @return void
     */
    public function sendFcmNotification(int $driverId, int $tasksCount, $latestTask = null): void
    {
        try {
            $user = \App\Models\User::with('deviceTokens')->find($driverId);

            if (!$user || $user->deviceTokens->isEmpty()) {
                Log::warning("No device tokens found for driver {$driverId}, skipping FCM.");
                return;
            }

            // Prepare data payload
            $data = [
                'type' => 'pickup_task_update',
                'tasks_count' => (string) $tasksCount,
                'driver_id' => (string) $driverId,
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
                : "تم تعيين مهمة استلام جديدة لك";

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            // Send to all driver devices
            $tokens = $user->deviceTokens->pluck('token')->toArray();

            if (!empty($tokens)) {
                $report = $this->messaging->sendMulticast($message, $tokens);
                Log::info("FCM sent to driver {$driverId}. Success: {$report->successes()->count()}, Failures: {$report->failures()->count()}", [
                    'tasks_count' => $tasksCount,
                    'data' => $data,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send FCM to driver {$driverId}: " . $e->getMessage());
        }
    }
}
