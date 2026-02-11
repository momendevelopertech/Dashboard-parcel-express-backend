<?php

namespace App\Services;

use App\Models\AssignShipmentToShelf;
use App\Models\DriverShipmentAssignment;
use App\Models\DriverRunsheetShipment;
use App\Models\Shipment;
use App\Models\TransferShipment;
use App\Models\TransferTask;
use App\Models\TransferTaskShipment;
use App\Models\ShipmentHistory;
use Log;
use Illuminate\Support\Facades\DB;

class ShipmentValidationService
{
    /** 
     * check if the shipment is already delivered.
     */

    public function isShipmentDelivered(Shipment $shipment)
    {
        $coreStatusName = $shipment->coreStatus()->name ?? 'null';
        $shipmentStatus = $shipment->status ?? 'null';
        $isDelivered = strtolower($coreStatusName) == strtolower("delivered") || strtolower($shipmentStatus) == strtolower("delivered");



        return $isDelivered;
    }

    public function isShipmentReturned(Shipment $shipment)
    {
        $coreStatusName = $shipment->coreStatus()->name ?? 'null';
        $shipmentStatus = $shipment->status ?? 'null';
        $isReturned = strtolower($coreStatusName) == strtolower("returned") || strtolower($shipmentStatus) == strtolower("returned");



        return $isReturned;
    }

    /**
     * check if the shipment is already assigned to driver and not returned.
     */
    public function isShipmentAssigned(Shipment $shipment, $driverId = null)
    {
        $query = DriverRunsheetShipment::where('shipment_tracking_no', $shipment->tracking_no);

        if ($driverId) {
            $query->where('driver_id', $driverId);
        }

        $query->where('status', "!=", "returned");
        $exists = $query->exists();



        return $exists;
    }

    /**
     * check if the shipment is already confirmed by the driver
     */
    public function isShipmentConfirmed(Shipment $shipment, $driverId = null)
    {
        $query = DriverRunsheetShipment::where('shipment_tracking_no', $shipment->tracking_no);

        if ($driverId) {
            $query->where('driver_id', $driverId);
        }

        $query->where('status', "confirmed");
        $exists = $query->exists();



        return $exists;
    }

    /**
     * check if the shipment is already in runsheet.
     */
    public function isShipmentInRunsheet(Shipment $shipment, $driverId = null)
    {
        $query = DriverRunsheetShipment::where('shipment_tracking_no', $shipment->tracking_no)
            ->whereIn('status', ['assigned', 'confirmed', 'delivered']);

        if ($driverId) {
            $query->where('driver_id', $driverId);
        }

        $exists = $query->exists();

        return $exists;
    }

    /**
     * check if the shipment is assigned from the current facility
     */
    public function isShipmentAssignedFromCurrentFacility(Shipment $shipment)
    {
        $currentFacilityType = facility("type");
        $currentFacilityId = facility("id");
        $shipmentOwnerType = $shipment->owner_type;
        $shipmentOwnerId = $shipment->owner_id;

        $isAssignedFromCurrentFacility = !($shipmentOwnerType == $currentFacilityType || $shipmentOwnerId == $currentFacilityId);



        return $isAssignedFromCurrentFacility;
    }

    /** 
     * check if the shipment is sorted.
     */
    public function isShipmentSorted(Shipment $shipment)
    {
        $ownerType = $shipment->owner_type;
        $ownerId = $shipment->owner_id;
        $isSorted = $shipment->is_sorted;

        $temp = $ownerType && $ownerId && $isSorted;



        return $temp;
    }

    /**
     * Shipment is already in exception
     */
    public function isShipmentInException(Shipment $shipment)
    {
        $inException = $shipment->in_exception;



        return $inException;
    }

    /** 
     * Shipment is out for delivery
     */
    public function isShipmentOFD(Shipment $shipment)
    {
        $driverId = $shipment->driver_id;
        $isOFD = $driverId != null;



        return $isOFD;
    }

    /**
     * is transfer shipment which needs to be transfered to another facility
     */
    public function isAnShipmentToBeTransferred(Shipment $shipment)
    {
        $transferTaskShipment = TransferTaskShipment::where('shipment_tracking_no', $shipment->tracking_no)->first();
        $currentFacilityType = facility("type");
        $currentFacilityId = facility("id");

        $transferTaskShipmentStatus = $transferTaskShipment ? $transferTaskShipment->status : null;
        $transferTaskShipmentCompleted = $transferTaskShipment ? ($transferTaskShipment->status == "completed") : false;

        $transferShipmentExists = TransferShipment::where('shipment_tracking_no', $shipment->tracking_no)
            ->where('ownership_type', $currentFacilityType)
            ->where('ownership_id', $currentFacilityId)
            ->exists();

        $isToBeTransferred = $transferTaskShipmentCompleted || $transferShipmentExists;



        return $isToBeTransferred;
    }

    /**
     * Check if shipment can have delivery exception created
     * 
     * Shipment must meet ALL these conditions:
     * 1. Not delivered
     * 2. Not already in exception
     * 3. Must be assigned to a driver
     * 4. Must be confirmed by driver (OFD status)
     * 5. Must not be in warehouse
     * 6. Must not be assigned to shelf
     * 7. Must not be RTO status
     * 8. Must not be in transfer task
     * 
     * @param Shipment $shipment
     * @return bool
     */
    public function canCreateDeliveryException(Shipment $shipment)
    {
        // Check if shipment is delivered
        if ($this->isShipmentDelivered($shipment)) {
            return false;
        }

        // Check if shipment is already in exception
        if ($this->isShipmentInException($shipment)) {
            return false;
        }

        // Check if shipment is assigned to a driver
        if (!$this->isShipmentAssigned($shipment)) {
            return false;
        }

        // Check if shipment is confirmed by driver (OFD status)
        if (!$this->isShipmentOFD($shipment)) {
            return false;
        }

        // Check if shipment is in warehouse
        if ($this->isShipmentInWarehouse($shipment)) {
            return false;
        }

        // Check if shipment is assigned to shelf
        if ($this->isShipmentAssignedToShelf($shipment)) {
            return false;
        }

        // Check if shipment is RTO status
        if ($this->isShipmentRTO($shipment)) {
            return false;
        }

        // Check if shipment is in transfer task
        if ($this->isShipmentInTransferTask($shipment)) {
            return false;
        }

        return true;
    }

    /**
     * Check if shipment is in warehouse
     * 
     * @param Shipment $shipment
     * @return bool
     */
    public function isShipmentInWarehouse(Shipment $shipment)
    {
        $inWarehouse = $shipment->shipment_information?->in_warehouse ?? false;



        return $inWarehouse;
    }

    /**
     * Check if shipment is assigned to shelf
     * 
     * @param Shipment $shipment
     * @return bool
     */
    // public function isShipmentAssignedToShelf(Shipment $shipment)
    // {
    //     $assignedToShelf = $shipment->assigned_to_shelf !== null;

    //     Log::info("ShipmentValidationService::isShipmentAssignedToShelf", [
    //         'shipment_id' => $shipment->id,
    //         'tracking_no' => $shipment->tracking_no,
    //         'assigned_to_shelf' => $assignedToShelf
    //     ]);

    //     return $assignedToShelf;
    // }

    /**
     * Check if shipment is RTO (Return to Origin) status
     * 
     * @param Shipment $shipment
     * @return bool
     */
    public function isShipmentRTO(Shipment $shipment)
    {
        $status = strtoupper((string) ($shipment->status ?? ''));
        if (in_array($status, ['RTO', 'RTO_PICKED', 'RTO_LOADED'], true)) {
            return true;
        }

        $rtoMarkers = ['RTO', 'RETURN_TO_ORIGIN', 'RETURNED_TO_ORIGIN'];

        $coreException = $shipment->relationLoaded('core_exception')
            ? $shipment->core_exception
            : $shipment->core_exception()->first();
        if ($coreException) {
            $exceptionType = strtoupper((string) ($coreException->type ?? ''));
            $exceptionName = strtoupper((string) ($coreException->name ?? ''));
            if (in_array($exceptionType, $rtoMarkers, true) || in_array($exceptionName, $rtoMarkers, true)) {
                return true;
            }
        }

        return ShipmentHistory::where('shipment_id', $shipment->id)
            ->where(function ($q) use ($rtoMarkers) {
                $q->whereIn(DB::raw('UPPER(name)'), $rtoMarkers)
                    ->orWhereIn(DB::raw('UPPER(type)'), $rtoMarkers);
            })
            ->exists();
    }

    /**
     * Check if shipment is in transfer task
     * 
     * @param Shipment $shipment
     * @return bool
     */
    public function isShipmentInTransferTask(Shipment $shipment)
    {
        $inTransferTask = TransferTaskShipment::where('shipment_tracking_no', $shipment->tracking_no)
            ->whereIn('status', ['pending', 'loaded'])
            ->exists();



        return $inTransferTask;
    }

    /**
     * Check if shipment is loaded in transfer task
     * 
     * @param Shipment $shipment
     * @return bool
     */
    public function isShipmentLoadedInTransferTask(Shipment $shipment)
    {
        $isLoaded = TransferTaskShipment::where('shipment_tracking_no', $shipment->tracking_no)
            ->where('status', 'loaded')
            ->exists();



        return $isLoaded;
    }

    /**
     * Check if shipment is already unloaded in transfer task
     * 
     * @param Shipment $shipment
     * @return bool
     */
    public function isShipmentAlreadyUnloadedInTransferTask(Shipment $shipment)
    {
        $isUnloaded = TransferTaskShipment::where('shipment_tracking_no', $shipment->tracking_no)
            ->where('status', 'unloaded')
            ->orWhere('status', 'completed')
            ->exists();



        return $isUnloaded;
    }

    /**
     * Check if shipment is unloaded in transfer task
     * 
     * @param Shipment $shipment
     * @return bool
     */
    public function isShipmentUnloadedInTransferTask(Shipment $shipment)
    {
        $isUnloaded = TransferTaskShipment::where('shipment_tracking_no', $shipment->tracking_no)
            ->whereIn('status', ['unloaded', 'completed'])
            ->exists();



        return $isUnloaded;
    }

    /**
     * Check if shipment belongs to current facility for transfer operations
     * 
     * @param Shipment $shipment
     * @return bool
     */
    public function isShipmentOwnedByCurrentFacility(Shipment $shipment)
    {
        $currentFacilityType = facility("type");
        $currentFacilityId = facility("id");

        $isOwned = $shipment->owner_type === $currentFacilityType && $shipment->owner_id === $currentFacilityId;



        return $isOwned;
    }

    /**
     * Check if transfer task shipment exists and is valid
     * 
     * @param string $trackingNo
     * @return TransferTaskShipment|null
     */
    public function getValidTransferTaskShipment($trackingNo)
    {
        $transferTaskShipment = TransferTaskShipment::where('shipment_tracking_no', $trackingNo)
            ->with(['task', 'destination', 'shipment'])
            ->first();



        return $transferTaskShipment;
    }


    /**
     * Check if transfer task shipment is being loaded from the facility in which it was marked as transfer shipment.
     * 
     * @param string $trackingNo
     */
    public function isLoadedFromSortFacility($trackingNo)
    {
        $transferTaskShipment = TransferTaskShipment::where('shipment_tracking_no', $trackingNo)->first();



        return $transferTaskShipment->task->owner_id === facility("id") && $transferTaskShipment->task->owner_type === facility("type");
    }

    /**
     * Check if truck is valid for transfer task
     * 
     * @param TransferTaskShipment $transferTaskShipment
     * @param int $truckId
     * @return bool
     */
    public function isTruckValidForTransferTask(TransferTaskShipment $transferTaskShipment, $truckId)
    {
        if (!$transferTaskShipment->task) {
            return false;
        }

        $isValid = $transferTaskShipment->task->truck_id === $truckId;



        return $isValid;
    }

    /**
     * Get detailed validation message for delivery exception creation
     * 
     * @param Shipment $shipment
     * @return string
     */
    public function getDeliveryExceptionValidationMessage(Shipment $shipment)
    {
        if ($this->isShipmentDelivered($shipment)) {
            return "Cannot create exception for delivered shipment.";
        }

        if ($this->isShipmentInException($shipment)) {
            return "Shipment is already in exception.";
        }

        if (!$this->isShipmentAssigned($shipment)) {
            return "Shipment must be assigned to a driver before creating exception.";
        }

        if (!$this->isShipmentOFD($shipment)) {
            return "Shipment must be confirmed by driver (OFD status) before creating exception.";
        }

        if ($this->isShipmentInWarehouse($shipment)) {
            return "Cannot create exception for shipment in warehouse.";
        }

        if ($this->isShipmentAssignedToShelf($shipment)) {
            return "Cannot create exception for shipment assigned to shelf.";
        }

        if ($this->isShipmentRTO($shipment)) {
            return "Cannot create exception for RTO shipment.";
        }

        if ($this->isShipmentInTransferTask($shipment)) {
            return "Cannot create exception for shipment in transfer task.";
        }

        return "Shipment is eligible for exception creation.";
    }

    /**
     * Check if shipment can have abnormality exception created (miss-sort, damage)
     * 
     * Abnormality exceptions can be created for shipments that:
     * 1. Are not delivered
     * 2. Are not already in exception (for certain types)
     * 3. Are not in transfer task
     * 
     * @param Shipment $shipment
     * @param string $exceptionType (MISS_SORT, DAMAGED)
     * @return bool
     */
    public function canCreateAbnormalityException(Shipment $shipment, $exceptionType = 'MISS_SORT')
    {
        // Check if shipment is delivered
        if ($this->isShipmentDelivered($shipment)) {
            return false;
        }

        // For miss-sort, check if shipment is already in exception
        if ($exceptionType === 'MISS_SORT' && $this->isShipmentInException($shipment)) {
            return false;
        }

        // Check if shipment is in transfer task
        if ($this->isShipmentInTransferTask($shipment)) {
            return false;
        }

        return true;
    }

    /**
     * Get detailed validation message for abnormality exception creation
     * 
     * @param Shipment $shipment
     * @param string $exceptionType
     * @return string
     */
    public function getAbnormalityExceptionValidationMessage(Shipment $shipment, $exceptionType = 'MISS_SORT')
    {
        if ($this->isShipmentDelivered($shipment)) {
            return "Cannot create {$exceptionType} exception for delivered shipment.";
        }

        if ($exceptionType === 'MISS_SORT' && $this->isShipmentInException($shipment)) {
            return "Shipment is already in exception.";
        }

        if ($this->isShipmentInTransferTask($shipment)) {
            return "Cannot create {$exceptionType} exception for shipment in transfer task.";
        }

        return "Shipment is eligible for {$exceptionType} exception creation.";
    }

    /**
     * chcek if the parcel is eligilable to be added to shelf. only future and cancelled shipments can be added to shelf.
     */
    public function isAddableToShelf(Shipment $shipment)
    {
        if ($this->isShipmentOFD($shipment)) {
            return true;
        }

        if ($this->isShipmentInTransferTask($shipment) || $this->isShipmentLoadedInTransferTask($shipment) || $this->isShipmentAlreadyUnloadedInTransferTask($shipment)) {
            return true;
        }



        return false;
    }

    /**
     * check if the shipment is already assigned to shelf.
     */
    public function isShipmentAssignedToShelf(Shipment $shipment)
    {
        return AssignShipmentToShelf::where('tracking_no', $shipment->tracking_no)->where('status', '=', 'pending')->exists();
    }
}
