<?php

namespace Tests\Feature;

use App\Enums\MerchantPickupTaskStatusEnum;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantPickupTask;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreIdPickupStatusTest extends TestCase
{
    /** @test */
    public function it_sets_task_status_to_picked_when_pickup_task_id_is_provided()
    {
        Storage::fake('public');

        // Create a Hub & Station (Dependencies for User/Merchant)
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

        // Create Driver
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

        // Create a shipment with a pre_id
        $preId = 'PRE-' . uniqid();
        $shipment = Shipment::factory()->create([
            'merchant_id' => $merchantUser->id,
            'pre_id' => $preId,
            'tracking_no' => 'TRK-' . uniqid(),
            'status' => 'CREATED', 
        ]);

        // Create a MerchantPickupTask
        $task = MerchantPickupTask::create([
            'merchant_id' => $shipment->merchant_id,
            'driver_id' => $driver->id,
            'status' => 'to_pickup',
            'no_of_shipments' => 1,
        ]);

        // Create a MerchantPickupShipment assignment (simulating pre-assigned task)
        MerchantPickupShipment::create([
            'pickup_task_id' => $task->id,
            'merchant_id' => $shipment->merchant_id,
            'driver_id' => $driver->id,
            'shipment_tracking_no' => $shipment->tracking_no,
            'pre_id' => $shipment->pre_id,
            'status' => 'to_pickup',
        ]);

        // Authenticate as the driver
        Sanctum::actingAs($driver, ['*']);

        $response = $this->postJson('/api/driver/shipment_pickup', [
            'pre_id' => $preId,
            'proof' => UploadedFile::fake()->image('proof.jpg'),
            'pickup_task_id' => $task->id,
        ]);
        
        $response->dump();

        // Assertions
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Check if task status is updated to PICKED instead of pickup_completed
        $task->refresh();
        $this->assertEquals(MerchantPickupTaskStatusEnum::PICKED, $task->status);
        $this->assertNotNull($task->completed_at);
    }
}
