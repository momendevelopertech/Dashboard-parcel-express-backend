<?php

// tests/Feature/PartnerApiTest.php
namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerKey;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerApiTest extends TestCase
{
    use RefreshDatabase;

    protected Partner $partner;
    protected string $apiKey;
    protected string $apiSecret;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test partner
        $this->partner = Partner::create([
            'name' => 'Test Partner',
            'contact_email' => 'test@partner.com',
            'allowed_scopes' => ['read:shipments', 'tracking:read'],
            'is_active' => true,
            'rate_limit' => 120,
            'daily_quota' => 150000,
        ]);

        // Generate credentials
        $credentials = PartnerKey::generate($this->partner);
        $this->apiKey = $credentials['key_id'];
        $this->apiSecret = $credentials['secret'];

        // Store secret for testing
        config(['partners.secrets.' . $this->partner->id => $this->apiSecret]);
    }

    protected function makeAuthenticatedRequest(string $method, string $uri, array $data = [])
    {
        $timestamp = now()->toIso8601String();
        $body = empty($data) ? '' : json_encode($data);
        $bodyHash = hash('sha256', $body);

        $stringToSign = "{$method}\n{$uri}\n{$timestamp}\n{$bodyHash}";
        $signature = hash_hmac('sha256', $stringToSign, $this->apiSecret);

        return $this->json($method, $uri, $data, [
            'X-API-Key' => $this->apiKey,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ]);
    }

    public function test_can_retrieve_partner_profile()
    {
        $response = $this->makeAuthenticatedRequest('GET', '/api/v1/partners/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'partner_id',
                'name',
                'scopes',
                'is_active',
                'created_at',
            ]);
    }

    public function test_can_get_shipment_details()
    {
        $shipment = Shipment::create([
            'partner_id' => $this->partner->id,
            'partner_shipment_id' => 'TEST-123',
            'status' => 'in_transit',
            'tracking_number' => 'TRK123456',
            'recipient_name' => 'John Doe',
            'recipient_phone' => '+96891234567',
            'recipient_city' => 'Muscat',
            'recipient_country' => 'OM',
        ]);

        $response = $this->makeAuthenticatedRequest('GET', '/api/v1/shipments/' . $shipment->id);

        $response->assertStatus(200)
            ->assertJson([
                'internal_shipment_id' => 'ORD-' . $shipment->id,
                'partner_shipment_id' => 'TEST-123',
                'status' => 'in_transit',
            ]);
    }

    public function test_cannot_access_other_partner_shipments()
    {
        $otherPartner = Partner::create([
            'name' => 'Other Partner',
            'contact_email' => 'other@partner.com',
            'allowed_scopes' => ['read:shipments'],
            'is_active' => true,
        ]);

        $shipment = Shipment::create([
            'partner_id' => $otherPartner->id,
            'partner_shipment_id' => 'OTHER-123',
            'status' => 'delivered',
        ]);

        $response = $this->makeAuthenticatedRequest('GET', '/api/v1/shipments/' . $shipment->id);

        $response->assertStatus(404);
    }

    public function test_rate_limiting_works()
    {
        $this->partner->update(['rate_limit' => 2]);

        // First two requests should succeed
        $this->makeAuthenticatedRequest('GET', '/api/v1/partners/me')->assertStatus(200);
        $this->makeAuthenticatedRequest('GET', '/api/v1/partners/me')->assertStatus(200);

        // Third should be rate limited
        $response = $this->makeAuthenticatedRequest('GET', '/api/v1/partners/me');
        $response->assertStatus(429)
            ->assertJson(['code' => 'rate_limit_exceeded']);
    }

    public function test_invalid_signature_rejected()
    {
        $timestamp = now()->toIso8601String();

        $response = $this->json('GET', '/api/v1/partners/me', [], [
            'X-API-Key' => $this->apiKey,
            'X-Timestamp' => $timestamp,
            'X-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(401)
            ->assertJson(['code' => 'invalid_signature']);
    }

    public function test_expired_timestamp_rejected()
    {
        $timestamp = now()->subMinutes(10)->toIso8601String();
        $stringToSign = "GET\napi/v1/partners/me\n{$timestamp}\n" . hash('sha256', '');
        $signature = hash_hmac('sha256', $stringToSign, $this->apiSecret);

        $response = $this->json('GET', '/api/v1/partners/me', [], [
            'X-API-Key' => $this->apiKey,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(401)
            ->assertJson(['code' => 'expired_timestamp']);
    }

    public function test_can_list_shipments_with_filters()
    {
        Shipment::create([
            'partner_id' => $this->partner->id,
            'partner_shipment_id' => 'TEST-1',
            'status' => 'delivered',
            'created_at' => now()->subDays(5),
        ]);

        Shipment::create([
            'partner_id' => $this->partner->id,
            'partner_shipment_id' => 'TEST-2',
            'status' => 'in_transit',
            'created_at' => now(),
        ]);

        $response = $this->makeAuthenticatedRequest('GET', '/api/v1/shipments?status=in_transit');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'in_transit');
    }
}


/*
=============================================================================
SETUP INSTRUCTIONS
=============================================================================

1. RUN MIGRATIONS
-----------------
php artisan migrate


2. INSTALL REQUIRED PACKAGES
-----------------------------
composer require predis/predis


3. CONFIGURE REDIS (for rate limiting)
---------------------------------------
Update .env:
REDIS_MERCHANT=phpredis
REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis


4. REGISTER COMMANDS
--------------------
Add to app/Console/Kernel.php $commands array:
Commands\CreatePartnerCommand::class,
Commands\CreateWebhookCommand::class,
Commands\ListPartnersCommand::class,
Commands\TestWebhookCommand::class,
Commands\GenerateApiDocsCommand::class,


5. CREATE FIRST PARTNER
------------------------
php artisan partner:create "Temu" "temu@marketplace.com" \
  --scopes=read:shipments --scopes=tracking:read


6. ADD WEBHOOK (optional)
--------------------------
php artisan partner:webhook 1 "https://temu.com/webhooks/parcel-express" \
  --events=shipment.status.changed --events=delivery.completed


7. START QUEUE WORKER
----------------------
php artisan queue:work --queue=webhooks


8. RUN TESTS
------------
php artisan test


9. GENERATE API DOCUMENTATION
------------------------------
php artisan partner:generate-docs


10. CREATE TRACKING EVENTS TABLE (if not exists)
-------------------------------------------------
You'll need to create a tracking_events table for shipments:

php artisan make:migration create_tracking_events_table

Schema::create('tracking_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipment_id')->constrained()->onDelete('cascade');
    $table->string('status');
    $table->text('description');
    $table->string('city')->nullable();
    $table->string('country')->nullable();
    $table->timestamps();
});


11. EXAMPLE: TESTING WITH CURL
-------------------------------
# Generate signature
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
METHOD="GET"
PATH="api/v1/partners/me"
BODY_HASH=$(echo -n "" | sha256sum | cut -d' ' -f1)
STRING_TO_SIGN="${METHOD}\n${PATH}\n${TIMESTAMP}\n${BODY_HASH}"
SIGNATURE=$(echo -n "$STRING_TO_SIGN" | openssl dgst -sha256 -hmac "YOUR_SECRET" | cut -d' ' -f2)

curl -X GET "https://api.parcelexpress.om/v1/partners/me" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-Signature: $SIGNATURE"


12. MONITORING
--------------
# Check webhook delivery status
SELECT * FROM webhook_deliveries WHERE status = 'failed';

# Check rate limit usage
php artisan tinker
>>> Redis::get('partner:ratelimit:1')

# View partner logs
SELECT * FROM partner_api_logs WHERE partner_id = 1 ORDER BY created_at DESC LIMIT 10;

=============================================================================
*/