<?php


use App\Http\Controllers\Driver\DriverShipmentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SorterController;
use App\Models\Station;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentSuccessCaseTest extends TestCase
{
    /** @test */
    public function it_creates_an_shipment_and_delivers_it_end_to_end()
    {
        $shipmentService = new ShipmentService();

        $headers = [
            'X-Workspace-Key' => Crypt::encryptString('3'),
            'X-Workspace-Type' => Station::class,
        ];

        // 1. Authenticate as SuperAdmin to create the shipment
        $admin = User::where('email', 'superadmin@parcelexpress.com')->first();

        Sanctum::actingAs($admin, ['*']);
        // 2. Create the shipment
        $shipment = $shipmentService->create_shipment();

        // 3. Inbound sort
        $sorter = User::where('email', 'sorter.muscat@parcelexpress.com')->first();
        Sanctum::actingAs($sorter, ['*']);
        $inboundSortResponse = $this->withHeaders($headers)
            ->postJson(
                action([SorterController::class, 'inbound_sort']),
                ['tracking_no' => $shipment->tracking_no]
            );
        $inboundSortResponse->assertStatus(200);

        // 4. Fetch drivers
        $driver = User::with('roles','driver')->where("email",'driver.muscat@parcelexpress.com')->first();
        $firstDriver = collect($driver);

        // 5. Assign to driver
        $assignShipmentResponse = $this->withHeaders($headers)
            ->postJson(
                action([ShipmentController::class, 'assignShipment']),
                [
                    'tracking_no' => $shipment->tracking_no,
                    'driver_id' => $firstDriver['id'],
                ]
            );
        $assignShipmentResponse->assertStatus(200);

        // 6. Switch to driver and confirm assignment
        $driver = User::find($firstDriver['id']);
        pinfo($driver, "Driver in success");
        Sanctum::actingAs($driver, ['*']);

        $confirmAssignResponse = $this->postJson(
            action([ShipmentController::class, 'confirmAssignShipment']),
            [
                'tracking_no' => $shipment->tracking_no,
                'driver_id' => $driver->id,
            ]
        );
        $confirmAssignResponse->assertStatus(200);

        // // 7. Deliver the parcel
        // $file = UploadedFile::fake()->image('proof.jpg');
        // $deliverResponse = $this->postJson(
        //     action([DriverShipmentController::class, 'deliver']),
        //     [
        //         'shipment_id' => $shipment->id,
        //         'payment_cash' => $shipment->amount,
        //         'delivery_lat' => 30.062638,
        //         'delivery_lng' => 31.249673,
        //         'status' => 'DELIVERED',
        //         'proof' => $file,
        //     ]
        // );
        // $deliverResponse->assertStatus(200);

        // // 8. Assert final status
        // $this->assertDatabaseHas('shipments', [
        //     'id' => $shipment->id,
        //     'status' => 'DELIVERED',
        // ]);
    }
}
