<?php

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\WhatsAppTemplate;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShipmentDeliverLaterTodayNotification extends Notification
{
    use Queueable;

    protected Shipment $shipment;
    protected Carbon $deferUntil;

    public function __construct(Shipment $shipment, Carbon $deferUntil)
    {
        $this->shipment = $shipment;
        $this->deferUntil = $deferUntil;
        $this->id = null;
    }

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }


    public function toWhatsapp(object $notifiable)
    {
        $shipment = $this->shipment;
        $template = WhatsAppTemplate::where('name', 'DELIVER_LATER_TODAY')->first();

        $tz = $shipment->consignee->timezone ?? 'Asia/Muscat';

        $deferLocal = $this->deferUntil->copy()->setTimezone($tz);


        \Carbon\Carbon::setLocale('ar');
        $deferTimeStr = $deferLocal->translatedFormat('l d F Y، g:i A'); // تقدر تغيّر الفورمات حسب ذوقك

        $marketingUrl = rtrim(config('app.marketing_url'), '/');

        $message = $template
            ? str_replace(
                ['{{receiver_name}}', '{{tracking_number}}', '{{defer_time}}', '{{shipper_name}}', '{{marketing_url}}'],
                [
                    $shipment->consignee?->name ?? '',
                    $shipment->tracking_no,
                    $deferTimeStr,
                    $shipment->shipper?->name ?? '',
                    $marketingUrl,
                ],
                $template->message
            )
            : "مرحباً {$shipment->consignee->name}، بناءً على طلبك سنعيد محاولة تسليم شحنتك رقم {$shipment->tracking_no} حوالي الساعة {$deferTimeStr}. تتبّع طلبك: {$marketingUrl}/tracking/{$shipment->tracking_no}";

        $recipients = array_filter([
            $shipment->consignee?->country_key_cellphone . $shipment->consignee?->cellphone,
            $shipment->consignee?->country_key_alternatePhone . $shipment->consignee?->alternatePhone,
        ]);

        $data = [
            'message' => $message,
            'recipients' => $recipients,
            'meta' => [
                'utc' => $this->deferUntil->toIso8601String(),
                'local' => $deferLocal->toIso8601String(),
                'timezone' => $tz,
            ]
        ];

        info('Deliver Later Today WhatsApp Message', $data);

        return $data;
    }
}
