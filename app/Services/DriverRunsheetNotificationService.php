<?php

namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use App\Models\DriverShipmentAssignment;
use Illuminate\Support\Facades\Log;
use App\Models\DriverRunsheetShipment;
use App\Models\Hub;
use App\Models\Station;
use Carbon\Carbon;

class DriverRunsheetNotificationService
{
    protected $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Send FCM notification to driver with count of unconfirmed shipments.
     *
     * @param int $driverId
     * @return void
     */
    public function notifyDriverUnconfirmedShipments(int $driverId): void
    {
        try {
                            $user = \App\Models\User::find($driverId);
                        $timezone = $user->timezone();
           
                $startOfDay = Carbon::now($timezone)->startOfDay()->utc();
                $endOfDay   = Carbon::now($timezone)->endOfDay()->utc();
            // Count unconfirmed shipments (TO_CONFIRM status)
            $unconfirmedCount = DriverRunsheetShipment::where('driver_id', $driverId)
               ->where('status',"assigned")
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count();

            if ($unconfirmedCount === 0) {
                Log::info("No unconfirmed shipments for driver {$driverId}, skipping FCM.");
                return;
            }

            // Send FCM notification
            $this->sendFcmNotification($driverId, $unconfirmedCount);

        } catch (\Throwable $e) {
            Log::error("Failed to send runsheet FCM for driver {$driverId}: " . $e->getMessage());
        }
    }

    /**
     * Send FCM Data Message to the driver.
     *
     * @param int $driverId
     * @param int $unconfirmedCount
     * @return void
     */
    protected function sendFcmNotification(int $driverId, int $unconfirmedCount): void
    {
        try {
            $user = \App\Models\User::with('deviceTokens')->find($driverId);
            
            if (!$user || $user->deviceTokens->isEmpty()) {
                Log::warning("No device tokens found for driver {$driverId}, skipping FCM.");
                return;
            }

            // Prepare data payload
            $data = [
                'type' => 'runsheet_update',
                'unconfirmed_count' => (string) $unconfirmedCount,
                'driver_id' => (string) $driverId,
                'timestamp' => (string) now()->timestamp,
            ];

            // Create notification
            $title = 'شحنات جديدة للتأكيد';
            $body = $unconfirmedCount > 1 
                ? "لديك {$unconfirmedCount} شحنات بانتظار التأكيد" 
                : "لديك شحنة واحدة بانتظار التأكيد";

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            // Send to all driver devices
            $tokens = $user->deviceTokens->pluck('token')->toArray();
            
            if (!empty($tokens)) {
                $report = $this->messaging->sendMulticast($message, $tokens);
                Log::info("Runsheet FCM sent to driver {$driverId}. Success: {$report->successes()->count()}, Failures: {$report->failures()->count()}", [
                    'unconfirmed_count' => $unconfirmedCount,
                    'data' => $data,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error("Failed to send runsheet FCM to driver {$driverId}: " . $e->getMessage());
        }
    }
}
