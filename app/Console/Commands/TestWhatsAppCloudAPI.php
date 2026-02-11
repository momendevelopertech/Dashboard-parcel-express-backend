<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsAppService;

class TestWhatsAppCloudAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:test2 {phone : Phone number to test} {--message= : Custom message to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WhatsApp Cloud API integration';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsAppService)
    {
        $phone = $this->argument('phone');
        $message = $this->option('message') ?? 'Hello! This is a test message from Parcel Express WhatsApp Cloud API integration.';

        $this->info('Testing WhatsApp Cloud API Integration...');
        $this->line('');

        // Check configuration
        $this->info('1. Checking configuration...');
        $configStatus = $whatsAppService->getConfigurationStatus();
        
        foreach ($configStatus as $key => $value) {
            $status = is_bool($value) ? ($value ? '✓' : '✗') : $value;
            $this->line("   {$key}: {$status}");
        }

        if (!$configStatus['configured']) {
            $this->error('WhatsApp Cloud API is not properly configured!');
            $this->line('');
            $this->line('Please set the following environment variables:');
            $this->line('  - WHATSAPP_CLOUD_ACCESS_TOKEN');
            $this->line('  - WHATSAPP_PHONE_NUMBER_ID');
            $this->line('  - WHATSAPP_BUSINESS_ACCOUNT_ID');
            return 1;
        }

        $this->line('');
        $this->info('2. Sending test message...');
        $this->line("   To: {$phone}");
        $this->line("   Message: {$message}");
        $this->line('');

        // Send test message
        $result = $whatsAppService->sendMessage($phone, $message);

        if ($result) {
            $this->info('✓ Test message sent successfully!');
            $this->line('');
            $this->info('WhatsApp Cloud API integration is working correctly.');
        } else {
            $this->error('✗ Failed to send test message!');
            $this->line('');
            $this->warn('Please check your logs for more details.');
            return 1;
        }

        return 0;
    }
} 