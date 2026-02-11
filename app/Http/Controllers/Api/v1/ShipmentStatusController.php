<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Database\QueryException;
use App\Models\ShipmentStatus;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStatusRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\ShipmentStatusResource;

/**
 * @OA\Tag(name="OMS", description="Shipment Management System")
 * @OA\Server(url="/api")
 *  
 */
class ShipmentStatusController extends Controller
{
    /**
     * @OA\Get(
     *     path="/statuses/all",
     *     summary="Get all shipment statuses",
     *     tags={"OMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Status retrieved successfully.",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for label or description",
     *         @OA\Schema(type="string")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = $request->input('query');
        $statuses = [];
        $systemStatuses = ShipmentStatus::query();
        if ($query) {
            $systemStatuses = $systemStatuses->where(function ($q) use ($query) {
                $q->where('label', 'LIKE', '%' . $query . '%')
                    ->orWhere('description', 'LIKE', '%' . $query . '%');
            });
        }
        $statuses['system'] = $systemStatuses->get()->map(function ($status) {
            return [
                'id' => $status->id,
                'label' => $status->label,
                'description' => $status->description ?? '',
            ];
        });
        $static = statuses();
        if ($query) {
            $static = array_filter($static, function ($status) use ($query) {
                return stripos($status['label'], $query) !== false ||
                    stripos($status['description'], $query) !== false;
            });
        }
        $statuses['static'] = array_values($static);
        return sendResponse(
            "Status retrieved successfully.",
            $statuses,
            true
        );
    }

    /**
     * @OA\Post(
     *     path="/statuses/store",
     *     summary="Create a new shipment status",
     *     tags={"OMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="label", type="string", description="Label of the status", example="Pending"),
     *             @OA\Property(property="description", type="string", description="Description of the status", example="Shipment is pending processing"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status created successfully.",
     *     ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation errors",
     *      ),
     *     @OA\Response(
     *         response=500,
     *         description="An error occurred while creating the status.",
     *     ),
     * )
     */
    public function store(StoreStatusRequest $request)
    {

        try {
            // Create a new status
            $status = ShipmentStatus::create([
                'label' => $request->label,
                'description' => $request->description,
            ]);
            activityLog('status created', "new status created called {$status->label}");
            return sendResponse(
                "Status created successfully.",
                new ShipmentStatusResource($status),
                true
            );
        } catch (QueryException $e) {
            return sendResponse(
                "An error occurred while creating the status.",
                [],
                [$e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/statuses/update",
     *     summary="Update an shipment status",
     *     tags={"OMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the status to update (required)"),
     *             @OA\Property(property="label", type="string", description="Label of the status (required, string, max 255)"),
     *             @OA\Property(property="description", type="string", description="Description of the status (required, string, max 255)"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Status updated successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred while updating unit.",
     *     ),
     * )
     */
    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:shipment_statuses,id',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);
        try {
            $status = ShipmentStatus::findOrFail($request->id);
            $status->label = $request->label;
            $status->description = $request->description;
            $status->save();
         activityLog('status update',"status with name {$status->label} updated");

            return sendResponse("Shipment Status updated successfully.", new ShipmentStatusResource($status));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating unit.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/statuses/delete",
     *     summary="Delete an shipment status",
     *     tags={"OMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the status to delete (required)"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="ShipmentStatus deleted successfully.",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error Occured.",
     *     ),
     * )
     */
    public function delete(Request $request)
    {
        try {
            $status=ShipmentStatus::findOrFail($request->id);
            $status->delete();
            activityLog('status delete',"status with name {$status->lable} deleted");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("ShipmentStatus deleted successfully.", []);
    }
}