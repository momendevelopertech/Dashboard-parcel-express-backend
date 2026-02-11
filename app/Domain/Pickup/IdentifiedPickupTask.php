<?php

namespace App\Domain\Pickup;

use App\Enums\ShipmentStatusEnum;
use App\Models\MerchantPickupTask;
use App\Models\PickupRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\ApiResponse;
use App\Http\Helpers\SendResponse;
use App\Models\MerchantPickupShipment;
use App\Models\Shipment;
use App\Enums\MerchantPickupTaskStatusEnum;

class IdentifiedPickupTask
{
    private MerchantPickupTask $merchantPickupTask;
    private ?MerchantPickupShipment $merchantPickupShipment = null;
    private ?Shipment $shipment = null;

    public function __construct(MerchantPickupTask $merchantPickupTask)
    {
        $this->merchantPickupTask = $merchantPickupTask->fresh();
        $this->updatePickedShipments($this->merchantPickupTask);
    }

    // Updating the picked shipments number and note
    public function updatePickedShipments(MerchantPickupTask $task)
    {
        if ($task->driver_id !== Auth::id()) {
            return sendResponse(
                "You are not allowed to update this task.",
                [],
                false,
                [],
                403
            );
        }

        $task->loadMissing('shipments');

        // Get the total shipments Number, registered shipments Number and picked shipments Number
        $registeredShipmentsNo = (int) $task->registered_shipments_no;
        $totalShipmentsNo = (int) $task->no_of_shipments;
        $pickedShipmentsNo = (int) $task->picked_shipments_no;
        // $pickedShipmentsNo = (int) $task->shipments()
        //     ->where('status', MerchantPickupTaskStatusEnum::PICKED)
        //     ->count();

        // Update Picked Shipments status of Merchant Pickup Task model
        // $this->updatePickedShipmentStatus($task);

        // Update Picked Shipments count | status of Merchant Pickup Task model | status of Shipments/MerchantPickupShipments
        $this->updatePickupCount($task);
        $this->updatePickupTaskStatus($task);
        // $this->updatePickupTaskShipments($task);

        // 👈 هنا هنجيب ref من جدول merchant_pickup_tasks
        $pickupRequestRef = $task->ref;

        return sendResponse("Pickup task updated successfully.", [
            'task_id' => $task->id,
            'registered_shipments_no' => $registeredShipmentsNo,
            'total_shipments_no' => $totalShipmentsNo,
            'picked_shipments_no' => $pickedShipmentsNo,
            'pickup_request_id' => $task->pickup_request_id,
            'ref' => $pickupRequestRef,
        ]);
    }

    private function updatePickupCount(MerchantPickupTask $task): void
    {
        // Use the calculated accessor value to keep the DB column in sync
        $task->picked_shipments_no = $task->picked_shipments_no;
        $task->save();
    }

    private function updatePickupTaskStatus(MerchantPickupTask $task): void
    {
        $task->refresh();
        // If the picked shipments Number is equal to the total shipments Number, update the task status to completed
        if ($task->picked_shipments_no == $task->no_of_shipments)
        {
            $task->status = MerchantPickupTaskStatusEnum::PICKED;
            $task->completed_at = now();
            $task->save();
        }
    }

    // private function updatePickupTaskShipments(MerchantPickupTask $task): void
    // {
    //     $task->refresh();

    //     if($this->shipment != null)
    //     {
    //         $this->shipment->refresh();
    //         $this->shipment->status = ShipmentStatusEnum::PICKED;
    //         $this->shipment->save();
    //     }

    //     if($this->merchantPickupShipment != null)
    //     {
    //         $this->merchantPickupShipment->refresh();
    //         $this->merchantPickupShipment->status = MerchantPickupTaskStatusEnum::PICKED;
    //         $this->merchantPickupShipment->save();
    //     }
    // }
}
