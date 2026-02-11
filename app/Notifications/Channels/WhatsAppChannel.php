<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    protected WhatsAppService $whatsApp;

    public function __construct(WhatsAppService $whatsApp)
    {
        $this->whatsApp = $whatsApp;
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWhatsapp')) {
            return;
        }
        $data = $notification->toWhatsapp($notifiable);
        $message = $data['message'] ?? '';
        $recipients = $data['recipients'] ?? [];
        $imageUrl = $data['image'] ?? null;

        // Log complete markdown message exactly as it will be sent
        Log::info('WhatsApp Message - Final Formatted Message', [
            'notification_class' => get_class($notification),
            'notifiable_id' => $notifiable->id ?? 'N/A',
            'recipients' => $recipients,
            'message_to_be_sent' => $message,
            'message_preview' => "=== WhatsApp Message Preview ===\n" . $message . "\n=== End of Message ===",
            'has_image' => !empty($imageUrl),
            'image_url' => $imageUrl
        ]);

        if ((empty($message) && empty($imageUrl)) || empty($recipients)) {
            return;
        }
        try {
            $sent = false;
            foreach ($recipients as $to) {
                if (!$this->whatsApp->isWhatsAppNumber($to)) {
                    Log::info("Recipient $to is not a WhatsApp number.");
                    continue;
                }
                if ($imageUrl) {
                    if ($this->whatsApp->sendImage($to, $imageUrl, $message)) {
                        Log::info("WhatsApp image sent successfully to $to.");
                        $sent = true;
                        break;
                    }
                    Log::warning("Failed to send WhatsApp image to $to.");
                } else {
                    if ($this->whatsApp->sendMessage($to, $message)) {
                        Log::info("WhatsApp message sent successfully to $to.");
                        $sent = true;
                        break;
                    }
                    Log::warning("Failed to send WhatsApp message to $to.");
                }
            }
            if (!$sent) {
                Log::warning("No valid WhatsApp recipients found.");
            }
        } catch (\Exception $e) {
            Log::error("WhatsApp message sending failed: " . $e->getMessage());
        }
    }
}

// {
//     protected WhatsAppService $whatsApp;

//     public function __construct(WhatsAppService $whatsApp)
//     {
//         $this->whatsApp = $whatsApp;
//     }

//     /**
//      * Send the given notification.
//      */
//     public function send($notifiable, Notification $notification): void
//     {
//         if (! method_exists($notification, 'toWhatsapp')) {
//             return;
//         }

//         // Build the message payload
//         $message = $notification->toWhatsapp($notifiable);
//         $to      = $notifiable->phone;

//         // Only send if both number and message are present
//         if ($to && $message) {
//             $this->whatsApp->sendMessage($to, $message);
//         }
//     }
// }