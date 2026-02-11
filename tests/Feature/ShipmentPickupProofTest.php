<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentProof;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentPickupProofTest extends TestCase
{
    /** @test */
    public function it_uploads_proof_2_when_pre_id_exists_and_proof_2_is_provided()
    {
        Storage::fake('public');

        // Create a Hub
        $hub = \App\Models\Hub::create([
            'name' => 'Test Hub',
            'location' => 'Test Location',
            'country_id' => 165,
            'governorate_id' => \App\Models\Governorate::inRandomOrder()->first()->id ?? 1,
            'state_id' => \App\Models\State::inRandomOrder()->first()->id ?? 1,
            'place_id' => \App\Models\Place::inRandomOrder()->first()->id ?? 1,
            'city_id' => \App\Models\City::inRandomOrder()->first()->id ?? 1,
        ]);

        // Create a Station
        $station = \App\Models\Station::create([
            'hub_id' => $hub->id,
            'name' => 'Test Station',
            'country_id' => 165,
            'governorate_id' => \App\Models\Governorate::inRandomOrder()->first()->id ?? 1,
            'state_id' => \App\Models\State::inRandomOrder()->first()->id ?? 1,
            'place_id' => \App\Models\Place::inRandomOrder()->first()->id ?? 1,
            'city_id' => \App\Models\City::inRandomOrder()->first()->id ?? 1,
        ]);

        // 1. Create a driver
        $driver = User::factory()->create([
            'owner_type' => \App\Models\Station::class,
            'owner_id' => $station->id,
        ]);
        $driver->assignRole('Driver');

        // Create Merchant User
        $merchantUser = User::factory()->create([
            'owner_type' => \App\Models\Station::class,
            'owner_id' => $station->id,
        ]);
        $merchantUser->assignRole('Merchant');

        // Create Merchant Profile linked to Station
        $merchant = \App\Models\Merchant::factory()->create([
            'user_id' => $merchantUser->id,
            'owner_type' => \App\Models\Station::class,
            'owner_id' => $station->id,
        ]);

        // 2. Create a shipment with a pre_id
        $preId = 'PRE-' . uniqid();
        $shipment = Shipment::factory()->create([
            'merchant_id' => $merchantUser->id,
            'pre_id' => $preId,
            'tracking_no' => 'TRK-' . uniqid(),
            'status' => 'CREATED', 
        ]);

        // Create a MerchantPickupTask
        $task = \App\Models\MerchantPickupTask::create([
            'merchant_id' => $shipment->merchant_id,
            'driver_id' => $driver->id,
            'status' => 'to_pickup',
            'no_of_shipments' => 1,
        ]);

        // Create a MerchantPickupShipment assignment
        \App\Models\MerchantPickupShipment::create([
            'pickup_task_id' => $task->id,
            'merchant_id' => $shipment->merchant_id,
            'driver_id' => $driver->id,
            'shipment_tracking_no' => $shipment->tracking_no,
            'pre_id' => $shipment->pre_id,
            'status' => 'to_pickup',
        ]);

        // 3. Authenticate as the driver
        Sanctum::actingAs($driver, ['*']);

        // 4. Prepare the request data
        $proofFile = UploadedFile::fake()->image('proof_2.jpg');
        $data = [
            'pre_id' => $preId,
            'proof_2' => $proofFile,
            // Add other required fields if necessary, e.g. tracking_no if the controller requires it or handles it
            'tracking_no' => $shipment->tracking_no, 
        ];

        // 5. Send the request
        $response = $this->postJson('/api/driver/shipment_pickup', $data);

        // 6. Assertions
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Assert file was stored
        // The controller stores it in 'pickup_proofs' folder
        // We need to check if any file exists in that directory or check the DB record
        
        // Assert ShipmentProof record created
        $this->assertDatabaseHas('shipment_proofs', [
            'shipment_id' => $shipment->id,
            'type' => 'pickup_2',
        ]);

        $proof = ShipmentProof::where('shipment_id', $shipment->id)
            ->where('type', 'pickup_2')
            ->first();

        $this->assertNotNull($proof);
        Storage::disk('public')->assertExists($proof->path);
    }

    /** @test */
    public function it_updates_proof_2_when_shipment_is_already_picked()
    {
        Storage::fake('public');

        // Setup environment
        $hub = \App\Models\Hub::create([
            'name' => 'Test Hub',
            'location' => 'Test Location',
            'country_id' => 165,
            'governorate_id' => \App\Models\Governorate::inRandomOrder()->first()->id ?? 1,
            'state_id' => \App\Models\State::inRandomOrder()->first()->id ?? 1,
            'place_id' => \App\Models\Place::inRandomOrder()->first()->id ?? 1,
            'city_id' => \App\Models\City::inRandomOrder()->first()->id ?? 1,
        ]);

        $station = \App\Models\Station::create([
            'hub_id' => $hub->id,
            'name' => 'Test Station',
            'country_id' => 165,
            'governorate_id' => \App\Models\Governorate::inRandomOrder()->first()->id ?? 1,
            'state_id' => \App\Models\State::inRandomOrder()->first()->id ?? 1,
            'place_id' => \App\Models\Place::inRandomOrder()->first()->id ?? 1,
            'city_id' => \App\Models\City::inRandomOrder()->first()->id ?? 1,
        ]);

        $driver = User::factory()->create([
            'owner_type' => \App\Models\Station::class,
            'owner_id' => $station->id,
        ]);
        $driver->assignRole('Driver');

        $merchantUser = User::factory()->create([
            'owner_type' => \App\Models\Station::class,
            'owner_id' => $station->id,
        ]);
        $merchantUser->assignRole('Merchant');

        \App\Models\Merchant::factory()->create([
            'user_id' => $merchantUser->id,
            'owner_type' => \App\Models\Station::class,
            'owner_id' => $station->id,
        ]);

        $preId = 'PRE-' . uniqid();
        $shipment = Shipment::factory()->create([
            'merchant_id' => $merchantUser->id,
            'pre_id' => $preId,
            'tracking_no' => 'TRK-' . uniqid(),
            'status' => 'CREATED', 
        ]);

        $task = \App\Models\MerchantPickupTask::create([
            'merchant_id' => $shipment->merchant_id,
            'driver_id' => $driver->id,
            'status' => 'to_pickup',
            'no_of_shipments' => 1,
        ]);

        // Create assignment and mark as PICKED
        \App\Models\MerchantPickupShipment::create([
            'pickup_task_id' => $task->id,
            'merchant_id' => $shipment->merchant_id,
            'driver_id' => $driver->id,
            'shipment_tracking_no' => $shipment->tracking_no,
            'pre_id' => $shipment->pre_id,
            'status' => 'picked', // Already picked
        ]);

        Sanctum::actingAs($driver, ['*']);

        $file = UploadedFile::fake()->image('proof_2.jpg');

        $data = [
            'pre_id' => $preId,
            'proof_2' => $file,
        ];

        $response = $this->postJson('/api/driver/shipment_pickup', $data);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('shipment_proofs', [
            'shipment_id' => $shipment->id,
            'type' => 'pickup_2',
            'uploaded_by' => $driver->id,
        ]);
    }
}
