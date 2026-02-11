<?php

namespace App\Observers;

use App\Enums\MerchantPickupTaskStatusEnum;
use App\Enums\ShipmentStatusEnum;
use App\Models\MerchantPickupShipment;
use App\Models\ShipmentHistory;
use App\Services\AdminCounterService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MerchantPickupShipmentObserver
{
    protected $adminCounterService;
    public function __construct(AdminCounterService $adminCounterService)
    {
        $this->adminCounterService = $adminCounterService;
    }
    /**
     * Handle the MerchantPickupShipment "created" event.
     */
    public function created(MerchantPickupShipment $merchantPickupShipment): void
    {
        is_null($merchantPickupShipment->shipment_id) ?? $this->adminCounterService->broadcastToAllAdmins();
    }

    /**
     * Handle the MerchantPickupShipment "updated" event.
     */
    public function updated(MerchantPickupShipment $merchantPickupShipment): void
    {
        $merchantPickupShipmentStatus = MerchantPickupTaskStatusEnum::PICKED;
        $shipmentStatus = ShipmentStatusEnum::PICKED;

        // Check if status changed to PICKED and we have proof and shipment_id
        if (
            $merchantPickupShipment->wasChanged('status') &&
            $merchantPickupShipment->status === $merchantPickupShipmentStatus &&
            !empty($merchantPickupShipment->pickup_proof) &&
            !empty($merchantPickupShipment->shipment_id)
        ) {

            // Look for existing history by 'name' field (not 'status')
            $history = ShipmentHistory::where('shipment_id', $merchantPickupShipment->shipment_id)
                ->where('name', $shipmentStatus)
                ->orderBy('created_at', 'desc')
                ->first();

            if (empty($history)) {
                // Create new history with proof
                shipmentHistory([
                    "description" => status($shipmentStatus)['description'] ?? 'Shipment picked from merchant',
                    "shipment_id" => $merchantPickupShipment->shipment_id,
                    "status" => status($shipmentStatus)['label'] ?? $shipmentStatus,
                    "proof" => $merchantPickupShipment->pickup_proof,
                ]);

                Log::info('[MerchantPickupShipmentObserver] Created shipment history with proof', [
                    'shipment_id' => $merchantPickupShipment->shipment_id,
                    'proof' => $merchantPickupShipment->pickup_proof,
                ]);
            } else {
                // Update existing history with proof (only if proof is empty)
                if (empty($history->proof)) {
                    $history->update([
                        'proof' => $merchantPickupShipment->pickup_proof,
                    ]);

                    Log::info('[MerchantPickupShipmentObserver] Updated shipment history with proof', [
                        'history_id' => $history->id,
                        'shipment_id' => $merchantPickupShipment->shipment_id,
                        'proof' => $merchantPickupShipment->pickup_proof,
                    ]);
                }
            }
        }
    }

    /**
     * Handle the MerchantPickupShipment "deleted" event.
     */
    public function deleted(MerchantPickupShipment $merchantPickupShipment): void {}

    /**
     * Handle the MerchantPickupShipment "restored" event.
     */
    public function restored(MerchantPickupShipment $merchantPickupShipment): void
    {
        //
    }

    /**
     * Handle the MerchantPickupShipment "force deleted" event.
     */
    public function forceDeleted(MerchantPickupShipment $merchantPickupShipment): void
    {
        //
    }


    public function saved(MerchantPickupShipment $merchantPickupShipment): void
    {
        is_null($merchantPickupShipment->shipment_id) ?? $this->adminCounterService->broadcastToAllAdmins();
    }
}
