<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\PartnerKey;
use Illuminate\Database\Seeder;

class SeedTestPartnerKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verify APP_KEY is set
        if (empty(config('app.key'))) {
            $this->command->error('ERROR: APP_KEY is not set in .env file!');
            $this->command->error('Run: php artisan key:generate');
            throw new \Exception('APP_KEY is not configured');
        }

        // Check if partner with key PARTNER_123 already exists
        $existingKey = PartnerKey::where('key_id', 'PARTNER_123')->first();
        if ($existingKey) {
            $this->command->warn('Partner key PARTNER_123 already exists. Skipping...');
            return;
        }

        // Create test partner
        $partner = Partner::create([
            'name' => 'Test Partner',
            'contact_email' => 'test@example.com',
            'allowed_scopes' => ['read:shipments', 'write:shipments', 'read:tracking'],
            'is_active' => true,
            'rate_limit' => [
                'requests_per_minute' => 120,
                'requests_per_hour' => 5000,
            ],
        ]);

        // Create partner key with specific key_id and plain text secret
        $secret = 'supersecret';
        // // Ensure secret is max 10 characters
        // if (strlen($secret) > 10) {
        //     $secret = substr($secret, 0, 10);
        // }

        PartnerKey::create([
            'partner_id' => $partner->id,
            'key_type' => 'sandbox',
            'key_id' => 'PARTNER_123',
            'secret_hash' => $secret, // Store as plain text
            'is_active' => true,
            'expires_at' => null,
        ]);

        // Verify the key was stored correctly
        $createdKey = PartnerKey::where('key_id', 'PARTNER_123')->first();
        if ($createdKey && $createdKey->secret_hash === $secret) {
            $this->command->info('✓ Test partner and key created successfully!');
            $this->command->info('  Partner ID: ' . $partner->id);
            $this->command->info('  X-API-Key: PARTNER_123');
            $this->command->info('  API Secret: ' . $secret);
        } else {
            $this->command->error('✗ Key creation failed: Secret does not match stored value');
            throw new \Exception('Key creation verification failed');
        }
    }
}

