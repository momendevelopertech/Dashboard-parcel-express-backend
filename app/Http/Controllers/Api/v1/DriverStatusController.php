<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreDriverStatusRequest;
use App\Http\Requests\UpdateDriverStatusRequest;
use App\Http\Resources\DriverStatusResource;
use App\Models\DriverStatus;
use App\Traits\Searchable;
use Google\Service\CloudLifeSciences\Action;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DriverStatusController extends Controller
{
    use Searchable;

    protected function modelQuery()
    {
        return DriverStatus::query()->select('id', 'driver_id', 'location', 'latitude', 'longitude', 'last_updated');
    }

    public function index()
    {
        $statuses = $this->handleSearch(
            searchColumns: ['location','driver.name','driver.phone'],
            withRelationships: [
                'driver:id,name,email,phone',
                'driver.driver:id,user_id,phone,status',
            ],
            perPage: request()->input('per_page', 10),
            shipmentColumn: 'last_updated',
            shipmentDirection: 'desc'
        );

        return sendResponse("Driver statuses retrieved successfully.", new DriverStatusResource($statuses));
    }

    public function store(StoreDriverStatusRequest $request)
    {
        try {
            $validated = $request->validated();

            $status = DriverStatus::updateOrCreate(
                ['driver_id' => $validated['driver_id']],
                $validated
            );

            return sendResponse("Driver status saved successfully.", new DriverStatusResource($status));
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    public function update(UpdateDriverStatusRequest $request)
    {
        try {
            $status = DriverStatus::findOrFail($request->id);
            $status->update($request->validated());
            return sendResponse("Driver status updated successfully.", new DriverStatusResource($status));
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    public function delete(Request $request)
    {
        try {
            DriverStatus::findOrFail($request->id)->delete();
            return sendResponse("Driver status deleted successfully.", []);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    public function history(Request $request, $driver_id)
    {
        $history = DriverStatus::where('driver_id', $driver_id)
            ->orderBy('last_updated', 'desc')
            ->paginate($request->input('per_page', 10));

        return sendResponse("Driver status history retrieved successfully.", new DriverStatusResource($history));
    }

    public function all()
    {
        $statuses = DriverStatus::with([
            'driver:id,name,email',
            'driver.driver:id,user_id,phone,status'
        ])
        ->orderBy('last_updated', 'desc')
        ->get();

        return sendResponse("All driver statuses retrieved successfully.", new DriverStatusResource($statuses));
    }

    public function update_driver_location(StoreDriverStatusRequest $request)
    {
        try {
            $validated = $request->validated();

            $status = DriverStatus::updateOrCreate(
                ['driver_id' => $validated['driver_id']],
                $validated
            );

            return sendResponse("Driver location updated successfully.", new DriverStatusResource($status));
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }
}
