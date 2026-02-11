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


class MerchantPickupRequestReverseService
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


    public function sendFcmNotification($reverseRequest): void
    {
        try {
            $merchantId=$reverseRequest->merchant_id;
            $user = \App\Models\User::with('deviceTokens')->find($merchantId);

            if (!$user || $user->deviceTokens->isEmpty()) {
                Log::warning("No device tokens found for merchant {$merchantId}, skipping FCM.");
                return;
            }

            // Prepare data payload
            $data = [
                'type' => 'pickup_reverse_received_at_hub',
                'merchant_id' => (string) $merchantId,
                'timestamp' => (string) now()->timestamp,
            ];


            // Create notification for display when app is in background
            $title = 'تم ارجاع شحناتك بنجاح.';
            $body = "الشحنات المرتجعة جاهزة للاستلام يرجي تحديد طريقة الاستلام.";

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            // Send to all merchant devices
            $tokens = $user->deviceTokens->pluck('token')->toArray();

            if (!empty($tokens)) {
                $report = $this->messaging->sendMulticast($message, $tokens);
                Log::info("FCM sent to merchant {$merchantId}. Success: {$report->successes()->count()}, Failures: {$report->failures()->count()}", [
                    'data' => $data,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send FCM to merchant {$merchantId}: " . $e->getMessage());
        }
    }
}
