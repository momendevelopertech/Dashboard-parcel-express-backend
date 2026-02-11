<?php

namespace App\Services;

use App\Models\AssignShipmentToShelf;

class ParcelShelfService
{
    /**
     * Determine if a parcel is currently on any shelf.
     *
     * @param  string  $trackingNo
     * @return bool
     */
    public function isOnShelf(string $trackingNo): bool
    {
        return AssignShipmentToShelf::where('tracking_no', $trackingNo)->exists();
    }

    /**
     * Retrieve the shelf assignment record for a parcel, if any.
     *
     * @param  string  $trackingNo
     * @return \App\Models\AssignShipmentToShelf|null
     */
    public function getAssignment(string $trackingNo): ?AssignShipmentToShelf
    {
        return AssignShipmentToShelf::with('shelf')
            ->where('tracking_no', $trackingNo)
            ->first();
    }

    /**
     * Remove a parcel’s shelf assignment.
     *
     * @param  string  $trackingNo
     * @return bool  True if removed, false if no assignment found.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function removeFromShelf(string $trackingNo): bool
    {
        $assignment = AssignShipmentToShelf::where('tracking_no', $trackingNo)->first();

        if (!$assignment) {
            return false;
        }

        return $assignment->delete();
    }
}
