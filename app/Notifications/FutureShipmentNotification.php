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

class FutureShipmentNotification extends Notification
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
        Shipment $shipment
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
        return ['database'];
    }
   public function toDatabase(object $notifiable): array
    {
        $o = $this->shipment;
        $delivery_date = $o->shipment_delivery->future_delivery_date;
        $shelf = $o?->shelf?->tracking_no ?? 'no shelf';
        return [
            'type' => 'ofd_notification',
            'title' => "📦  Shipment #{$o->tracking_no} - Future Delivery",
            'content' => "Shipment #{$o->tracking_no}\n" .
                "📅 Future Delivery Scheduled\n" .
                "shelf number {$shelf}\n".
                "📍 We'll deliver to: {$o->consignee->address}".
                "Deliver Date: {$delivery_date}",
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
