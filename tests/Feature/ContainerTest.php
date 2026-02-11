<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ContainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContainerTest extends TestCase
{
    use RefreshDatabase;

    protected ContainerService $containerService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->containerService = app(ContainerService::class);
        $this->user = User::factory()->create();
    }

    /**
     * Test creating a container
     */
    public function test_create_container()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'max_volume' => 50,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Container::class, $container);
        $this->assertNotNull($container->container_number);
        $this->assertNotNull($container->tracking_no);
        $this->assertEquals('BOX', $container->container_type);
        $this->assertEquals(100, $container->max_weight);
        $this->assertEquals('ACTIVE', $container->status);
    }

    /**
     * Test adding a shipment to container
     */
    public function test_add_shipment_to_container()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::factory()->create(['value' => 25]);

        $this->containerService->addShipment($container, $shipment);

        $this->assertEquals(1, $container->refresh()->shipment_count);
        $this->assertEquals(25, $container->current_weight);
        $this->assertEquals($container->id, $shipment->refresh()->container_id);
    }

    /**
     * Test removing a shipment from container
     */
    public function test_remove_shipment_from_container()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::factory()->create(['value' => 25]);
        $this->containerService->addShipment($container, $shipment);

        $this->containerService->removeShipment($container, $shipment);

        $this->assertEquals(0, $container->refresh()->shipment_count);
        $this->assertEquals(0, $container->current_weight);
        $this->assertNull($shipment->refresh()->container_id);
    }

    /**
     * Test adding multiple shipments
     */
    public function test_add_multiple_shipments()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 150,
            'created_by' => $this->user->id,
        ]);

        $shipments = Shipment::factory(3)->create(['value' => 25]);
        $shipmentIds = $shipments->pluck('id')->toArray();

        $results = $this->containerService->addMultipleShipments($container, $shipmentIds);

        $this->assertEquals(3, count($results['success']));
        $this->assertEquals(0, count($results['failed']));
        $this->assertEquals(3, $container->refresh()->shipment_count);
        $this->assertEquals(75, $container->current_weight);
    }

    /**
     * Test container capacity check
     */
    public function test_container_capacity_check()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 50,
            'created_by' => $this->user->id,
        ]);

        $shipment1 = Shipment::factory()->create(['value' => 30]);
        $shipment2 = Shipment::factory()->create(['value' => 25]);

        // First shipment should fit
        $this->assertTrue($container->canAcceptWeight($shipment1->value));
        $this->containerService->addShipment($container, $shipment1);

        // Second shipment should NOT fit
        $this->assertFalse($container->refresh()->canAcceptWeight($shipment2->value));
    }

    /**
     * Test container full status
     */
    public function test_container_full_status()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 50,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::factory()->create(['value' => 50]);
        $this->containerService->addShipment($container, $shipment);

        $this->assertTrue($container->refresh()->isFull());
        $this->assertEquals('FULL', $container->status);
    }

    /**
     * Test moving shipments between containers
     */
    public function test_move_shipments_between_containers()
    {
        $container1 = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        $container2 = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::factory()->create(['value' => 25]);
        $this->containerService->addShipment($container1, $shipment);

        $results = $this->containerService->moveShipments($container1, $container2, [$shipment->id]);

        $this->assertEquals(1, count($results['success']));
        $this->assertEquals(0, $container1->refresh()->shipment_count);
        $this->assertEquals(1, $container2->refresh()->shipment_count);
        $this->assertEquals($container2->id, $shipment->refresh()->container_id);
    }

    /**
     * Test emptying container
     */
    public function test_empty_container()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        Shipment::factory(3)->create(['value' => 25])->each(function ($shipment) use ($container) {
            $this->containerService->addShipment($container, $shipment);
        });

        $removedCount = $this->containerService->emptyContainer($container);

        $this->assertEquals(3, $removedCount);
        $this->assertEquals(0, $container->refresh()->shipment_count);
        $this->assertEquals(0, $container->current_weight);
        $this->assertEquals('ACTIVE', $container->status);
    }

    /**
     * Test get container details
     */
    public function test_get_container_details()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'max_volume' => 50,
            'created_by' => $this->user->id,
        ]);

        $shipments = Shipment::factory(2)->create(['value' => 25]);
        $shipments->each(function ($shipment) use ($container) {
            $this->containerService->addShipment($container, $shipment);
        });

        $details = $this->containerService->getContainerDetails($container);

        $this->assertEquals(2, $details['shipment_count']);
        $this->assertEquals(50, $details['current_weight']);
        $this->assertEquals(50, $details['remaining_weight']);
        $this->assertEquals(50, $details['utilization_percentage']);
    }

    /**
     * Test API - Get containers
     */
    public function test_api_get_containers()
    {
        $this->containerService->createContainer([
            'container_type' => 'BOX',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/containers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'current_page',
                'data' => [
                    '*' => ['id', 'container_number', 'container_type', 'status']
                ],
                'total',
                'per_page'
            ]
        ]);
    }

    /**
     * Test API - Create container
     */
    public function test_api_create_container()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/containers', [
            'container_type' => 'BOX',
            'max_weight' => 100,
            'max_volume' => 50,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => ['id', 'container_number', 'container_type', 'status']
        ]);

        $this->assertDatabaseHas('containers', [
            'container_type' => 'BOX',
            'max_weight' => 100,
        ]);
    }

    /**
     * Test API - Add shipment
     */
    public function test_api_add_shipment()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::factory()->create(['value' => 25]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/containers/{$container->id}/add-shipment",
            ['shipment_id' => $shipment->id]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => ['container', 'shipment']
        ]);
    }

    /**
     * Test API - Add multiple shipments
     */
    public function test_api_add_multiple_shipments()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 150,
            'created_by' => $this->user->id,
        ]);

        $shipments = Shipment::factory(3)->create(['value' => 25]);
        $shipmentIds = $shipments->pluck('id')->toArray();

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/containers/{$container->id}/add-multiple-shipments",
            ['shipment_ids' => $shipmentIds]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => ['success', 'failed'],
            'container'
        ]);
    }

    /**
     * Test API - Move shipments
     */
    public function test_api_move_shipments()
    {
        $container1 = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        $container2 = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        $shipment = Shipment::factory()->create(['value' => 25]);
        $this->containerService->addShipment($container1, $shipment);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/containers/{$container1->id}/move-shipments",
            [
                'to_container_id' => $container2->id,
                'shipment_ids' => [$shipment->id]
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => ['success', 'failed'],
            'from_container',
            'to_container'
        ]);
    }

    /**
     * Test API - Empty container
     */
    public function test_api_empty_container()
    {
        $container = $this->containerService->createContainer([
            'container_type' => 'BOX',
            'max_weight' => 100,
            'created_by' => $this->user->id,
        ]);

        Shipment::factory(3)->create(['value' => 25])->each(function ($shipment) use ($container) {
            $this->containerService->addShipment($container, $shipment);
        });

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/containers/{$container->id}/empty"
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => ['container', 'removed_shipments_count']
        ]);
    }
}
