<?php

namespace App\Notifications;



use App\Models\Consignee;
use App\Models\Shipment;
use App\Models\WhatsAppTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class ConsigneeOFDNotification extends Notification implements ShouldQueue
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
        // Send via WhatsApp, email, and also store in the database
        $channels = [
            'database',
            // 'mail',
            'whatsapp'
        ];

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        $o = $this->shipment;

        return [
            'type' => 'ofd_notification',
            'title' => "📦  Shipment #{$o->tracking_no} - Future Delivery",
            'content' => "Shipment #{$o->tracking_no}\n" .
                "📅 Future Delivery Scheduled\n" .
                "📍 We'll deliver to: {$o->consignee->address}",
            'shipment_id' => $o->id,
            'tracking_no' => $o->tracking_no,
            'consignee' => [
                'id' => $o->consignee_id,
                'name' => $o->consignee->name,
                'phone' => $o->consignee->cellphone,
                'alt' => $o->consignee->alternatePhone,
                'address' => $o->consignee->address,
            ],
            'otp' => $o->shipment_delivery->delivery_otp,
            'amount' => $o->amount,
            'address_update_url' => $o->consignee->address_update_url,
        ];
    }

    public function toArray(object $notifiable): array
    {
        $shipment = $this->shipment;

        $title = "Shipment #{$shipment->tracking_no} Out for Delivery";

        $data = [
            'type' => 'ofd_notification',
            'title' => $title,
            'body' => "Your shipment with tracking number {$shipment->tracking_no} is out for delivery and on its way to you. Please provide the OTP to the delivery agent.",
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
                'otp' => $shipment->shipment_delivery->delivery_otp,
                'amount' => $shipment->amount,
                'address_update_url' => $shipment->consignee->address_update_url,
            ]
        ];
        info("Message", ["" => $data]);
        return $data;
    }

    /**
     * Get the mail representation of the notification.
     */
    /**
     * Get the mail representation of the notification.
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     $shipment = $this->shipment;
    //     $template = WhatsAppTemplate::where('name', 'SORT_OFD')->first();
    //     info("inside created consignee notification to mail");

    //     $merchantName = optional($shipment->merchant)->name;
    //     $shipperName = optional($shipment->shipper)->name;

    //     if ($merchantName && $shipperName) {
    //         $senderInfo = "Merchant: $merchantName, Shipper: $shipperName";
    //     } elseif ($merchantName) {
    //         $senderInfo = "Merchant: $merchantName";
    //     } elseif ($shipperName) {
    //         $senderInfo = "Shipper: $shipperName";
    //     } else {
    //         $senderInfo = 'N/A';
    //     }

    //     if ($template) {
    //         $message = str_replace(
    //             ['{{receiver_name}}', '{{tracking_number}}', '{{otp}}', '{{merchant_name}}', '{{cod}}', '{{address_update_url}}'],
    //             [
    //                 $shipment->consignee->name,
    //                 $shipment->tracking_no,
    //                 $shipment->shipment_delivery->delivery_otp,
    //                 $senderInfo,
    //                 $shipment->amount,
    //                 $shipment->consignee->address_update_url ?? '',
    //             ],
    //             $template->message
    //         );
    //     } else {
    //         $message = "Hello {$shipment->consignee->name}, your shipment #{$shipment->tracking_no} is out for delivery. OTP: {$shipment->shipment_delivery->delivery_otp}. Total amount: {$shipment->amount}.";
    //     }

    //     $htmlMessage = $this->convertMarkdownToHtml($message);

    //     return (new MailMessage)
    //         ->from('itparcelexpress@gmail.com', 'Parcel Express')
    //         ->subject("Your Shipment #{$shipment->tracking_no} is Out for Delivery")
    //         ->greeting("Hello {$shipment->consignee->name},")
    //         ->line(new HtmlString($htmlMessage));
    // }

    /**
     * Build the WhatsApp message text for shipment information only.
     */
    public function toWhatsapp($notifiable)
    {
        $shipment = $this->shipment;
        $template = WhatsAppTemplate::where('name', 'SORT_OFD')->first();
        $merchantName = optional($shipment->merchant)->name;
        $shipperName = optional($shipment->shipper)->name;

        if ($merchantName && $shipperName) {
            $senderInfo = "Merchant: $merchantName, Shipper: $shipperName";
        } elseif ($merchantName) {
            $senderInfo = "Merchant: $merchantName";
        } elseif ($shipperName) {
            $senderInfo = "Shipper: $shipperName";
        } else {
            $senderInfo = 'N/A';
        }

        if ($template) {
            $message = str_replace(
                ['{{receiver_name}}', '{{tracking_number}}', '{{otp}}', '{{merchant_name}}', '{{cod}}', '{{address_update_url}}'],
                [
                    $shipment->consignee->name,
                    $shipment->tracking_no,
                    $shipment->shipment_delivery->delivery_otp,
                    $senderInfo,
                    $shipment->amount,
                    $shipment->consignee->address_update_url ?? ''
                ],
                $template->message
            );
        } else {
            $message = "Shipment {$shipment->tracking_no} has been dispatched successfully and is on its way. Please provide the OTP to the delivery agent.";
        }

        $recipients = array_filter([
            $shipment->consignee->country_key_cellphone . $shipment->consignee->cellphone,
            $shipment->consignee->country_key_alternatePhone . $shipment->consignee->alternatePhone,
        ]);

        $data = [
            'message' => $message,
            'recipients' => $recipients,
        ];

        info("OFD WhatsApp Message", $data);
        return $data;
    }

    private function convertMarkdownToHtml(string $text): string
    {
        // Convert WhatsApp template format to standard markdown
        $markdown = $this->convertWhatsAppTemplateToMarkdown($text);

        // Use the professional markdown converter
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = $converter->convert($markdown);

        // Add email-specific styling
        $html = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">' . $html . '</div>';

        return $html;
    }

    private function convertWhatsAppTemplateToMarkdown(string $text): string
    {
        // Convert WhatsApp template format to standard markdown
        $markdown = $text;

        // Convert bullet points (•) to markdown list items (-)
        $markdown = preg_replace('/^•\s+/m', '- ', $markdown);

        // Convert URLs to markdown links (if they're not already in markdown format)
        $markdown = preg_replace_callback(
            '/(?<!\[)(https?:\/\/[^\s<>"\'\)]+)(?!\])/',
            function ($matches) {
                $url = $matches[1];
                return '[' . $url . '](' . $url . ')';
            },
            $markdown
        );

        return $markdown;
    }
}
