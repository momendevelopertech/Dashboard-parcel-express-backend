<?php

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\WhatsAppTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class DriverNearbyNotification extends Notification
{
    use Queueable;

    protected Shipment $shipment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Shipment $shipment)
    {
        $this->shipment = $shipment;

        // Allow Laravel to let the database auto-increment the primary key instead of using UUID strings
        $this->id = null;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [
            'database',
            'whatsapp'
        ];
        
        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        $shipment = $this->shipment;

        $title = "Driver Nearby - Shipment #{$shipment->tracking_no}";

        $data = [
            'type' => 'driver_nearby_notification',
            'title' => $title,
            'body' => "Our delivery driver is very close and will arrive at your location in approximately 15 minutes for shipment {$shipment->tracking_no}.",
            'notifiable_id' => $shipment->consignee_id,
            'notifiable_type' => 'App\Models\Consignee',
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->tracking_no,
                'consignee_id' => $shipment->consignee_id,
                'consignee_name' => $shipment->consignee->name,
                'consignee_cellphone' => $shipment->consignee->cellphone,
                'consignee_alternate_phone' => $shipment->consignee->alternatePhone,
                'consignee_address' => $shipment->consignee->address,
                'amount' => $shipment->amount,
            ]
        ];
        info("Message", ["" => $data]);
        return $data;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $shipment = $this->shipment;
        $consignee = $shipment->consignee;

        $senderInfo = [];


        $tableRows = [];
        if (!empty($senderInfo)) {
            $tableRows[] = "<tr><td style='padding: 5px;'>" . implode('</td></tr><tr><td style="padding: 5px;">', $senderInfo) . "</td></tr>";
        }
        $tableRows[] = "<tr><td style='padding: 5px;'><strong>Amount to Collect:</strong></td><td style='padding: 5px;'>{$formattedAmount}</td></tr>";

        $tableHtml = "<table style='width: 100%'>" . implode('', $tableRows) . "</table>";

        return (new MailMessage)
            ->subject("Driver Nearby - Shipment #{$shipment->tracking_no}")
            ->greeting("Hello {$consignee->name},")
            ->line("Good news! Our delivery driver is very close and will arrive at your location in approximately **15 minutes**.")
            ->line("**Shipment:** {$shipment->tracking_no}")
            ->line("Please be available to receive your package and have exact cash ready for COD.")
            ->line(new HtmlString("<hr>"))
            ->line(new HtmlString($tableHtml))
            ->action('Track Your Shipment', url('/tracking/' . $shipment->tracking_no))
            ->line('Thank you for choosing our service!');
    }

    /**
     * Build the WhatsApp message text for driver nearby notification.
     */
    public function toWhatsapp($notifiable)
    {
        $shipment = $this->shipment;
        $template = WhatsAppTemplate::where('name', 'DRIVER_NEARBY')->first();

        if ($template) {
            $message = str_replace(
                ['{{receiver_name}}'],
                [
                    $shipment->consignee->name,
                ],
                $template->message
            );
        } else {
            $message = "Dear {$shipment->consignee->name}, our delivery driver is very close and will arrive at your location in approximately 15 minutes. Please be available to receive your parcel.";
        }

        $recipients = array_filter([
            $shipment->consignee->cellphone,
            $shipment->consignee->alternatePhone,
        ]);

        $data = [
            'message' => $message,
            'recipients' => $recipients,
        ];

        return $data;
    }
}
