<?php

namespace App\Observers;


use App\Models\TransferShipment;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransferShipmentObserver
{
    protected $adminCounterService;

    public function __construct(\App\Services\AdminCounterService $adminCounterService)
    {
        $this->adminCounterService = $adminCounterService;
    }

    public function creating(TransferShipment $transfer_shipment)
    {
        $facility = facility();

        if ($facility) {
            $transfer_shipment->ownership_type = $facility->type;
            $transfer_shipment->ownership_id = $facility->id;
        }
    }

    public function created(TransferShipment $transferShipment): void
    {
        // Broadcast counter update when a new transfer shipment is created
        if ($transferShipment->status === 'pending') {
            $this->adminCounterService->broadcastToAllAdmins();
        }
    }

    public function updating(TransferShipment $transfer_shipment)
    {
        $facility = facility();

        if ($facility) {
            $transfer_shipment->ownership_type = $facility->type;
            $transfer_shipment->ownership_id = $facility->id;
        }
    }

    public function updated(TransferShipment $transferShipment): void
    {
        // Broadcast when status changes (e.g., pending -> assigned)
        if ($transferShipment->wasChanged('status')) {
            $this->adminCounterService->broadcastToAllAdmins();
        }
    }

    public function deleted(TransferShipment $transferShipment): void
    {
        // Broadcast when a transfer shipment is deleted
        if ($transferShipment->status === 'pending') {
            $this->adminCounterService->broadcastToAllAdmins();
        }
    }
}
