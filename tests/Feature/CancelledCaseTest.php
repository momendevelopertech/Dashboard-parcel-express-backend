<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ShelfController;
use App\Http\Controllers\AssignShipmentToShelfController;
use App\Http\Controllers\Driver\DeliveryReturnController;
use App\Http\Controllers\Driver\DriverShipmentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SorterController;
use App\Http\Controllers\StockOutTaskController;
use App\Models\Shipment;
use App\Models\Shelf;
use App\Models\Station;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelledCaseTest extends TestCase
{
    /** @test */
    public function it_creates_an_shipment_and_canclel_and_do_the_required_operations()
    {
        $shipmentService = new ShipmentService();

        $headers = [
            'X-Workspace-Key'  => Crypt::encryptString('3'),
            'X-Workspace-Type' => Station::class,
        ];

        // 1. Authenticate as SuperAdmin to create the shipment
        $admin = User::where('email', 'admin@gmail.com')->first();
        Sanctum::actingAs($admin, ['*']);

        // 2. Create the shipment
        $shipment = $shipmentService->create_shipment();

        // 3. Inbound sort
        $inboundSortResponse = $this->withHeaders($headers)
            ->postJson(
                action([SorterController::class, 'inbound_sort']),
                ['tracking_no' => $shipment->tracking_no]
            );
        $inboundSortResponse->assertStatus(200);

        // 4. Fetch drivers
        $getDriversResponse = $this->withHeaders($headers)
            ->getJson('/api/users/get-all-drivers');
        $getDriversResponse->assertStatus(200);
        $firstDriver = collect($getDriversResponse->json('data'))->first();

        // 5. Assign to driver
        $assignShipmentResponse = $this->withHeaders($headers)
            ->postJson(
                action([ShipmentController::class, 'assignShipment']),
                [
                    'tracking_no' => $shipment->tracking_no,
                    'driver_id'   => $firstDriver['id'],
                ]
            );
        $assignShipmentResponse->assertStatus(200);

        // 6. Switch to driver and confirm assignment
        $driver = User::find($firstDriver['id']);
        Sanctum::actingAs($driver, ['*']);


        $confirmAssignResponse = $this->withHeaders($headers)
            ->postJson(
                action([ShipmentController::class, 'confirmAssignShipment']),
                [
                    'tracking_no' => $shipment->tracking_no,
                    'driver_id'   => $driver->id,
                ]
            );
        $confirmAssignResponse->assertStatus(200);


        // make an exception of cancelled.
        $file = UploadedFile::fake()->image('proof.jpg');
        $exceptionResponse = $this->withHeaders($headers)
            ->postJson(
                action([DeliveryReturnController::class, 'handleShipmentException']),
                [
                    'tracking_no'     => $shipment->tracking_no,
                    'delivery_exception' => 'CANCELLED',
                    'proof' => $file,
                ]
            );
        $exceptionResponse->assertStatus(200);

        Sanctum::actingAs($admin, ['*']);

        // Sort OFD
        $sortOfdResponse = $this->withHeaders($headers)
            ->postJson(
                action([SorterController::class, 'sort_ofd']),
                ['tracking_no' => $shipment->tracking_no]
            );
        $sortOfdResponse->assertStatus(200);


        // // Assign to Shelf
        // $shelf = Shelf::where("barcode", "PE434343")->first();
        // $ATSResponse = $this->withHeaders($headers)
        //     ->postJson(
        //         action([AssignShipmentToShelfController::class, 'store']),
        //         [
        //             'tracking_no' => $shipment->tracking_no,
        //             'barcode' => $shelf->barcode
        //         ]
        //     );
        // $ATSResponse->assertStatus(200);

        // $shelfShipmentsResp = $this->withHeaders($headers)
        //     ->getJson(
        //         action([ShelfController::class, 'shipments']),
        //         [
        //             'tracking_no' => $shipment->tracking_no,
        //             'barcode'     => $shelf->barcode,
        //         ]
        //     );
        // $shelfShipmentsResp->assertStatus(200);

        // // Pluck all tracking numbers into a variable
        // $trackingNumbers = collect($shelfShipmentsResp->json('data.data'))
        //     ->pluck('tracking_no');

        // // here create the stock out task.
        // $StockoutResp = $this->withHeaders($headers)
        //     ->postJson(
        //         action([StockOutTaskController::class, 'store']),
        //         [
        //             'shipments' => $trackingNumbers,
        //         ]
        //     );
        // $StockoutResp->assertStatus(200);


        // $tasksResp = $this->withHeaders($headers)
        //     ->getJson(
        //         action([StockOutTaskController::class, 'tasks'])
        //     );

        // $tasks = collect($shelfShipmentsResp->json('data.data'))->pluck('id');
        // $tasksResp->assertStatus(200);
        // info($tasks[0]);
        // // stockout sort
        // $stockOutResp = $this->withHeaders($headers)
        //     ->postJson(
        //         action([SorterController::class, 'stockout']),
        //         [
        //             'tracking_no' => $trackingNumbers[0],
        //             'stock_out_task_id' => $tasks[0]
        //         ]
        //     );
        // $stockOutResp->assertStatus(200);
    }
}
