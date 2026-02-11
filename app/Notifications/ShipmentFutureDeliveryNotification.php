<?php
namespace App\Notifications;

use App\Models\Shipment;
use App\Models\WhatsAppTemplate;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShipmentFutureDeliveryNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Shipment $shipment,
        protected Carbon $futureUtc
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsapp(object $notifiable)
    {
        $shipment = $this->shipment;
        $template = WhatsAppTemplate::where('name', 'FUTURE_DELIVERY')->first();

        $tz = $shipment->consignee->timezone ?? 'Asia/Muscat';

        $futureLocal = $this->futureUtc->copy()->setTimezone($tz);
        \Carbon\Carbon::setLocale('en');
        $futureDay = $futureLocal->format('l');
        $futureDate = $futureLocal->format('d F Y');

        $marketingUrl = rtrim(config('app.marketing_url'), '/');

        $message = $template
            ? str_replace(
                ['{{receiver_name}}', '{{tracking_number}}', '{{future_day}}', '{{future_date}}', '{{shipper_name}}', '{{marketing_url}}'],
                [
                    $shipment->consignee?->name ?? '',
                    $shipment->tracking_no,
                    $futureDay,
                    $futureDate,
                    $shipment->shipper?->name ?? '',
                    $marketingUrl,
                ],
                $template->message
            )
            : "Hello {$shipment->consignee->name}, your delivery is scheduled on {$futureDay}, {$futureDate}. Track: {$marketingUrl}/tracking/{$shipment->tracking_no}";

        $recipients = array_filter([
            $shipment->consignee?->country_key_cellphone . $shipment->consignee?->cellphone,
            $shipment->consignee?->country_key_alternatePhone . $shipment->consignee?->alternatePhone,
        ]);

        $data = [
            'message' => $message,
            'recipients' => $recipients,
            'meta' => [
                'future_utc' => $this->futureUtc->toIso8601String(),
                'future_local' => $futureLocal->toIso8601String(),
                'timezone' => $tz,
            ]
        ];

        info('Future Delivery WhatsApp Message', $data);

        return $data;
    }
}
