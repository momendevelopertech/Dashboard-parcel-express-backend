<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $instanceId;
    protected $token;
    protected $baseUrl;

    public function __construct()
    {
        $this->instanceId = config('services.ultramsg.instance_id');
        $this->token = config('services.ultramsg.token');
        $this->baseUrl = "https://api.ultramsg.com/{$this->instanceId}";
    }

    public function sendMessage(string $to, string $message): bool
    {
        $logContext = [
            'to' => $to,
            'message' => $message,
            'instance_id' => $this->instanceId,
            'endpoint' => $this->baseUrl . '/messages/chat'
        ];

        try {
            Log::info('Sending WhatsApp message', $logContext);

            $payload = [
                'token' => $this->token,
                'to' => $to,
                'body' => $message,
            ];

            $response = Http::withOptions(['verify' => public_path('certs/cacert.pem')])
                ->timeout(30)
                ->asForm()
                ->post("{$this->baseUrl}/messages/chat", $payload);

            $responseData = $response->json() ?? [];
            $statusCode = $response->status();
            $isSuccessful = $response->successful();

            $logContext = array_merge($logContext, [
                'status_code' => $statusCode,
                'response' => $responseData,
                'success' => $isSuccessful
            ]);

            if (
                isset($responseData['sent']) && $responseData['sent'] === 'true' &&
                str_contains($responseData['message'] ?? '', 'will be sent after successful authentication.')
            ) {
                $admin = User::where('email', 'admin@gmail.com')->first();
                create_notification(
                    $admin,
                    '⚠️ WhatsApp Authentication Required',
                    'Your Ultramsg WhatsApp instance is not authenticated. Please complete the QR code authentication in your Ultramsg dashboard to enable message sending.',
                    [
                        'type' => 'whatsapp_auth',
                        'icon' => 'mdi-whatsapp',
                        'color' => 'error',
                        'action_url' => 'https://app.ultramsg.com/instance/console',
                        'action_text' => 'Go to Ultramsg Console'
                    ],
                    'whatsapp_auth_required',
                    false
                );
            }
            if ($isSuccessful) {
                Log::info('WhatsApp message sent successfully', $logContext);
            } else {
                Log::error('Failed to send WhatsApp message', $logContext);
            }

            return $isSuccessful;
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp message', array_merge($logContext, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            return false;
        }
    }

    public function isWhatsAppNumber(string $number): bool
    {


        $url = "{$this->baseUrl}/contacts/check?token={$this->token}&chatId={$number}&nocache=true";
        $response = Http::withOptions(['verify' => public_path('certs/cacert.pem')])->get($url);
        if ($response->successful()) {
            $data = $response->json();
            Log::info("WhatsApp check response for {$number}", [
                'response' => $data,
                'status_code' => $response->status()
            ]);
            if (isset($data['error']) && $data['error'] === 'daily Limit exceeded') {
                Log::warning("Daily limit exceeded while checking WhatsApp number {$number}. Proceeding anyway.");
                return true;
            }
            if (isset($data['status'])) {
                return $data['status'] === 'valid';
            }
        }
        Log::warning("WhatsApp check failed for {$number}", [
            'status_code' => $response->status(),
            'body' => $response->body()
        ]);
        return false;
    }

    public function sendImage(string $to, string $imageUrl, string $caption = ''): bool
    {
        $response = Http::withOptions(['verify' => public_path('certs/cacert.pem')])
            ->asForm()
            ->post("{$this->baseUrl}/messages/image", [
                'token' => $this->token,
                'to' => $to,
                'image' => $imageUrl,
                // 'image' => "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRuIy6HNc3zXzJ9-y-rNEfnaSdhcgeXytmnQg&s",
                'caption' => $caption,
            ]);
        Log::info("Response status: {$response->status()}");
        Log::info("Response body: " . $response->body());
        if (!$response->successful()) {
            Log::warning("Failed to send WhatsApp image to $to", [
                'status_code' => $response->status(),
                'body' => $response->body(),
            ]);
        }
        return $response->successful();
    }
}
