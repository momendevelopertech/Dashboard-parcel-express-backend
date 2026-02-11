<?php

namespace Tests\Feature\Api\v1;

use Tests\TestCase;
use App\Models\User;
use App\Models\ReturnRequest;
use App\Models\Shipment;
use App\Models\Zone;
use App\Models\Country;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ReturnRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $merchant;
    protected User $admin;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = User::factory()->create(['role' => 'merchant']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->driver = User::factory()->create(['role' => 'driver']);
    }

    /** @test */
    public function merchant_can_list_their_return_requests()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        $ownRequests = ReturnRequest::factory()->count(3)->create([
            'merchant_id' => $this->merchant->id,
        ]);

        $otherRequests = ReturnRequest::factory()->count(2)->create([
            'merchant_id' => User::factory()->create()->id,
        ]);

        // Act
        $response = $this->getJson('/api/v1/return-requests');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
        $response->assertJsonPath('data.total', 3);
    }

    /** @test */
    public function merchant_can_create_return_request()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        $zone = Zone::factory()->create();
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);

        $data = [
            'details' => [
                [
                    'customer_name' => 'John Doe',
                    'customer_phone' => '12345678',
                    'customer_address' => '123 Main St',
                    'count' => 2,
                    'zone_id' => $zone->id,
                    'state_id' => $state->id,
                    'country_id' => $country->id,
                ],
            ],
        ];

        // Act
        $response = $this->postJson('/api/v1/return-requests', $data);

        // Assert
        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'ref',
                'merchant_id',
                'no_of_shipments',
                'shipments',
            ],
        ]);

        $this->assertDatabaseHas('return_requests', [
            'merchant_id' => $this->merchant->id,
            'no_of_shipments' => 2,
        ]);

        $this->assertDatabaseCount('shipments', 2);
    }

    /** @test */
    public function merchant_can_view_return_request()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        $returnRequest = ReturnRequest::factory()->create([
            'merchant_id' => $this->merchant->id,
        ]);

        $shipments = Shipment::factory()->count(3)->create([
            'is_return' => true,
            'return_request_id' => $returnRequest->id,
        ]);

        // Act
        $response = $this->getJson("/api/v1/return-requests/{$returnRequest->id}");

        // Assert
        $response->assertOk();
        $response->assertJsonPath('data.id', $returnRequest->id);
        $response->assertJsonCount(3, 'data.shipments');
    }

    /** @test */
    public function merchant_can_cancel_return_request()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        $returnRequest = ReturnRequest::factory()->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'pending',
        ]);

        $shipments = Shipment::factory()->count(2)->create([
            'is_return' => true,
            'return_request_id' => $returnRequest->id,
            'status' => 'TO_PICKUP',
        ]);

        // Act
        $response = $this->postJson("/api/v1/return-requests/{$returnRequest->id}/cancel", [
            'reason' => 'Customer changed mind',
        ]);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('return_requests', [
            'id' => $returnRequest->id,
            'status' => 'cancelled',
        ]);

        // Verify shipments cancelled
        $shipments->each(function ($shipment) {
            $this->assertDatabaseHas('shipments', [
                'id' => $shipment->id,
                'status' => 'CANCELLED',
            ]);
        });
    }

    /** @test */
    public function admin_can_assign_shipments_to_driver()
    {
        // Arrange
        Sanctum::actingAs($this->admin);

        $returnRequest = ReturnRequest::factory()->create();
        $shipments = Shipment::factory()->count(3)->create([
            'is_return' => true,
            'return_request_id' => $returnRequest->id,
            'return_type' => 'reverse_pickup',
            'driver_id' => null,
        ]);

        $data = [
            'shipment_ids' => $shipments->pluck('id')->toArray(),
            'driver_id' => $this->driver->id,
        ];

        // Act
        $response = $this->postJson("/api/v1/return-requests/{$returnRequest->id}/assign", $data);

        // Assert
        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify shipments assigned
        $shipments->each(function ($shipment) {
            $this->assertDatabaseHas('shipments', [
                'id' => $shipment->id,
                'driver_id' => $this->driver->id,
                'status' => 'TO_PICKUP',
            ]);
        });

        // Verify pickup tasks created
        $this->assertDatabaseCount('pickup_tasks', 3);
    }

    /** @test */
    public function admin_can_assign_shipments_by_zone()
    {
        // Arrange
        Sanctum::actingAs($this->admin);

        $zone = Zone::factory()->create();
        $returnRequest = ReturnRequest::factory()->create();

        $shipmentsInZone = Shipment::factory()->count(2)->create([
            'is_return' => true,
            'return_request_id' => $returnRequest->id,
            'return_type' => 'reverse_pickup',
            'zone_id' => $zone->id,
            'driver_id' => null,
        ]);

        $data = [
            'zone_id' => $zone->id,
            'driver_id' => $this->driver->id,
        ];

        // Act
        $response = $this->postJson("/api/v1/return-requests/{$returnRequest->id}/assign-by-zone", $data);

        // Assert
        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify shipments assigned
        $shipmentsInZone->each(function ($shipment) {
            $this->assertDatabaseHas('shipments', [
                'id' => $shipment->id,
                'driver_id' => $this->driver->id,
            ]);
        });
    }

    /** @test */
    public function merchant_can_get_their_return_shipments()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        $ownShipments = Shipment::factory()->count(3)->create([
            'merchant_id' => $this->merchant->id,
            'is_return' => true,
        ]);

        $otherShipments = Shipment::factory()->count(2)->create([
            'merchant_id' => User::factory()->create()->id,
            'is_return' => true,
        ]);

        // Act
        $response = $this->getJson('/api/v1/return-requests/shipments');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    /** @test */
    public function merchant_can_filter_shipments_by_status()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        Shipment::factory()->count(2)->create([
            'merchant_id' => $this->merchant->id,
            'is_return' => true,
            'status' => 'TO_PICKUP',
        ]);

        Shipment::factory()->count(3)->create([
            'merchant_id' => $this->merchant->id,
            'is_return' => true,
            'status' => 'DELIVERED',
        ]);

        // Act
        $response = $this->getJson('/api/v1/return-requests/shipments?status=TO_PICKUP');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
    }

    /** @test */
    public function merchant_can_get_cancelled_requests()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        $cancelledRequests = ReturnRequest::factory()->count(2)->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'cancelled',
        ]);

        $activeRequests = ReturnRequest::factory()->count(3)->create([
            'merchant_id' => $this->merchant->id,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->getJson('/api/v1/return-requests/cancelled');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
    }

    /** @test */
    public function non_merchant_cannot_create_return_request()
    {
        // Arrange
        Sanctum::actingAs($this->driver); // Driver, not merchant

        $data = [
            'details' => [
                [
                    'customer_name' => 'John Doe',
                    'customer_phone' => '12345678',
                    'count' => 1,
                ],
            ],
        ];

        // Act
        $response = $this->postJson('/api/v1/return-requests', $data);

        // Assert
        $response->assertForbidden();
    }

    /** @test */
    public function validation_fails_for_invalid_data()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        $data = [
            'details' => [
                [
                    // Missing required fields
                    'customer_name' => '',
                    'count' => 0,
                ],
            ],
        ];

        // Act
        $response = $this->postJson('/api/v1/return-requests', $data);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['details.0.customer_name', 'details.0.count']);
    }

    /** @test */
    public function backward_compatibility_old_endpoint_works()
    {
        // Arrange
        Sanctum::actingAs($this->merchant);

        ReturnRequest::factory()->count(2)->create([
            'merchant_id' => $this->merchant->id,
        ]);

        // Act - Using old endpoint
        $response = $this->getJson('/api/v1/reverse-pickup/requests');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
    }
}
