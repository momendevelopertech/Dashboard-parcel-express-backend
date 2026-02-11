<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsAppService;

class WhatsAppTestCommand extends Command
{
    protected $signature = 'whatsapp:test';
    protected $description = 'Send a test WhatsApp message using WhatsAppService directly (with fixed number and message)';
    protected WhatsAppService $whatsApp;
    public function __construct(WhatsAppService $whatsApp)
    {
        parent::__construct();
        $this->whatsApp = $whatsApp;
    }

    public function handle(): int
    {
        $number = '96898906703';
        $message = <<<EOT
                📦 *Delivery Notification*

                👤 Dear *Ahmed Al-Sayed*,

                *Shipment Details:*
                - 📦 Tracking Number: *PEX-2025-123456*
                - 📦 OTP: *456733*
                - 👤 Sender: *Mohammed Al-Hassan*
                - 💰 Payment: *COD (150MR)* - Cash Only

                *Delivery Instructions:*
                📍 Please share your location or national address short code.
                🔔 We'll contact you before arrival.

                *Customer Support:*
                📞 For any questions or issues, contact us at:
                *968 22711502*

                *Thank you for choosing Parcel Express!*
                EOT;
        $this->info("Verifying number: {$number}...");

        if (! $this->whatsApp->isWhatsAppNumber($number)) {
            $this->error("❌ Number $number is not valid on WhatsApp.");
            return Command::FAILURE;
        }
        $this->info("✅ Valid number! Sending message...");
        $success = $this->whatsApp->sendMessage($number, $message);
        if ($success) {
            $this->info("✅ Message sent successfully to {$number}");
            return Command::SUCCESS;
        }
        $this->error("❌ Failed to send message to {$number}");
        return Command::FAILURE;
    }
}
