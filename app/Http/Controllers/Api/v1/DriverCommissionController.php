<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\UpdateDriverCommissionRequest;
use App\Http\Resources\DriverCommissionResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\DriverCommission;

/**
 * @OA\Tag(name="Fleet & Driver Management", description="API endpoints for managing fleet and driver commissions.")
 * @OA\Controller(tags={"Fleet & Driver Management"})
 */
class DriverCommissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/driver_commissions",
     *     summary="Get driver commissions by driver ID",
     *     description="Retrieves a list of driver commissions for a given driver ID.",
     *     operationId="getDriverCommissions",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commissions retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {
        $driver_id = $request->driver_id;
        $commissions = DriverCommission::byOwner()->where('driver_id', $driver_id)->with('driver', 'state')->get();
        return sendResponse("Commissions reterived successfully.", $commissions, []);
    }

    /**
     * @OA\Post(
     *     path="/driver_commissions/store",
     *     summary="Create or update driver commissions",
     *     description="Creates or updates driver commissions for multiple states.",
     *     operationId="createOrUpdateDriverCommissions",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="driver_id", type="integer", description="ID of the driver", example=1),
     *             @OA\Property(
     *                 property="commissions",
     *                 type="array",
     *                 description="Array of commissions",
     *                 @OA\Items(
     *                     @OA\Property(property="state_id", type="integer", description="ID of the state", example=1),
     *                     @OA\Property(property="delivery_fee", type="number", format="float", description="Delivery fee", example=10.00),
     *                     @OA\Property(property="pickup_fee", type="number", format="float", description="Pickup fee", example=5.00)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commission saved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or error occurred while saving commission",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'driver_id' => 'required|exists:users,id',
                'commissions' => 'required|array',
                'commissions.*.state_id' => 'required|exists:states,id',
                'commissions.*.delivery_fee' => 'required|numeric|min:0',
                'commissions.*.pickup_fee' => 'required|numeric|min:0',
            ]);
            foreach ($data['commissions'] as $commissionData) {
                DriverCommission::updateOrCreate(
                    [
                        'driver_id' => $data['driver_id'],
                        'state_id' => $commissionData['state_id'],
                    ],
                    [
                        'delivery_fee' => $commissionData['delivery_fee'],
                        'pickup_fee' => $commissionData['pickup_fee'],
                    ]
                );
            }
            return response()->json(["message" => "Commission saved successfully."], 200);
        } catch (QueryException $e) {
            return response()->json(["error" => "Error occurred while saving commission.", "details" => $e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/driver_commissions/update",
     *     summary="Update driver commission",
     *     description="Updates a driver commission.",
     *     operationId="updateDriverCommission",
     *     tags={"Fleet & Driver Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the driver commission to update", example=1),
     *             @OA\Property(property="driver_id", type="integer", description="ID of the driver", example=1),
     *             @OA\Property(
     *                 property="commissions",
     *                 type="array",
     *                 description="Array of commissions",
     *                 @OA\Items(
     *                     @OA\Property(property="state_id", type="integer", description="ID of the state", example=1),
     *                     @OA\Property(property="delivery_fee", type="number", format="float", description="Delivery fee", example=10.00),
     *                     @OA\Property(property="pickup_fee", type="number", format="float", description="Pickup fee", example=5.00)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver Commission updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating driver commissions",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateDriverCommissionRequest $request)
    {
        try {
            $driver_commission = DriverCommission::findOrFail($request->id);
            $driver_commission->update($request->validated());
            return sendResponse("Driver Commission. updated successfully.", new DriverCommissionResource($driver_commission));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating driver commissions.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/driver_commissions/delete",
     *     summary="Delete driver commission",
     *     description="Deletes a driver commission.",
     *     operationId="deleteDriverCommission",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the driver commission to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver Commission deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting driver commission",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            DriverCommission::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Driver Commission. deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/driver_commissions/all",
     *     summary="Get all driver commissions",
     *     description="Retrieves all driver commissions.",
     *     operationId="getAllDriverCommissions",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Driver Commissions",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Driver Commissions", new DriverCommissionResource(DriverCommission::all()));
    }

    /**
     * @OA\Get(
     *     path="/driver_commissions/{driver_id}/{state_id}",
     *     summary="Get driver commission by driver and state ID",
     *     description="Retrieves a driver commission for a given driver and state ID.",
     *     operationId="getDriverCommissionByDriverAndStateId",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="state_id",
     *         in="path",
     *         description="ID of the state",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Response(
     *         response=200,
     *         description="Driver Commissions retrieved successfully.",
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function by_state($driver_id, $state_id)
    {
        $driver_commissions = DriverCommission::where('driver_id', $driver_id)->where('state_id', $state_id)
            ->orderBy('id', 'desc')
            ->first();
        return sendResponse("Driver Commissions retrieved successfully.", $driver_commissions);
    }
}
