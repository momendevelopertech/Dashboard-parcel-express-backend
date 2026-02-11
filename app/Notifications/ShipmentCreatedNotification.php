<?php

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\WhatsAppTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class ShipmentCreatedNotification extends Notification
{
    use Queueable;

    protected Shipment $shipment;

    /**
     * Create a new notification instance.
     */
    private function sanitizeForWhatsapp(string $text): string
    {
        return preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', trim($text));
    }
    public function __construct(
        Shipment $shipment,
        public string $addressUpdateUrl,
        public string $otp
    ) {
        info("inside created consignee notification");
        $this->shipment = $shipment;
        // Let the database auto-increment the primary key instead of using UUID strings
        $this->id = null;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    // public function toArray(object $notifiable): array
    // {
    //     $shipment = $this->shipment;
    //     info("inside created consignee notification to database");
    //     return [
    //         'type' => 'shipment_created',
    //         'title' => "Shipment #{$shipment->tracking_no} Created",
    //         'body' => "Your shipment with tracking number {$shipment->tracking_no} has been created successfully.",
    //         'notifiable_id' => $shipment->consignee_id,
    //         'notifiable_type' => 'App\\Models\\Consignee',
    //         'data' => [
    //             'shipment_id' => $shipment->id,
    //             'tracking_number' => $shipment->tracking_no,
    //             'consignee_name' => $shipment->consignee->name,
    //             'amount' => $shipment->amount,
    //             'address_update_url' => $shipment->consignee->address_update_url,
    //         ],
    //     ];
    // }

    /**
     * Get the mail representation of the notification.
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     $shipment = $this->shipment;
    //     $template = WhatsAppTemplate::where('name', 'ORDER_CREATED')->first();
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

    //     $marketingUrl = env("MARKETING_APP_URL", "https://parcelexpress.om");
    //     if ($template) {
    //         $message = str_replace(
    //             ['{{receiver_name}}', '{{tracking_number}}', '{{merchant_name}}', '{{cod}}', '{{address_update_url}}', '{{marketing_url}}'],
    //             [
    //                 $shipment->consignee->name,
    //                 $shipment->tracking_no,
    //                 $senderInfo,
    //                 $shipment->amount,
    //                 $shipment->consignee->address_update_url ?? '',
    //                 $marketingUrl,
    //             ],
    //             $template->message
    //         );
    //     } else {
    //         $message = "Hello {$shipment->consignee->name}, your shipment #{$shipment->tracking_no} has been created. Total amount: {$shipment->amount}. Track it here: " . $marketingUrl . "/tracking/" . $shipment->tracking_no;
    //     }

    //     $htmlMessage = $this->convertMarkdownToHtml($message);

    //     return (new MailMessage)
    //         ->from('itparcelexpress@gmail.com', 'Parcel Express')
    //         ->subject("Your Shipment #{$shipment->tracking_no} has been created")
    //         ->greeting("Hello {$shipment->consignee->name},")
    //         ->line(new HtmlString($htmlMessage));
    // }

    /**
     * Build the WhatsApp message for shipment creation.
     */
    public function toWhatsapp(object $notifiable)
    {
        $shipment = $this->shipment;
        $template = WhatsAppTemplate::where('name', 'ORDER_CREATED')->first();
        info("inside created consignee notification to whatsapp");

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
        $url = $this->sanitizeForWhatsapp($this->addressUpdateUrl);
        if ($template) {
            // $message = str_replace(
            //     // 7 placeholders بالترتيب ده
            //     ['{{receiver_name}}', '{{tracking_number}}', '{{merchant_name}}', '{{cod}}', '{{address_update_url}}', '{{otp}}'],
            //     [
            //         $shipment->consignee->name,
            //         $shipment->tracking_no,
            //         $senderInfo,
            //         $shipment->amount,
            //         "\n{$url}\n",
            //         rtrim(config('app.marketing_url'), '/'),
            //         $this->otp,
            //     ],
            //     $template->message

            $message = str_replace(
                ['{{receiver_name}}', '{{tracking_number}}', '{{merchant_name}}', '{{cod}}', '{{address_update_url}}', '{{marketing_url}}', '{{otp}}'],
                [
                    $shipment->consignee->name,
                    $shipment->tracking_no,
                    $senderInfo,
                    $shipment->amount,
                    "\n{$url}\n",
                    rtrim(config('app.marketing_url'), '/'),
                    $this->otp
                ],
                $template->message
            );
        } else {
            $url = $this->addressUpdateUrl ?? ($shipment->address_update_url ?? url('/tracking/' . $shipment->tracking_no));
            // $otp = $this->otp ?? ($shipment->address_update_otp ?? '');
            // $message = "Hi {$shipment->consignee->name}, track: {$url} / OTP: {$this->otp}";
            // $message = "Hello {$shipment->consignee->name}, your shipment #{$shipment->tracking_no} has been created. Total amount: {$shipment->amount}. Track/Update: {$url}" . ($otp ? " | OTP: {$otp}" : "");
        }
        $recipients = array_filter([
            $shipment->consignee->country_key_cellphone . $shipment->consignee->cellphone,
            $shipment->consignee->country_key_alternatePhone . $shipment->consignee->alternatePhone,
        ]);
        $data = [
            'message' => $message,
            'recipients' => $recipients,
        ];
        info('Shipment Created WhatsApp Message', $data);
        return $data;
    }

    /**
     * Convert markdown-style formatting to HTML using professional markdown parser
     */
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
