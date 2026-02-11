<?php
namespace App\Jobs;
use App\Models\{WebhookDelivery, PartnerWebhook};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;


class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $deliveryId)
    {
    }


    public function handle(): void
    {
        $delivery = WebhookDelivery::with('webhook')->findOrFail($this->deliveryId);
        $webhook = $delivery->webhook;
        if (!$webhook->is_active)
            return; 
        $payload = $delivery->payload;
        $timestamp = now()->toIso8601String();
        $secret = decrypt($webhook->secret_hash); 
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sigBase = $timestamp . "\n" . hash('sha256', $payloadJson);
        $signature = hash_hmac(env('PARTNERAPI_HMAC_ALGO', 'sha256'), $sigBase, $secret);


        $delivery->update(['status' => 'delivering']);
        $resp = Http::timeout((int) env('PARTNERAPI_WEBHOOK_TIMEOUT', 10))
            ->withHeaders([
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
                'X-Event' => $delivery->event_type,
                'X-Delivery-Id' => $delivery->delivery_id,
                'Content-Type' => 'application/json',
            ])->post($webhook->url, $payloadJson);


        if ($resp->successful()) {
            $delivery->update(['status' => 'succeeded', 'delivered_at' => now(), 'attempts' => $delivery->attempts + 1]);
            $webhook->update(['last_success_at' => now(), 'failure_count' => 0]);
            return;
        }
        $attempts = $delivery->attempts + 1;
        $delivery->update(['attempts' => $attempts, 'status' => 'failed', 'last_error' => $resp->body()]);
        $backoffs = array_map('intval', explode(',', env('PARTNERAPI_BACKOFF_SECONDS', '3600,7200,14400,28800,86400')));
        if ($attempts >= (int) env('PARTNERAPI_WEBHOOK_MAX_ATTEMPTS', 5))
            return;
        $delay = $backoffs[min($attempts - 1, count($backoffs) - 1)] ?? 3600;
        $delivery->update(['next_retry_at' => now()->addSeconds($delay)]);
        self::dispatch($delivery->id)->delay(now()->addSeconds($delay));
    }
}