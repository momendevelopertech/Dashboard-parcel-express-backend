<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCommand extends Command
{
    protected $signature = 'app:test';
    protected $description = 'Just for Testing';

    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        parent::__construct();
        $this->whatsappService = $whatsappService;
    }

    public function handle()
    {
        $this->info('Starting WhatsApp message test...');
        
        $phoneNumber = '+963983843933'; 
        $message = 'Hello from Laravel Test Command!';
        
        try {
            $this->info('Sending text message...');
            $result = $this->whatsappService->sendMessage($phoneNumber, $message);
            if ($result) {
                $this->info('✅ Text message sent successfully!');
                
                // Optional: Test sending an image
                if ($this->confirm('Would you like to test sending an image?')) {
                    $imageUrl = 'https://example.com/image.jpg'; // Replace with your image URL
                    $caption = 'Test Image';
                    
                    $this->info('Sending image...');
                    $imageResult = $this->whatsappService->sendImage($phoneNumber, $imageUrl, $caption);
                    
                    if ($imageResult) {
                        $this->info('✅ Image sent successfully!');
                    } else {
                        $this->error('❌ Failed to send image');
                    }
                }
            } else {
                $this->error('❌ Failed to send text message');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ An error occurred: ' . $e->getMessage());
            Log::error('UltraMSG Test Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}