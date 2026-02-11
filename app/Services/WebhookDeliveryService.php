<?php

namespace App\Services;
use App\Models\{PartnerWebhook, WebhookDelivery};
use App\Jobs\DeliverWebhookJob;
use Illuminate\Support\Str;


class WebhookDeliveryService
{
    public function queue(PartnerWebhook $webhook, string $event, array $payload): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => $event,
            'delivery_id' => Str::uuid(),
            'payload' => $payload,
            'status' => 'queued',
            'attempts' => 0,
        ]);
        DeliverWebhookJob::dispatch($delivery->id);
        return $delivery;
    }
}