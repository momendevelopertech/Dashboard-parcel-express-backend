<?php
namespace Tests\Feature;

use App\Jobs\DeliverWebhookJob;
use App\Models\Partner;
use App\Models\PartnerWebhook;
use App\Models\WebhookDelivery;
use App\Models\Shipment;
use App\Services\WebhookDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_is_queued_on_shipment_status_change()
    {
        Queue::fake();

        $partner = Partner::factory()->create();
        $webhook = PartnerWebhook::create([
            'partner_id' => $partner->id,
            'url' => 'https://partner.com/webhook',
            'secret_hash' => bcrypt('secret'),
            'events' => ['shipment.status.changed'],
            'is_active' => true,
        ]);

        $shipment = Shipment::create([
            'partner_id' => $partner->id,
            'status' => 'created',
        ]);

        $service = app(WebhookDeliveryService::class);
        $service->onShipmentStatusChanged($shipment, 'created', 'in_transit');

        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_webhook_delivery_succeeds()
    {
        Http::fake(['*' => Http::response([], 200)]);

        $partner = Partner::factory()->create();
        $webhook = PartnerWebhook::create([
            'partner_id' => $partner->id,
            'url' => 'https://partner.com/webhook',
            'secret_hash' => bcrypt('secret'),
            'events' => ['shipment.status.changed'],
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => 'shipment.status.changed',
            'payload' => ['test' => 'data'],
            'status' => 'pending',
        ]);

        $job = new DeliverWebhookJob($delivery);
        $job->handle();

        $this->assertEquals('success', $delivery->fresh()->status);
    }
}