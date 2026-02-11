<?php

namespace App\Services\Return;

use App\Models\ReturnRequest;
use App\Models\Shipment;
use App\Models\PickupTask;
use App\Models\DriverRunsheetShipment;
use App\Models\DriverShipmentAssignment;
use App\Models\User;
use App\Enums\ShipmentStatusEnum;
use App\Services\Return\ReturnPricingService;
use App\Services\Return\ReturnFinancialService;
use App\Services\MerchantPickupWhatsappMessegingService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domain\Pickup\ShipmentPickupFactory;


/**
 * ReturnShipmentService
 * 
 * Handles return shipment operations using unified architecture.
 * Replaces the old ReverseShipmentService with simplified logic.
 * 
 * Key Changes:
 * - Creates shipments with is_return=true instead of separate reverse_shipments table
 * - Uses single tracking number format (not RET- prefix)
 * - Enables per-shipment driver assignment (not per-request)
 * - No dual tracking or sync logic needed
 */
class ReturnShipmentService
{
    public function __construct(
        private ReturnPricingService $pricingService,
        private ReturnFinancialService $financialService
    ) {
    }

    /**
     * Create return pickup request with shipments
     * 
     * Creates a return request and associated shipments in ONE table.
     * Each shipment has is_return=true and return_type='reverse_pickup'.
     * 
     * @param array $data Request data with details array containing count per customer
     * @return ReturnRequest
     */
    public function createReturnPickupRequest(array $data): ReturnRequest
    {
        return DB::transaction(function () use ($data) {
            // Create return request
            $returnRequest = ReturnRequest::create([
                'merchant_id' => $data['merchant_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'pickup_address_id' => $data['pickup_address_id'] ?? null,
                'delivery_address_id' => $data['delivery_address_id'] ?? null,
                'no_of_shipments' => 0, // Will be updated after creating shipments
                'scheduled_at' => $data['scheduled_at'] ?? now(),
                'note' => $data['note'] ?? null,
                'owner_id' => $data['owner_id'] ?? Auth::id(),
                'owner_type' => $data['owner_type'] ?? User::class,
            ]);

            $shipmentsCreated = 0;

            // Create shipments for each item in the request
            foreach ($data['details'] as $detail) {
                $count = $detail['count'] ?? 1;

                for ($i = 0; $i < $count; $i++) {
                    // Calculate pricing
                    $stateId = $detail['state_id'] ?? null;
                    $pricing = $stateId
                        ? $this->pricingService->calculateReturnFee($data['merchant_id'], $stateId)
                        : ['base_return' => 0, 'return_discount' => 0, 'final_return_fee' => 0];


                    // Create or find consignee for the customer (pickup location)
                    $consignee = \App\Models\Consignee::firstOrCreate(
                        [
                            'cellphone' => $detail['customer_phone'],
                        ],
                        [
                            'name' => $detail['customer_name'] ?? 'Customer',
                            'country_id' => $detail['country_id'] ?? null,
                            'governorate_id' => $detail['governorate_id'] ?? null,
                            'state_id' => $detail['state_id'] ?? null,
                            'place_id' => $detail['place_id'] ?? null,
                            'streetAddress' => $detail['customer_address'] ?? null,
                            'latitude' => $detail['latitude'] ?? null,
                            'longitude' => $detail['longitude'] ?? null,
                            'location_url' => $detail['location_url'] ?? null,
                        ]
                    );


                    // Get merchant for validation
                    $user = \App\Models\User::find($data['merchant_id']);
                    $merchant = $user->merchant ?? null;
                    if (!$merchant) {
                        throw new \Exception("Merchant not found with ID: {$data['merchant_id']}");
                    }

                    $deliveryAddressId = null; // Merchant location is in merchant table, not addresses
            

                    // Create shipment with is_return=true
                    $shipment = Shipment::create([
                        // Return-specific fields
                        'is_return' => true,
                        'return_request_id' => $returnRequest->id,
                        'return_to_type' => 'merchant',
                        'return_to_id' => $data['merchant_id'],

                        // Return fees
                        'return_fee_before_discount' => $pricing['base_return'],
                        'return_fee_discount' => $pricing['return_discount'],
                        'return_fee' => $pricing['final_return_fee'],
                        'return_fee_source' => 'merchant_commission',

                        // Standard shipment fields
                        'tracking_no' => $this->generateTrackingNumber(),
                        'status' => ShipmentStatusEnum::CREATED,
                        'merchant_id' => $data['merchant_id'],
                        'customer_id' => $detail['customer_id'] ?? null,
                        'consignee_id' => $consignee->id, // Link to consignee for zone resolution

                        // Customer information (for display)
                        'customer_name' => $detail['customer_name'] ?? null,
                        'customer_phone' => $detail['customer_phone'] ?? null,

                        // Pickup location (will use consignee data in zone resolution)
                        'pickup_address_id' => null, // Not needed - consignee has the address

                        // Delivery location (merchant's address)
                        'delivery_address_id' => $deliveryAddressId,

                        // Location details (from consignee)
                        'state_id' => $stateId,
                        'governorate_id' => $detail['governorate_id'] ?? null,
                        'place_id' => $detail['place_id'] ?? null,

                        // Ownership
                        'owner_id' => $data['owner_id'] ?? Auth::id(),
                        'owner_type' => $data['owner_type'] ?? User::class,

                        // Notes
                        'notes' => $detail['note'] ?? null,
                    ]);

                    // Create ShipmentDelivery record (required for assignment workflow)
                    \App\Models\ShipmentDelivery::create([
                        'shipment_id' => $shipment->id,
                        'ofd_count' => 0,
                        'failed_count' => 0,
                    ]);

                    // Create shipment history
                    if (function_exists('shipmentHistory')) {
                        shipmentHistory([
                            'status' => 'Return Request Created',
                            'description' => "Return pickup request created by merchant",
                            'shipment_id' => $shipment->id,
                            'created_at' => now(),
                        ]);
                    }

                    $shipmentsCreated++;
                }
            }

            // Update return request with actual shipment count
            $returnRequest->update([
                'no_of_shipments' => $shipmentsCreated,
            ]);

            // Send notifications
            $this->notify('created', $returnRequest);

            return $returnRequest->load('shipments');
        });
    }

    /**
     * Assign shipments to driver (per-shipment assignment)
     * 
     * Key Change: Assigns individual shipments, not entire request.
     * Enables multiple drivers for different zones.
     * 
     * @param array $shipmentIds Array of shipment IDs to assign
     * @param int $driverId
     * @return array Created pickup tasks
     */
    public function assignShipmentsToDriver(array $shipmentIds, int $driverId): array
    {
        return DB::transaction(function () use ($shipmentIds, $driverId) {
            $tasks = [];

            foreach ($shipmentIds as $shipmentId) {
                $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                    ->findOrFail($shipmentId);

                // Validate it's a return shipment
                if (!$shipment->is_return) {
                    throw new \Exception("Shipment {$shipment->tracking_no} is not a reverse pickup return");
                }

                // Create pickup task
                $task = PickupTask::create([
                    'shipment_id' => $shipment->id,
                    'driver_id' => $driverId,
                    'zone_id' => $shipment->zone_id,
                    'return_request_id' => $shipment->return_request_id,
                    'status' => 'assigned',
                    'scheduled_at' => now(),
                ]);

                // Update shipment status
                $shipment->update([
                    'status' => ShipmentStatusEnum::TO_PICKUP,
                    'driver_id' => $driverId,
                ]);

                // Create shipment history
                if (function_exists('shipmentHistory')) {
                    shipmentHistory([
                        'status' => 'Assigned for Pickup',
                        'description' => "Assigned to driver for reverse pickup",
                        'shipment_id' => $shipment->id,
                        'created_at' => now(),
                    ]);
                }

                $tasks[] = $task;
            }

            // Notify driver
            $this->notifyDriverPickupAssigned($driverId);

            return $tasks;
        });
    }

    /**
     * Assign shipments by zone (batch assignment)
     * 
     * Assigns all shipments in a zone to a driver.
     * 
     * @param int $returnRequestId
     * @param int $zoneId
     * @param int $driverId
     * @return array Created pickup tasks
     */
    public function assignShipmentsByZone(int $returnRequestId, int $zoneId, int $driverId): array
    {
        $shipments = Shipment::where('return_request_id', $returnRequestId)
            ->where('zone_id', $zoneId)
            ->where('is_return', true)
            ->whereNull('driver_id') // Not yet assigned
            ->get();

        $shipmentIds = $shipments->pluck('id')->toArray();

        return $this->assignShipmentsToDriver($shipmentIds, $driverId);
    }

    /**
     * Mark shipment as picked up
     * 
     * Simplified: Just update shipment status, no dual tracking.
     * 
     * @param int $shipmentId
     * @param array|null $proofPaths
     * @return void
     */
    public function markAsPicked(int $shipmentId, ?array $proofPaths = null): void
    {
        DB::transaction(function () use ($shipmentId, $proofPaths) {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->findOrFail($shipmentId);

            // Update shipment status
            $shipment->update([
                'status' => ShipmentStatusEnum::PICKED,
                'picked_at' => now(),
            ]);

            // Update pickup task
            $pickupTask = $shipment->pickupTask;
            if ($pickupTask) {
                $pickupTask->update([
                    'status' => 'picked',
                    'picked_at' => now(),
                ]);
            }

            // Save proof images
            if ($proofPaths) {
                foreach ($proofPaths as $proofPath) {
                    $shipment->proofs()->create([
                        'type' => 'pickup',
                        'path' => $proofPath,
                    ]);
                }
            }

            // Create shipment history
            if (function_exists('shipmentHistory')) {
                shipmentHistory([
                    'status' => 'Picked Up',
                    'description' => 'Return shipment picked up from customer',
                    'shipment_id' => $shipment->id,
                    'created_at' => now(),
                ]);
            }

            // Credit driver commission
            $this->financialService->creditDriverCommission($shipment->id);

            // Add pickup bonus
            if ($shipment->driver_id) {
                ShipmentPickupFactory::addPickupBonus($shipment, $shipment->driver_id);
            }
        });
    }

    /**
     * Mark shipment as at hub
     * 
     * @param int $shipmentId
     * @param string|null $note
     * @return void
     */
    public function markAsAtHub(int $shipmentId, ?string $note = null): void
    {
        DB::transaction(function () use ($shipmentId, $note) {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->findOrFail($shipmentId);

            $timestamp = now();
            $activeDriverId = $shipment->driver_id;

            if (!$shipment->shipment_information) {
                $shipment->shipment_information()->create([
                    'shipment_id' => $shipment->id,
                    'tracking_no' => $shipment->tracking_no,
                    'zone_id' => null,
                    'in_warehouse' => true,
                ]);
            } else {
                $shipment->shipment_information->update([
                    'in_warehouse' => true,
                ]);
            }

            // Close first-leg assignment (pickup from customer) so shipment can be re-assigned
            $runsheetRows = DriverRunsheetShipment::where('shipment_tracking_no', $shipment->tracking_no)
                ->whereIn('status', ['pending', 'assigned', 'confirmed']);

            if ($activeDriverId) {
                $runsheetRows->where('driver_id', $activeDriverId);
            }

            $runsheetRows->update(['status' => 'returned']);

            DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->whereNull('returned_at')
                ->whereNull('delivered_at')
                ->update([
                    'returned_at' => $timestamp,
                    'status' => 'RETURNED',
                ]);

            // Update shipment status and clear active driver reference for second-leg assignment
            $shipment->update([
                'status' => ShipmentStatusEnum::ORDER_INBOUNDED,
                'driver_id' => null,
                'assignment_id' => null,
            ]);

            $pickupTask = $shipment->pickupTask;
            if ($pickupTask) {
                $pickupTask->update([
                    'status' => 'at_hub',
                    'note' => $note ?: $pickupTask->note,
                ]);
            }

            // Create shipment history
            if (function_exists('shipmentHistory')) {
                $description = 'Return shipment arrived at hub';
                if ($note) {
                    $description .= ' - ' . $note;
                }

                shipmentHistory([
                    'status' => 'At Hub',
                    'description' => $description,
                    'shipment_id' => $shipment->id,
                    'created_at' => now(),
                ]);
            }
        });
    }

    /**
     * Mark shipment as delivered to merchant
     * 
     * Final step: Debit merchant wallet for return fee.
     * 
     * @param int $shipmentId
     * @return void
     */
    public function markAsDeliveredToMerchant(int $shipmentId): void
    {
        DB::transaction(function () use ($shipmentId) {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->findOrFail($shipmentId);

            // Update shipment status
            $shipment->update([
                'status' => ShipmentStatusEnum::DELIVERED,
                'delivered_at' => now(),
            ]);

            // Create shipment history
            if (function_exists('shipmentHistory')) {
                shipmentHistory([
                    'status' => 'Delivered',
                    'description' => 'Return shipment delivered to merchant',
                    'shipment_id' => $shipment->id,
                    'created_at' => now(),
                ]);
            }

            // Debit merchant wallet
            $this->financialService->debitMerchantWallet($shipment->id);

            // Update return request if all shipments delivered
            $this->updateReturnRequestStatus($shipment->return_request_id);
        });
    }

    /**
     * Cancel return shipment
     * 
     * @param int $shipmentId
     * @param string|null $reason
     * @return void
     */
    public function cancelReturnShipment(int $shipmentId, ?string $reason = null): void
    {
        DB::transaction(function () use ($shipmentId, $reason) {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->findOrFail($shipmentId);

            // Update shipment status
            $shipment->update([
                'status' => ShipmentStatusEnum::CANCELLED,
            ]);

            // Create shipment history
            if (function_exists('shipmentHistory')) {
                $description = 'Return shipment cancelled';
                if ($reason) {
                    $description .= ' - Reason: ' . $reason;
                }

                shipmentHistory([
                    'status' => 'Cancelled',
                    'description' => $description,
                    'shipment_id' => $shipment->id,
                    'created_at' => now(),
                ]);
            }

            // Reverse any financial transactions
            $this->financialService->reverseTransactions($shipment->id);
        });
    }

    /**
     * Update return request status based on shipments
     * 
     * @param int $returnRequestId
     * @return void
     */
    private function updateReturnRequestStatus(int $returnRequestId): void
    {
        $returnRequest = ReturnRequest::find($returnRequestId);
        if (!$returnRequest) {
            return;
        }

        $shipments = $returnRequest->shipments;
        $totalShipments = $shipments->count();
        $deliveredShipments = $shipments->where('status', ShipmentStatusEnum::DELIVERED)->count();
        $pickedShipments = $shipments->whereIn('status', [
            ShipmentStatusEnum::PICKED,
            ShipmentStatusEnum::ORDER_INBOUNDED,
            ShipmentStatusEnum::ORDER_SORTED,
            ShipmentStatusEnum::DISPATCH,
            ShipmentStatusEnum::OFD,
            ShipmentStatusEnum::DELIVERED,
        ])->count();

        // Update picked count
        $returnRequest->update([
            'picked_shipments_no' => $pickedShipments,
        ]);

        // Update status
        if ($deliveredShipments === $totalShipments) {
            $returnRequest->update(['status' => 'completed']);
        } elseif ($pickedShipments > 0) {
            $returnRequest->update(['status' => 'in_progress']);
        }
    }

    /**
     * Generate tracking number for return shipment
     * 
     * Uses regular format (not RET- prefix)
     * 
     * @return string
     */
    private function generateTrackingNumber(): string
    {
        do {
            $trackingNo = 'RET' . date('ymd') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (
            Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->where('tracking_no', $trackingNo)->exists()
        );

        return $trackingNo;
    }

    /**
     * Send notifications
     * 
     * @param string $type
     * @param ReturnRequest|null $returnRequest
     * @return void
     */
    private function notify(string $type, ?ReturnRequest $returnRequest = null): void
    {
        if (!$returnRequest) {
            return;
        }

        try {
            // Send app notification
            $this->sendAppMessage($returnRequest);

            // Send WhatsApp notification
            // $this->sendWhatsAppMessage($returnRequest);
        } catch (\Exception $e) {
            Log::error("Failed to send notification: " . $e->getMessage());
        }
    }

    /**
     * Send app notification
     * 
     * @param ReturnRequest $returnRequest
     * @return void
     */
    private function sendAppMessage(ReturnRequest $returnRequest): void
    {
        // Implementation depends on your notification system
        // This is a placeholder
    }

    /**
     * Notify driver of pickup assignment
     * 
     * @param int $driverId
     * @return void
     */
    private function notifyDriverPickupAssigned(int $driverId): void
    {
        // Implementation depends on your notification system
        // This is a placeholder
    }
}
