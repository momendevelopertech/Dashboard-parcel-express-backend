<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use App\Models\Shipment;
use Illuminate\Http\Request;
use App\Models\MerchantCommission;
use Illuminate\Support\Facades\Auth;
use App\Services\NearestDriverService;

class ShipmentObserver
{
    protected $request;

    public function __construct(
        Request $request,
        private NearestDriverService $nearestDriverService,
        private \App\Services\AdminCounterService $adminCounterService,
        private \App\Services\MerchantCommissionService $merchantCommissionService,
        private \App\Services\MerchantTransactionService $merchantTransactionService
    ) {
        $this->request = $request;
    }

    public function created(Shipment $shipment)
    {
        $timestamp = Carbon::now();

        $sortHistoryData = [
            'status'      => 'CREATED',
            'description' => 'Shipment Created',
            'shipment_id' => $shipment->id,
            "time" => $timestamp
        ];

        shipmentHistory($sortHistoryData);

        // DUAL-WRITE: Also record in unified transaction table
        $this->merchantTransactionService->recordRegistration($shipment);

        // Broadcast to admins for Unregistered Shipments counter
        // (If pre_id is set and tracking is empty - though initially tracking might be empty)
        if ($shipment->pre_id && empty($shipment->tracking_no)) {
            $this->adminCounterService->broadcastToAllAdmins();
        }
        
        // Calculate and set payment_type - use saveQuietly to avoid triggering observers
        $calculationService = app(\App\Services\CalculationLogicService::class);
        $totalCod = $calculationService->getTotalCOD($shipment);
        $shipment->payment_type = $totalCod > 0 ? 'COD' : 'Paid';
        $shipment->saveQuietly();
    }



    /**
     * Handle the Shipment "creating" event.
     */
    public function creating(Shipment $shipment)
    {
        $facility = facility();

        if ($facility) {
            $shipment->owner_type = $facility->type;
            $shipment->owner_id = $facility->id;
        }
    }

    /**
     * Handle the Shipment "updating" event.
     */
    public function updating(Shipment $shipment)
    {
        // Preserve ownership if already set
        if ($shipment->owner_id && $shipment->owner_type) {
            return;
        }

        $facility = facility();

        if ($facility) {
            $shipment->owner_type = $facility->type;
            $shipment->owner_id = $facility->id;
        }
    }
    /**
     * Handle the Shipment "updated" event.
     */
    public function updated(Shipment $shipment): void
    {
        if (!$shipment->isDirty('status')) {
            return;
        }
        // Ensure unified transactions are recorded when delivered
        if (strcasecmp((string)$shipment->status, 'DELIVERED') === 0) {
            // Priority: Record in unified transaction table
            $this->merchantTransactionService->recordShipmentFees($shipment);
            $this->merchantTransactionService->recordCOD($shipment);
        }

        // Handle RETURNED status
        if (strcasecmp((string)$shipment->status, 'RETURNED') === 0) {
            $this->merchantTransactionService->recordReturnFees($shipment);
        }

        // Check if fields relevant to "Unregistered" status changed
        if ($shipment->isDirty('pre_id') || $shipment->isDirty('tracking_no')) {
            $this->adminCounterService->broadcastToAllAdmins();
        }
        
        // Only update payment_type if it's not already being updated (prevent infinite loop)
        if (!$shipment->isDirty('payment_type')) {
            $calculationService = app(\App\Services\CalculationLogicService::class);
            $totalCod = $calculationService->getTotalCOD($shipment);
            $newPaymentType = $totalCod > 0 ? 'COD' : 'Paid';
            
            // Use saveQuietly to avoid triggering observers again
            if ($shipment->payment_type !== $newPaymentType) {
                $shipment->payment_type = $newPaymentType;
                $shipment->saveQuietly();
            }
        }
    }

    
}
