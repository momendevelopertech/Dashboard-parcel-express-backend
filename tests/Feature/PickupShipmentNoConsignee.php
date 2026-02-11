<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PickupShipmentNoConsignee extends TestCase
{

    protected $tracking_number = 'PE263844';
    protected $merchant_email = "merchant.muscat@parcelexpress.com";
    protected $admin_email = "superadmin@parcelexpress.com";
    protected $shipment_amount = 10;
    protected $payment_type = "COD";
    protected $shipment_fee = 1.000;
    protected $shipment_id = null;
    protected $admin_workspace_key = 1;
    protected $admin_workspace_type = \App\Models\Hub::class;
    protected $shipment_creater_workspace_key = 1;
    protected $shipment_creater_workspace_type = \App\Models\Hub::class;
    protected $sorter_workspace_key = 1;
    protected $sorter_workspace_type = \App\Models\Hub::class;
    protected $sorter_email = 'sorter.muscat@parcelexpress.com';
    protected $driver_workspace_key = 1;
    protected $driver_workspace_type = \App\Models\Hub::class;
    protected $driver_email = 'driver.muscat@parcelexpress.com';
    protected $driver_id = null;
    protected $merchant_id = null;

    /** @test */
    public function test_shipment_creation_with_no_consignee_validation(): void
    {
        $this->assertTrue(true);
        $headers = [
            'X-Workspace-Key' => Crypt::encryptString($this->driver_workspace_key),
            'X-Workspace-Type' => $this->driver_workspace_type,
            'Content-Type' => 'multipart/form-data',
            'Accept' => 'application/json'
        ];

        $driver = User::where('email', $this->driver_email)->first();
        info($driver);
        $this->driver_id = $driver->id;
        Sanctum::actingAs($driver, ['*']);
        $pickupShipmentResponse = $this->withHeaders($headers)
            ->post(
                action([\App\Http\Controllers\MobilePickupController::class, 'shipment_pickup']),
                ['shipment_tracking_no' => $this->tracking_number]
            );
        $pickupShipmentResponse->assertStatus(200);
    }
}
