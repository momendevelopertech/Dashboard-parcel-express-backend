<?php

namespace App\Jobs;

use App\Traits\CustomeTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyFutureDeliveryDue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CustomeTrait;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('NotifyFutureDeliveryDue job started at: ' . now());



        $shipments = self::getFutureDeliveryDueToday();

        if ($shipments->isEmpty()) {
            Log::info('No future delivery shipments due today');
            return;
        }

        $notificationCount = 0;

        foreach ($shipments as $shipment) {
            if (!$shipment->owner_id || !$shipment->owner_type) {
                Log::warning("Shipment {$shipment->tracking_no} has no owner workspace");
                continue;
            }

            $shelf = $shipment->assigned_to_shelf?->barcode ?? 'No shelf';
            $deliveryDate = $shipment->shipment_delivery?->future_delivery_date;

            $title = '📦 Future Delivery Due Today';
            $content = "Shipment #{$shipment->tracking_no} is scheduled for delivery today.\n" .
                "📍 Address: {$shipment->consignee?->streetAddress}\n" .
                "📞 Phone: {$shipment->consignee?->cellphone}\n" .
                "🗄️ Shelf: {$shelf}\n" .
                "📅 Scheduled: {$deliveryDate}";

            $data = [
                'shipment_id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'consignee_name' => $shipment->consignee?->name,
                'consignee_phone' => $shipment->consignee?->cellphone,
                'delivery_date' => $deliveryDate,
            ];

            $count = notify_workspace_users(
                $shipment->owner_id,
                $shipment->owner_type,
                ['Shipment access'],
                $title,
                $content,
                $data,
                'future_delivery_due',
                false
            );

            $notificationCount += $count;
        }

        Log::info("NotifyFutureDeliveryDue job completed. Notifications sent: {$notificationCount}");
    }
}
