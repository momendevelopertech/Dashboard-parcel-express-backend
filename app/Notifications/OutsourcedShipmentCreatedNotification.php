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

class OutsourcedShipmentCreatedNotification extends Notification
{
    use Queueable;

    // protected Shipment $shipment;

    /**
     * Create a new notification instance.
     */
    private function sanitizeForWhatsapp(string $text): string
    {
        return preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', trim($text));
    }
    public function __construct(
        public $shipment,
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


    public function toWhatsapp(object $notifiable)
    {
        $shipment = $this->shipment;
        $template = WhatsAppTemplate::where('name', 'ORDER_CREATED_Outsourced')->first();

        $senderInfo = optional($shipment->shipper)->name
            ? "Shipper: {$shipment->shipper->name}"
            : (optional($shipment->merchant)->name ? "Merchant: {$shipment->merchant->name}" : 'N/A');

        $url = $this->sanitizeForWhatsapp($this->addressUpdateUrl);

        if ($template) {
            $message = str_replace(
                ['{{receiver_name}}', '{{tracking_number}}', '{{merchant_name}}', '{{cod}}', '{{address_update_url}}', '{{otp}}'],
                [$shipment->consignee->name, $shipment->tracking_no, $senderInfo, $shipment->amount, "\n{$url}\n", $this->otp],
                $template->message
            );  
        } else {
            $message = "Hi {$shipment->consignee->name}, track: {$url} / OTP: {$this->otp}";
        }

        return [
            'message' => $message,
            'recipients' => array_filter([
                ($shipment->consignee->country_key_cellphone . $shipment->consignee->cellphone),
                ($shipment->consignee->country_key_alternatePhone . $shipment->consignee->alternatePhone),
            ]),
        ];
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
