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

class ShipmentCreate extends TestCase
{
    /** @test */
    public function shipment_create()
    {
        $shipmentService = new ShipmentService();

        $headers = [
            'X-Workspace-Key' => Crypt::encryptString('1'),
            'X-Workspace-Type' => \App\Models\Hub::class,
        ];

        // 1. Authenticate as SuperAdmin to create the shipment
        $admin = User::where('email', 'superadmin@parcelexpress.com')->first();

        Sanctum::actingAs($admin, ['*']);
        // 2. Create the shipment
        //    Muscat
        $countryId = 165;
        $governorateId = 1;
        $stateId = 1;
        $placeId = 1;
        //    Sohar
//        $countryId = 165;
//        $governorateId = 5;
//        $stateId = 32;
//        $placeId = 1087;
        $shipmentService->create_shipment($countryId, $governorateId, $stateId, $placeId);
    }
}
