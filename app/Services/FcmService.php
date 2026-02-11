<?php
// app/Services/FcmService.php
namespace App\Services;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    public function __construct(private Messaging $messaging)
    {
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): void
    {
        $data = array_map('strval', $data);
        $message = CloudMessage::fromArray([
            'topic' => $topic,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => $data,
        ]);
        $this->messaging->send($message);
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($tokens))
            return;
        $data = array_map('strval', $data);
        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        foreach (array_chunk($tokens, 500) as $chunk) {
            $this->messaging->sendMulticast($message, $chunk);
        }
    }

    public function subscribeTokenToDriverTopic(int $driverId, string $token): void
    {
        $this->messaging->subscribeToTopic("driver_{$driverId}", [$token]);
    }
    public function subscribeTokenToMerchantTopic(int $merchantId, string $token): void
    {
        $this->messaging->subscribeToTopic("merchant_{$merchantId}", [$token]);
    }

    public function unsubscribeTokenFromDriverTopic(int $driverId, string $token): void
    {
        $this->messaging->unsubscribeFromTopic("driver_{$driverId}", [$token]);
    }
}
