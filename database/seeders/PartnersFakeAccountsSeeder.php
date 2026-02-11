<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\PartnerKey;
use Illuminate\Support\Str;
use App\Models\PartnerWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PartnersFakeAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Delete existing data - temporarily disable foreign key checks for truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('webhook_deliveries')->truncate();
        DB::table('partner_webhooks')->truncate();
        DB::table('partner_keys')->truncate();
        DB::table('partners')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Create first partner
        $partner1 = Partner::create([
            'name' => 'Tech Solutions Partner',
            'contact_email' => 'tech.partner@example.com',
            'allowed_scopes' => ['read:shipments', 'write:shipments', 'read:tracking'],
            'is_active' => true,
            'rate_limit' => [
                'requests_per_minute' => 100,
                'requests_per_hour' => 5000,
            ],
        ]);

        // Generate both sandbox and production API keys for partner 1 manually
        $credentials1 = $this->createKeysManually($partner1, 'Partner 1');

        // Create webhook for partner 1
        $webhookSecret1 = Str::random(64);
        PartnerWebhook::create([
            'partner_id' => $partner1->id,
            'url' => 'https://tech-solutions.example.com/webhooks/parcel-express',
            'secret_hash' => encrypt($webhookSecret1),
            'events' => ['shipment.status.changed', 'delivery.completed'],
            'is_active' => true,
            'failure_count' => 0,
        ]);

        // Create second partner
        $partner2 = Partner::create([
            'name' => 'E-Commerce Integration Partner',
            'contact_email' => 'ecommerce.partner@example.com',
            'allowed_scopes' => ['read:shipments', 'write:shipments', 'read:tracking', 'manage:webhooks'],
            'is_active' => true,
            'rate_limit' => [
                'requests_per_minute' => 200,
                'requests_per_hour' => 10000,
            ],
        ]);

        // Generate both sandbox and production API keys for partner 2 manually
        $credentials2 = $this->createKeysManually($partner2, 'Partner 2');

        // Create webhook for partner 2
        $webhookSecret2 = Str::random(64);
        PartnerWebhook::create([
            'partner_id' => $partner2->id,
            'url' => 'https://ecommerce.example.com/webhooks/parcel-express',
            'secret_hash' => encrypt($webhookSecret2),
            'events' => ['shipment.status.changed', 'delivery.completed', 'shipment.created'],
            'is_active' => true,
            'failure_count' => 0,
        ]);

        // Output credentials for testing
        $this->command->info('');
        $this->command->info('Partner 1 - Tech Solutions Partner:');
        $this->command->info('  Sandbox Key:');
        $this->command->info('    X-API-Key: ' . $credentials1['sandbox']['key_id']);
        $this->command->info('    API Secret: ' . $credentials1['sandbox']['secret']);
        $this->command->info('  Production Key:');
        $this->command->info('    X-API-Key: ' . $credentials1['production']['key_id']);
        $this->command->info('    API Secret: ' . $credentials1['production']['secret']);
        $this->command->info('');
        $this->command->info('Partner 2 - E-Commerce Integration Partner:');
        $this->command->info('  Sandbox Key:');
        $this->command->info('    X-API-Key: ' . $credentials2['sandbox']['key_id']);
        $this->command->info('    API Secret: ' . $credentials2['sandbox']['secret']);
        $this->command->info('  Production Key:');
        $this->command->info('    X-API-Key: ' . $credentials2['production']['key_id']);
        $this->command->info('    API Secret: ' . $credentials2['production']['secret']);
    }

    /**
     * Create keys manually for a partner, storing plain text secrets (max 10 chars)
     */
    private function createKeysManually(Partner $partner, string $partnerLabel): array
    {
        // Create sandbox key with plain text secret (max 10 chars)
        $sandboxKeyId = 'test_' . Str::random(32);
        $sandboxSecret = Str::random(10); // Max 10 characters plain text

        PartnerKey::create([
            'partner_id' => $partner->id,
            'key_type' => 'sandbox',
            'key_id' => $sandboxKeyId,
            'secret_hash' => $sandboxSecret, // Store as plain text
            'is_active' => true,
            'expires_at' => null,
        ]);

        $this->command->info("✓ {$partnerLabel} - Sandbox key created with plain text secret");

        // Create production key with plain text secret (max 10 chars)
        $productionKeyId = 'live_' . Str::random(32);
        $productionSecret = Str::random(10); // Max 10 characters plain text

        PartnerKey::create([
            'partner_id' => $partner->id,
            'key_type' => 'production',
            'key_id' => $productionKeyId,
            'secret_hash' => $productionSecret, // Store as plain text
            'is_active' => true,
            'expires_at' => null,
        ]);

        $this->command->info("✓ {$partnerLabel} - Production key created with plain text secret");

        return [
            'sandbox' => [
                'key_id' => $sandboxKeyId,
                'secret' => $sandboxSecret,
            ],
            'production' => [
                'key_id' => $productionKeyId,
                'secret' => $productionSecret,
            ],
        ];
    }

}


