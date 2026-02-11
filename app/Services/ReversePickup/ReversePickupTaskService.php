<?php

namespace App\Services\ReversePickup;

use App\Models\ReversePickupTask;
use App\Models\ReversePickupShipment;
use App\Models\ReverseShipment;
use App\Enums\ReversePickupTaskStatusEnum;
use App\Enums\ReverseShipmentStatusEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReversePickupTaskService
{
    public function __construct(
        private ReversePickupFinancialService $financialService
    ) {}

    /**
     * Create reverse pickup task and assign shipments to driver
     *
     * @param array $data Task data [merchant_id, driver_id, no_of_shipments, note, reverse_pickup_request_id, owner_id, owner_type]
     * @param array $reverseShipmentIds Array of reverse_shipment IDs to assign
     * @return ReversePickupTask
     * @throws \Exception
     */
    public function createTaskAndShipments(array $data, array $reverseShipmentIds = []): ReversePickupTask
    {
        return DB::transaction(function () use ($data, $reverseShipmentIds) {
            // Create the task
            $task = ReversePickupTask::create([
                'merchant_id' => $data['merchant_id'],
                'driver_id' => $data['driver_id'],
                'no_of_shipments' => $data['no_of_shipments'] ?? count($reverseShipmentIds),
                'note' => $data['note'] ?? null,
                'status' => $data['status'] ?? ReversePickupTaskStatusEnum::TO_PICKUP,
                'reverse_pickup_request_id' => $data['reverse_pickup_request_id'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'owner_type' => $data['owner_type'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? now(),
                'assigned_by' => $data['assigned_by'] ?? Auth::id(),
            ]);

            // Assign reverse shipments to this task
            foreach ($reverseShipmentIds as $reverseShipmentId) {
                $reverseShipment = ReverseShipment::find($reverseShipmentId);

                if (!$reverseShipment) {
                    continue;
                }

                // Create reverse pickup shipment entry
                ReversePickupShipment::create([
                    'reverse_pickup_task_id' => $task->id,
                    'reverse_pickup_request_id' => $data['reverse_pickup_request_id'] ?? null,
                    'reverse_shipment_id' => $reverseShipment->id,
                    'driver_id' => $data['driver_id'],
                    'merchant_id' => $data['merchant_id'],
                    'status' => 'to_pickup',
                ]);

                // Update reverse shipment status
                $reverseShipment->update([
                    'status' => ReverseShipmentStatusEnum::REVERSE_ASSIGNED,
                ]);

                // Create shipment history
                if (function_exists('shipmentHistory')) {
                    shipmentHistory([
                        'status' => 'Assigned for Reverse Pickup',
                        'description' => "Assigned to driver {$task->driver->name} for reverse pickup",
                        'shipment_id' => $reverseShipment->id,
                        'created_at' => now(),
                    ]);
                }
            }

            return $task;
        });
    }

    /**
     * Mark shipment as picked up by driver
     * This triggers driver commission credit (Step 3 in scenario)
     *
     * @param int $reversePickupShipmentId
     * @param string|null $proofPath
     * @return void
     * @throws \Exception
     */
    public function markAsPicked(int $reversePickupShipmentId, ?array $proofPaths = null): void
    {
        DB::transaction(function () use ($reversePickupShipmentId, $proofPaths) {
            $reversePickupShipment = ReversePickupShipment::findOrFail($reversePickupShipmentId);

            // Update reverse pickup shipment status
            $reversePickupShipment->update([
                'status' => 'picked',
                'pickup_proofs' => json_encode($proofPaths),
            ]);

            // Update reverse shipment status
            $reverseShipment = $reversePickupShipment->reverseShipment;
            $reverseShipment->update([
                'status' => ReverseShipmentStatusEnum::REVERSE_PICKED_UP,
                'picked_at' => now(),
            ]);

            // IMPORTANT: Credit driver commission immediately (Step 3)
            // $this->financialService->creditDriverCommission($reverseShipment->id);

            // Create shipment history
            if (function_exists('shipmentHistory')) {
                shipmentHistory([
                    'status' => 'Reverse Picked Up',
                    'description' => 'Shipment picked up from customer for return to merchant',
                    'shipment_id' => $reverseShipment->id,
                    'created_at' => now(),
                ]);
            }

            // Update task picked count
            $task = $reversePickupShipment->reversePickupTask;
            $data = [
                'picked_shipments_no' => $task->pickedShipments()->count(),
            ];
            if ($task->no_of_shipments === $data['picked_shipments_no']) {
                $data['status'] = ReversePickupTaskStatusEnum::COMPLETED;
            }
            if ($task) {
                $task->update([
                    'picked_shipments_no' => $task->pickedShipments()->count(),
                    'status' => $data['status'] ?? ReversePickupTaskStatusEnum::PICKUP,
                ]);
            }
        });
    }

    /**
     * Mark shipment as at hub
     * No financial transaction (Step 4)
     *
     * @param int $reverseShipmentId
     * @return void
     */
    public function markAsAtHub($reverseShipment, ?string $note = null, ?string $proofPath = null): void
    {
        DB::transaction(function () use ($reverseShipment, $note, $proofPath) {

            $reverseShipment->update([
                'status' => ReverseShipmentStatusEnum::REVERSE_AT_HUB,
            ]);

            // Create shipment history
            if (function_exists('shipmentHistory')) {
                $description = 'Reverse shipment arrived at hub/warehouse';
                if ($note) {
                    $description .= ' - Note: ' . $note;
                }

                shipmentHistory([
                    'status' => 'At Hub',
                    'description' => $description,
                    'shipment_id' => $reverseShipment->id,
                    'created_at' => now(),
                    'proof' => $proofPath,
                ]);
            }
        });
    }
}
