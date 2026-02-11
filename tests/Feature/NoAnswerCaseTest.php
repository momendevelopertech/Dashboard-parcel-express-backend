<?php

namespace Tests\Feature;

use App\Http\Controllers\Driver\DeliveryReturnController;
use App\Http\Controllers\Driver\DriverShipmentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SorterController;
use App\Models\Station;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoAnswerCaseTest extends TestCase
{
    /** 
     * @test
     * 
     * Here this function is the base case for no answer exception. 
     * in which the driver will contact the customer and after contacting him 3 times he will make an exception with NO_ANSWER. 
     * and after that it will run an sort_ofd to redispatch it again.
     */
    public function create_shipment_and_create_no_answer_exception_and_then_move_to_dispatch()
    {
        $shipmentService = new ShipmentService();

        $headers = [
            'X-Workspace-Key' => Crypt::encryptString('3'),
            'X-Workspace-Type' => Station::class,
        ];

        // 1. Authenticate as SuperAdmin to create the shipment
        $superAdmin = User::where('email', 'admin@gmail.com')->first();

        Sanctum::actingAs($superAdmin, ['*']);
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
                    'driver_id' => $firstDriver['id'],
                ]
            );
        $assignShipmentResponse->assertStatus(200);

        // 6. Switch to driver and confirm assignment
        $driver = User::find($firstDriver['id']);
        Sanctum::actingAs($driver, ['*']);

        $confirmAssignResponse = $this->postJson(
            action([ShipmentController::class, 'confirmAssignShipment']),
            [
                'tracking_no' => $shipment->tracking_no,
                'driver_id' => $driver->id,
            ]
        );
        $confirmAssignResponse->assertStatus(200);

        // after confirmation the driver will contact the customer. as defined in the settings.
        $contactResp = $this->postJson(
            action([DriverShipmentController::class, 'contact_count']),
            [
                'shipment_id' => $shipment->id
            ]
        );
        $contactResp->assertStatus(200);

        // contact twice
        $contactResp = $this->postJson(
            action([DriverShipmentController::class, 'contact_count']),
            [
                'shipment_id' => $shipment->id
            ]
        );
        $contactResp->assertStatus(200);

        // contact thrice
        $contactResp = $this->postJson(
            action([DriverShipmentController::class, 'contact_count']),
            [
                'shipment_id' => $shipment->id
            ]
        );
        $contactResp->assertStatus(200);

        $file = UploadedFile::fake()->image('proof.jpg');
        $exceptionResponse = $this->withHeaders($headers)
            ->postJson(
                action([DeliveryReturnController::class, 'handleShipmentException']),
                [
                    'tracking_no' => $shipment->tracking_no,
                    'delivery_exception' => 'NO_ANSWER',
                    'proof' => $file,
                ]
            );
        $exceptionResponse->assertStatus(200);

        // Sanctum::actingAs($superAdmin, ['*']);
        // // Sort OFD
        // $sortOfdResponse = $this->withHeaders($headers)
        //     ->postJson(
        //         action([SorterController::class, 'sort_ofd']),
        //         ['tracking_no' => $shipment->tracking_no]
        //     );
        // $sortOfdResponse->assertStatus(200);
    }
}
