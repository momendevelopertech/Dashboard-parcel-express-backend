<?php

namespace Tests\Unit\Services\Return;

use Tests\TestCase;
use App\Services\Return\ReturnShipmentService;
use App\Services\Return\ReturnPricingService;
use App\Services\Return\ReturnFinancialService;
use App\Models\ReturnRequest;
use App\Models\Shipment;
use App\Models\PickupTask;
use App\Models\User;
use App\Models\Zone;
use App\Enums\ShipmentStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ReturnShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReturnShipmentService $service;
    protected ReturnPricingService $pricingService;
    protected ReturnFinancialService $financialService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReturnShipmentService::class);
        $this->pricingService = app(ReturnPricingService::class);
        $this->financialService = app(ReturnFinancialService::class);
    }

    /** @test */
    public function it_creates_return_pickup_request_with_shipments()
    {
        // Arrange
        $merchant = User::factory()->create(['role' => 'merchant']);
        $zone = Zone::factory()->create();

        $data = [
            'merchant_id' => $merchant->id,
            'details' => [
                [
                    'customer_name' => 'John Doe',
                    'customer_phone' => '12345678',
                    'customer_address' => '123 Main St',
                    'count' => 2,
                    'zone_id' => $zone->id,
                    'state_id' => 1,
                    'country_id' => 1,
                ],
            ],
        ];

        // Act
        $returnRequest = $this->service->createReturnPickupRequest($data);

        // Assert
        $this->assertInstanceOf(ReturnRequest::class, $returnRequest);
        $this->assertEquals($merchant->id, $returnRequest->merchant_id);
        $this->assertEquals(2, $returnRequest->no_of_shipments);
        $this->assertCount(2, $returnRequest->shipments);

        // Verify shipments are marked as returns
        foreach ($returnRequest->shipments as $shipment) {
            $this->assertTrue($shipment->is_return);
            $this->assertEquals('reverse_pickup', $shipment->return_type);
            $this->assertEquals($returnRequest->id, $shipment->return_request_id);
        }
    }

    /** @test */
    public function it_assigns_shipments_to_driver()
    {
        // Arrange
        $driver = User::factory()->create(['role' => 'driver']);
        $returnRequest = ReturnRequest::factory()->create();
        $shipments = Shipment::factory()->count(3)->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'return_request_id' => $returnRequest->id,
            'driver_id' => null,
        ]);

        $shipmentIds = $shipments->pluck('id')->toArray();

        // Act
        $tasks = $this->service->assignShipmentsToDriver($shipmentIds, $driver->id);

        // Assert
        $this->assertCount(3, $tasks);

        foreach ($tasks as $task) {
            $this->assertInstanceOf(PickupTask::class, $task);
            $this->assertEquals($driver->id, $task->driver_id);
            $this->assertEquals('assigned', $task->status);
        }

        // Verify shipments updated
        $shipments->each(function ($shipment) use ($driver) {
            $shipment->refresh();
            $this->assertEquals($driver->id, $shipment->driver_id);
            $this->assertEquals(ShipmentStatusEnum::TO_PICKUP, $shipment->status);
        });
    }

    /** @test */
    public function it_assigns_shipments_by_zone()
    {
        // Arrange
        $driver = User::factory()->create(['role' => 'driver']);
        $zone = Zone::factory()->create();
        $returnRequest = ReturnRequest::factory()->create();

        $shipmentsInZone = Shipment::factory()->count(2)->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'return_request_id' => $returnRequest->id,
            'zone_id' => $zone->id,
            'driver_id' => null,
        ]);

        $shipmentsOutsideZone = Shipment::factory()->count(2)->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'return_request_id' => $returnRequest->id,
            'zone_id' => Zone::factory()->create()->id,
            'driver_id' => null,
        ]);

        // Act
        $tasks = $this->service->assignShipmentsByZone(
            $returnRequest->id,
            $zone->id,
            $driver->id
        );

        // Assert
        $this->assertCount(2, $tasks); // Only shipments in the zone

        // Verify only zone shipments assigned
        $shipmentsInZone->each(function ($shipment) use ($driver) {
            $shipment->refresh();
            $this->assertEquals($driver->id, $shipment->driver_id);
        });

        $shipmentsOutsideZone->each(function ($shipment) {
            $shipment->refresh();
            $this->assertNull($shipment->driver_id);
        });
    }

    /** @test */
    public function it_marks_shipment_as_picked()
    {
        // Arrange
        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'status' => ShipmentStatusEnum::TO_PICKUP,
        ]);

        $proofPaths = ['proof1.jpg', 'proof2.jpg'];

        // Act
        $this->service->markAsPicked($shipment->id, $proofPaths);

        // Assert
        $shipment->refresh();
        $this->assertEquals(ShipmentStatusEnum::PICKED_UP, $shipment->status);

        // Verify pickup task updated
        $pickupTask = $shipment->pickupTask;
        $this->assertNotNull($pickupTask);
        $this->assertEquals('picked', $pickupTask->status);
        $this->assertNotNull($pickupTask->picked_at);
    }

    /** @test */
    public function it_marks_shipment_as_at_hub()
    {
        // Arrange
        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'status' => ShipmentStatusEnum::PICKED_UP,
        ]);

        // Act
        $this->service->markAsAtHub($shipment->id, 'Received at hub');

        // Assert
        $shipment->refresh();
        $this->assertEquals(ShipmentStatusEnum::AT_HUB, $shipment->status);
    }

    /** @test */
    public function it_marks_shipment_as_delivered_to_merchant()
    {
        // Arrange
        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'status' => ShipmentStatusEnum::OUT_FOR_DELIVERY,
        ]);

        // Act
        $this->service->markAsDeliveredToMerchant($shipment->id);

        // Assert
        $shipment->refresh();
        $this->assertEquals(ShipmentStatusEnum::DELIVERED, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
    }

    /** @test */
    public function it_cancels_return_shipment()
    {
        // Arrange
        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
            'status' => ShipmentStatusEnum::TO_PICKUP,
        ]);

        // Act
        $this->service->cancelReturnShipment($shipment->id, 'Customer request');

        // Assert
        $shipment->refresh();
        $this->assertEquals(ShipmentStatusEnum::CANCELLED, $shipment->status);
    }

    /** @test */
    public function it_throws_exception_when_assigning_non_return_shipment()
    {
        // Arrange
        $driver = User::factory()->create(['role' => 'driver']);
        $shipment = Shipment::factory()->create([
            'is_return' => false, // Not a return shipment
        ]);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('is not a reverse pickup return');

        $this->service->assignShipmentsToDriver([$shipment->id], $driver->id);
    }

    /** @test */
    public function it_creates_multiple_shipments_for_count_greater_than_one()
    {
        // Arrange
        $merchant = User::factory()->create(['role' => 'merchant']);
        $zone = Zone::factory()->create();

        $data = [
            'merchant_id' => $merchant->id,
            'details' => [
                [
                    'customer_name' => 'John Doe',
                    'customer_phone' => '12345678',
                    'count' => 5, // Should create 5 shipments
                    'zone_id' => $zone->id,
                    'state_id' => 1,
                    'country_id' => 1,
                ],
            ],
        ];

        // Act
        $returnRequest = $this->service->createReturnPickupRequest($data);

        // Assert
        $this->assertEquals(5, $returnRequest->no_of_shipments);
        $this->assertCount(5, $returnRequest->shipments);
    }

    /** @test */
    public function it_generates_unique_tracking_numbers()
    {
        // Arrange
        $merchant = User::factory()->create(['role' => 'merchant']);
        $zone = Zone::factory()->create();

        $data = [
            'merchant_id' => $merchant->id,
            'details' => [
                [
                    'customer_name' => 'John Doe',
                    'customer_phone' => '12345678',
                    'count' => 3,
                    'zone_id' => $zone->id,
                    'state_id' => 1,
                    'country_id' => 1,
                ],
            ],
        ];

        // Act
        $returnRequest = $this->service->createReturnPickupRequest($data);

        // Assert
        $trackingNumbers = $returnRequest->shipments->pluck('tracking_no')->toArray();
        $uniqueTrackingNumbers = array_unique($trackingNumbers);

        $this->assertCount(3, $uniqueTrackingNumbers);
        $this->assertEquals(count($trackingNumbers), count($uniqueTrackingNumbers));
    }

    /** @test */
    public function it_uses_transaction_for_assignment()
    {
        // Arrange
        $driver = User::factory()->create(['role' => 'driver']);
        $shipment = Shipment::factory()->create([
            'is_return' => true,
            'return_type' => 'reverse_pickup',
        ]);

        // Mock DB transaction to verify it's used
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Act
        $this->service->assignShipmentsToDriver([$shipment->id], $driver->id);

        // Assert - transaction was called (verified by mock)
    }
}
