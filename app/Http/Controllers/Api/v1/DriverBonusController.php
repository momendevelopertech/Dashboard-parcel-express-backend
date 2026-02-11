<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\UpdateDriverBonusRequest;
use App\Http\Resources\DriverBonusResource;
use App\Models\DriverBonus;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Fleet & Driver Management", description="API endpoints for managing fleet and driver bonuses.")
 * @OA\Controller(description="Manage driver bonuses.")
 */
class DriverBonusController extends Controller
{
    /**
     * @OA\Get(
     *     path="/driver_bonuses",
     *     summary="Get driver bonuses by driver ID",
     *     description="Retrieves a list of driver bonuses for a specific driver.",
     *     tags={"Fleet & Driver Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="owner_type",
     *         in="query",
     *         description="Owner type (optional)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="owner_id",
     *         in="query",
     *         description="Owner ID (optional)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bonuses retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     )
     * )
     */
    public function index(Request $request)
    {
        $driver_id = $request->driver_id;
        $query = DriverBonus::where('driver_id', $driver_id);

        // Filter by owner if provided
        if ($request->has('owner_type') && $request->has('owner_id')) {
            $query->where('owner_type', $request->owner_type)
                  ->where('owner_id', $request->owner_id);
        }

        $bonuses = $query->with('driver', 'state', 'owner')->get();
        return sendResponse("Bonuses retrieved successfully.", $bonuses, []);
    }

    /**
     * @OA\Post(
     *     path="/driver_bonuses/store",
     *     summary="Create or update driver bonuses",
     *     description="Creates or updates bonuses for a driver in multiple states.",
     *     tags={"Fleet & Driver Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="driver_id", type="integer", description="ID of the driver", example=1),
     *             @OA\Property(
     *                 property="bonuses",
     *                 type="array",
     *                 description="Array of bonuses for different states",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="state_id", type="integer", description="ID of the state", example=1),
     *                     @OA\Property(property="delivery_bonus", type="number", format="float", description="Delivery bonus amount", example=10.00),
     *                     @OA\Property(property="pickup_bonus", type="number", format="float", description="Pickup bonus amount", example=5.00),
     *                     @OA\Property(property="return_bonus", type="number", format="float", description="Return bonus amount", example=10.00),
     *                     @OA\Property(property="return_pickup_bonus", type="number", format="float", description="Return pickup bonus amount", example=5.00),
     *                 )
     *             ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bonuses saved successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while saving commission.",
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'driver_id' => 'required|exists:users,id',
                'owner_type' => 'nullable|string',
                'owner_id' => 'nullable|integer',
                'bonuses' => 'required|array',
                'bonuses.*.state_id' => 'required|exists:states,id',
                'bonuses.*.delivery_bonus' => 'required|numeric|min:0',
                'bonuses.*.pickup_bonus' => 'required|numeric|min:0',
                'bonuses.*.return_bonus' => 'required|numeric|min:0',
                'bonuses.*.return_pickup_bonus' => 'required|numeric|min:0',
            ]);

            $ownerType = $data['owner_type'] ?? null;
            $ownerId = $data['owner_id'] ?? null;

            foreach ($data['bonuses'] as $bonusData) {
                // Check if record exists
                $existingBonus = DriverBonus::where('driver_id', $data['driver_id'])
                    ->where('state_id', $bonusData['state_id'])
                    ->where('owner_type', $ownerType)
                    ->where('owner_id', $ownerId)
                    ->first();

                if ($existingBonus) {
                    // Update existing record
                    $existingBonus->update([
                        'delivery_bonus' => $bonusData['delivery_bonus'],
                        'pickup_bonus' => $bonusData['pickup_bonus'],
                        'return_bonus' => $bonusData['return_bonus'],
                        'return_pickup_bonus' => $bonusData['return_pickup_bonus'],
                    ]);
                } else {
                    // Create new record
                    DriverBonus::create([
                        'driver_id' => $data['driver_id'],
                        'state_id' => $bonusData['state_id'],
                        'owner_type' => $ownerType,
                        'owner_id' => $ownerId,
                        'delivery_bonus' => $bonusData['delivery_bonus'],
                        'pickup_bonus' => $bonusData['pickup_bonus'],
                        'return_bonus' => $bonusData['return_bonus'],
                        'return_pickup_bonus' => $bonusData['return_pickup_bonus'],
                    ]);
                }
            }
            info(DriverBonus::where('driver_id', $data['driver_id'])->get());
            return response()->json(["message" => "Bonuses saved successfully."], 200);
        } catch (QueryException $e) {
            return response()->json(["error" => "Error occurred while saving commission.", "details" => $e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/driver_bonuses/update",
     *     summary="Update a driver bonus",
     *     description="Updates an existing driver bonus.",
     *     tags={"Fleet & Driver Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="driver_id", type="integer", description="ID of the driver", example=1),
     *             @OA\Property(property="state_id", type="integer", description="ID of the state", example=1),
     *             @OA\Property(property="delivery_bonus", type="number", format="float", description="Delivery bonus amount", example=10.00),
     *             @OA\Property(property="pickup_bonus", type="number", format="float", description="Pickup bonus amount", example=5.00),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver Bonus updated successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating driver bonuses.",
     *     )
     * )
     */
    public function update(UpdateDriverBonusRequest $request)
    {
        try {
            $driver_commission = DriverBonus::findOrFail($request->id);
            $driver_commission->update($request->validated());
            return sendResponse("Driver Bonus. updated successfully.", new DriverBonusResource($driver_commission));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating driver bonuses.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/driver_bonuses/delete",
     *     summary="Delete a driver bonus",
     *     description="Deletes a driver bonus.",
     *     tags={"Fleet & Driver Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the driver bonus to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver Bonus deleted successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while deleting driver bonus.",
     *     )
     * )
     */
    public function delete(Request $request)
    {
        try {
            DriverBonus::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Driver Bonus. deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/driver_bonuses/all",
     *     summary="Get all driver bonuses",
     *     description="Retrieves all driver bonuses.",
     *     tags={"Fleet & Driver Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Driver Bonuses retrieved successfully.",
     *     )
     * )
     */
    public function all()
    {
        return sendResponse("Driver Bonuses", new DriverBonusResource(DriverBonus::all()));
    }

    /**
     * @OA\Get(
     *     path="/driver_bonuses/{driver_id}/{state_id}",
     *     summary="Get driver bonus by driver and state ID",
     *     description="Retrieves a driver bonus for a specific driver and state.",
     *     tags={"Fleet & Driver Management"},
     *     security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="ID of the driver",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Parameter(
     *         name="state_id",
     *         in="path",
     *         description="ID of the state",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Parameter(
     *         name="owner_type",
     *         in="query",
     *         description="Owner type (optional)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *      @OA\Parameter(
     *         name="owner_id",
     *         in="query",
     *         description="Owner ID (optional)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver Bonuses retrieved successfully.",
     *     )
     * )
     */
    public function by_state(Request $request, $driver_id, $state_id)
    {
        $query = DriverBonus::where('driver_id', $driver_id)->where('state_id', $state_id);

        // Filter by owner if provided
        if ($request->has('owner_type') && $request->has('owner_id')) {
            $query->where('owner_type', $request->owner_type)
                  ->where('owner_id', $request->owner_id);
        } elseif ($request->has('global') && $request->global == 'true') {
            // Get global bonuses (no owner)
            $query->whereNull('owner_type')
                  ->whereNull('owner_id');
        }

        $driver_bonuses = $query->orderBy('id', 'desc')->first();
        return sendResponse("Driver Bonuses retrieved successfully.", $driver_bonuses);
    }
}
