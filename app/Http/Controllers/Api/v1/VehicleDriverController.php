<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreTruckStatusRequest;
use App\Http\Resources\TruckStatusResource;
use App\Models\TruckStatus;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class VehicleDriverController extends Controller
{
    public function update_vehicle_status(StoreTruckStatusRequest $request)
    {
        try {
            $validated = $request->validated();

            $status = TruckStatus::updateOrCreate(
                ['truck_id' => $validated['truck_id']],
                $validated
            );

            return sendResponse("Vehicle status saved successfully.", new TruckStatusResource($status));
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }
}
