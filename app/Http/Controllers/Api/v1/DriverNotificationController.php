<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DriverShipmentAssignment;
use App\Models\MerchantPickupTask;
use App\Models\MerchantPickupShipment;
use App\Enums\MerchantPickupTaskStatusEnum;
use App\Enums\ShipmentStatusEnum;

class DriverNotificationController extends Controller
{
    /**
     * Get unified driver notifications (tasks and rewards).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $driverId = Auth::id();

        // 1. New Delivery Tasks (Pending Assignments)
        // Logic: DriverShipmentAssignment where driver_id = auth, not delivered, status is active/assigned
        $deliveryTasks = DriverShipmentAssignment::with(['shipment'])
            ->where('driver_id', $driverId)
            ->whereNull('delivered_at')
            ->whereIn('status', ['ASSIGNED', 'DISPATCH', 'OFD', 'IN_TRANSIT']) // Adjust statuses based on project flow
            ->get()
            ->map(function ($assignment) {
                return [
                    'task_id' => $assignment->id,
                    'shipment_tracking_no' => $assignment->shipment_tracking_no,
                    'status' => $assignment->status,
                    'created_at' => $assignment->created_at->toDateTimeString(),
                    'consignee_name' => $assignment->shipment->consignee->name ?? null,
                    'address' => $assignment->shipment->deliveryAddress->address ?? null,
                ];
            });

        // 2. New Pickup Tasks (Pending Pickups)
        // Logic: MerchantPickupTask where driver_id = auth, status is TO_PICKUP
        $pickupTasks = MerchantPickupTask::with(['merchant', 'pickupRequest'])
            ->where('driver_id', $driverId)
            ->whereIn('status', [MerchantPickupTaskStatusEnum::TO_PICKUP, MerchantPickupTaskStatusEnum::PENDING])
            ->get()
            ->map(function ($task) {
                return [
                    'task_id' => $task->id,
                    'pickup_request_ref' => $task->pickupRequest->ref ?? null,
                    'merchant_name' => $task->merchant->name ?? null,
                    'no_of_shipments' => (int) $task->no_of_shipments,
                    'status' => $task->status,
                    'created_at' => $task->created_at->toDateTimeString(),
                    'address' => $task->merchant->address ?? null,
                ];
            });

        // 3. Delivery Rewards Due
        // Logic: Delivered shipments where driver commission > 0 and invoice/settlement is pending
        // Reusing logic from DriverFinanceController::not_settled_shipments
        $deliveryRewards = DriverShipmentAssignment::with(['shipment.shipment_finance'])
            ->where('driver_id', $driverId)
            ->whereNotNull('delivered_at')
            ->whereHas('shipment', function ($q) {
                $q->whereHas('invoice_shipment', function ($q2) {
                    $q2->where('status', 'pending');
                })->orWhereDoesntHave('invoice_shipment'); // Also include if not invoiced yet? Assuming pending invoice or no invoice means due.
            })
            ->whereHas('shipment', function ($q) {
                $q->whereHas('shipment_finance', function ($financeQuery) {
                    $financeQuery->whereNotNull('driver_delivery_fee')
                        ->where('driver_delivery_fee', '>', 0);
                });
            })
            ->get()
            ->map(function ($assignment) {
                return [
                    'shipment_tracking_no' => $assignment->shipment_tracking_no,
                    'reward_amount' => (float) $assignment->shipment->shipment_finance->driver_delivery_fee,
                    'currency' => 'OMR', // Assuming default currency
                    'status' => 'due',
                    'delivered_at' => $assignment->delivered_at->toDateTimeString(),
                ];
            });

        // 4. Pickup Rewards Due
        // Logic: Picked shipments where driver commission > 0 and not settled
        // Assuming pickup rewards are stored in shipment_finance linked to pickup_shipment_id or similar
        // Based on DriverFinanceController, it seems focused on delivery. I will adapt for pickup.
        // If pickup fee is in driver_delivery_fee (unlikely) or another column.
        // Checking MerchantPickupShipment linked to ShipmentFinance.
        $pickupRewards = MerchantPickupShipment::with(['shipment_finance'])
            ->where('driver_id', $driverId)
            ->where('status', MerchantPickupTaskStatusEnum::PICKED) // Or whatever status means "done"
            ->whereHas('shipment_finance', function ($q) {
                 // Assuming there's a fee column for pickup, or reusing driver_delivery_fee if it's a pickup-only job?
                 // The user prompt said "Reuse the same reward / finance models... but for the pickup flow".
                 // I'll check if 'driver_delivery_fee' is used for pickup too, or if I should look for something else.
                 // For now, I will assume 'driver_delivery_fee' on the finance record linked to the pickup shipment.
                 $q->where('driver_delivery_fee', '>', 0);
            })
             // Add settlement check if applicable (e.g. invoice_shipment status)
            ->get()
            ->map(function ($pickupShipment) {
                return [
                    'shipment_tracking_no' => $pickupShipment->shipment_tracking_no,
                    'reward_amount' => (float) ($pickupShipment->shipment_finance->driver_delivery_fee ?? 0),
                    'currency' => 'OMR',
                    'status' => 'due',
                    'picked_at' => $pickupShipment->updated_at->toDateTimeString(), // Approximation
                ];
            });


        return sendResponse("Driver notifications retrieved successfully.", [
            'delivery_tasks' => $deliveryTasks,
            'pickup_tasks' => $pickupTasks,
            'delivery_rewards' => $deliveryRewards,
            'pickup_rewards' => $pickupRewards,
        ]);
    }
}
