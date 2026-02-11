<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreTruckStatusRequest;
use App\Http\Requests\UpdateTruckStatusRequest;
use App\Http\Resources\TruckStatusResource;
use App\Models\TruckStatus;
use App\Traits\Searchable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Fleet & Driver Management",
 *     description="API endpoints for managing fleet and driver data"
 * )
 */
class TruckStatusController extends Controller
{
    use Searchable;

    protected function modelQuery()
    {
        return TruckStatus::query()->select('id', 'truck_id', 'status', 'location', 'latitude', 'longitude', 'last_updated');
    }

    /**
     * @OA\Get(
     *     path="/truck_statuses",
     *     summary="Get all truck statuses",
     *     description="Retrieves a list of truck statuses with pagination and search capabilities.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vehicle statuses retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $statuses = $this->handleSearch(
            searchColumns: ['status'],
            withRelationships: [
                'truck:id,number_plate,type,truck_driver_id',
                'truck.truck_driver',
            ],
            perPage: request()->input('per_page', 10),
            shipmentColumn: 'last_updated',
            shipmentDirection: 'desc'
        );

        return sendResponse("Vehicle statuses retrieved successfully.", new TruckStatusResource($statuses));
    }

    /**
     * @OA\Post(
     *     path="/truck_statuses/store",
     *     summary="Create a new truck status",
     *     description="Creates a new truck status.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="truck_id", type="integer", description="ID of the truck", example=1),
     *             @OA\Property(property="status", type="string", description="Status of the truck (in_transit, idle, long_idle, under_maintenance)", example="in_transit"),
     *             @OA\Property(property="location", type="string", description="Location of the truck", example="New York"),
     *             @OA\Property(property="latitude", type="number", format="float", description="Latitude of the truck", example=40.7128),
     *             @OA\Property(property="longitude", type="number", format="float", description="Longitude of the truck", example=-74.0060),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vehicle status saved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreTruckStatusRequest $request)
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

    /**
     * @OA\Put(
     *     path="/truck_statuses/update",
     *     summary="Update an existing truck status",
     *     description="Updates an existing truck status.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the truck status"),
     *             @OA\Property(property="truck_id", type="integer", description="ID of the truck"),
     *             @OA\Property(property="status", type="string", description="Status of the truck (in_transit, idle, long_idle, under_maintenance)"),
     *             @OA\Property(property="location", type="string", description="Location of the truck"),
     *             @OA\Property(property="latitude", type="number", format="float", description="Latitude of the truck"),
     *             @OA\Property(property="longitude", type="number", format="float", description="Longitude of the truck"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vehicle status updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateTruckStatusRequest $request)
    {
        try {
            $status = TruckStatus::findOrFail($request->id);
            $status->update($request->validated());
            return sendResponse("Vehicle status updated successfully.", new TruckStatusResource($status));
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/truck_statuses/delete",
     *     summary="Delete a truck status",
     *     description="Deletes a truck status.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the truck status to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vehicle status deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            TruckStatus::findOrFail($request->id)->delete();
            return sendResponse("Vehicle status deleted successfully.", []);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/truck_statuses/history/{truck_id}",
     *     summary="Get truck status history",
     *     description="Retrieves the status history for a specific truck.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="truck_id",
     *         in="path",
     *         description="ID of the truck",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Response(
     *         response=200,
     *         description="Vehicle status history retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function history(Request $request, $truck_id)
    {
        $history = TruckStatus::where('truck_id', $truck_id)
            ->orderBy('last_updated', 'desc')
            ->paginate($request->input('per_page', 10));

        return sendResponse("Vehicle status history retrieved successfully.", new TruckStatusResource($history));
    }
}