<?php

namespace Tests\Feature\Api\v1;

use Tests\TestCase;
use App\Models\User;
use App\Models\Shipment;
use App\Models\PickupTask;
use App\Models\ReturnRequest;
use App\Enums\ShipmentStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class DriverReturnPickupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;
    protected User $otherDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::factory()->create(['role' => 'driver']);
        $this->otherDriver = User::factory()->create(['role' => 'driver']);

        Storage::fake('public');
    }

    /** @test */
    public function driver_can_view_their_assigned_tasks()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $ownTasks = PickupTask::factory()->count(3)->create([
            'driver_id' => $this->driver->id,
        ]);

        $otherTasks = PickupTask::factory()->count(2)->create([
            'driver_id' => $this->otherDriver->id,
        ]);

        // Act
        $response = $this->getJson('/api/v1/driver/return-pickup/my-tasks');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    /** @test */
    public function driver_can_filter_tasks_by_status()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        PickupTask::factory()->count(2)->create([
            'driver_id' => $this->driver->id,
            'status' => 'assigned',
        ]);

        PickupTask::factory()->count(3)->create([
            'driver_id' => $this->driver->id,
            'status' => 'picked',
        ]);

        // Act
        $response = $this->getJson('/api/v1/driver/return-pickup/my-tasks?status=assigned');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function driver_can_pickup_assigned_shipment()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'driver_id' => $this->driver->id,
            'status' => ShipmentStatusEnum::TO_PICKUP,
        ]);

        $proofs = [
            UploadedFile::fake()->image('proof1.jpg'),
            UploadedFile::fake()->image('proof2.jpg'),
        ];

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/pickup', [
            'tracking_no' => $shipment->tracking_no,
            'proofs' => $proofs,
            'note' => 'Picked up successfully',
        ]);

        // Assert
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => ShipmentStatusEnum::PICKED_UP,
        ]);

        // Verify proofs uploaded
        Storage::disk('public')->assertExists('return_pickup_proofs/proof1.jpg');
        Storage::disk('public')->assertExists('return_pickup_proofs/proof2.jpg');
    }

    /** @test */
    public function driver_cannot_pickup_shipment_assigned_to_another_driver()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'driver_id' => $this->otherDriver->id, // Assigned to other driver
            'status' => ShipmentStatusEnum::TO_PICKUP,
        ]);

        $proofs = [
            UploadedFile::fake()->image('proof.jpg'),
        ];

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/pickup', [
            'tracking_no' => $shipment->tracking_no,
            'proofs' => $proofs,
        ]);

        // Assert
        $response->assertForbidden();
        $response->assertJsonPath('success', false);
    }

    /** @test */
    public function driver_can_scan_shipment_at_hub()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'status' => ShipmentStatusEnum::PICKED_UP,
        ]);

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/scan-hub', [
            'tracking_no' => $shipment->tracking_no,
            'note' => 'Received at hub',
        ]);

        // Assert
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => ShipmentStatusEnum::AT_HUB,
        ]);
    }

    /** @test */
    public function driver_can_deliver_shipment_to_merchant()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'status' => ShipmentStatusEnum::OUT_FOR_DELIVERY,
        ]);

        $proof = UploadedFile::fake()->image('delivery_proof.jpg');

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/deliver-to-merchant', [
            'tracking_no' => $shipment->tracking_no,
            'delivery_proof' => $proof,
            'note' => 'Delivered to merchant',
        ]);

        // Assert
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => ShipmentStatusEnum::DELIVERED,
        ]);

        $shipment->refresh();
        $this->assertNotNull($shipment->delivered_at);
    }

    /** @test */
    public function driver_can_view_shipment_details()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $returnRequest = ReturnRequest::factory()->create();
        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'return_request_id' => $returnRequest->id,
        ]);

        // Act
        $response = $this->getJson("/api/v1/driver/return-pickup/shipment/{$shipment->tracking_no}");

        // Assert
        $response->assertOk();
        $response->assertJsonPath('data.tracking_no', $shipment->tracking_no);
        $response->assertJsonPath('data.is_return', true);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'tracking_no',
                'is_return',
                'return_type',
                'status',
                'returnRequest',
                'pickupTask',
            ],
        ]);
    }

    /** @test */
    public function validation_fails_when_pickup_without_proofs()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'driver_id' => $this->driver->id,
        ]);

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/pickup', [
            'tracking_no' => $shipment->tracking_no,
            // Missing proofs
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['proofs']);
    }

    /** @test */
    public function validation_fails_for_invalid_tracking_number()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/scan-hub', [
            'tracking_no' => 'INVALID-TRACKING',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function driver_cannot_pickup_non_return_shipment()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $shipment = Shipment::factory()->create([
            'is_return' => false, // Not a return shipment
            'driver_id' => $this->driver->id,
        ]);

        $proofs = [
            UploadedFile::fake()->image('proof.jpg'),
        ];

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/pickup', [
            'tracking_no' => $shipment->tracking_no,
            'proofs' => $proofs,
        ]);

        // Assert
        $response->assertStatus(404); // Should not find return shipment
    }

    /** @test */
    public function backward_compatibility_old_driver_endpoint_works()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        PickupTask::factory()->count(2)->create([
            'driver_id' => $this->driver->id,
        ]);

        // Act - Using old endpoint
        $response = $this->getJson('/api/v1/driver/reverse-pickup/my-tasks');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function pickup_task_status_updates_when_shipment_picked()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'driver_id' => $this->driver->id,
            'status' => ShipmentStatusEnum::TO_PICKUP,
        ]);

        $pickupTask = PickupTask::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $this->driver->id,
            'status' => 'assigned',
        ]);

        $proofs = [
            UploadedFile::fake()->image('proof.jpg'),
        ];

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/pickup', [
            'tracking_no' => $shipment->tracking_no,
            'proofs' => $proofs,
        ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('pickup_tasks', [
            'id' => $pickupTask->id,
            'status' => 'picked',
        ]);

        $pickupTask->refresh();
        $this->assertNotNull($pickupTask->picked_at);
    }

    /** @test */
    public function driver_commission_credited_on_pickup()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'driver_id' => $this->driver->id,
            'status' => ShipmentStatusEnum::TO_PICKUP,
        ]);

        $proofs = [
            UploadedFile::fake()->image('proof.jpg'),
        ];

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/pickup', [
            'tracking_no' => $shipment->tracking_no,
            'proofs' => $proofs,
        ]);

        // Assert
        $response->assertOk();

        // Verify transaction created for driver commission
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->driver->id,
            'shipment_id' => $shipment->id,
            'type' => 'credit',
            'category' => 'driver_commission',
        ]);
    }

    /** @test */
    public function merchant_fee_debited_on_delivery()
    {
        // Arrange
        Sanctum::actingAs($this->driver);

        $merchant = User::factory()->create(['role' => 'merchant']);
        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'merchant_id' => $merchant->id,
            'status' => ShipmentStatusEnum::OUT_FOR_DELIVERY,
        ]);

        // Act
        $response = $this->postJson('/api/v1/driver/return-pickup/deliver-to-merchant', [
            'tracking_no' => $shipment->tracking_no,
        ]);

        // Assert
        $response->assertOk();

        // Verify transaction created for merchant fee
        $this->assertDatabaseHas('transactions', [
            'user_id' => $merchant->id,
            'shipment_id' => $shipment->id,
            'type' => 'debit',
            'category' => 'return_fee',
        ]);
    }
}
