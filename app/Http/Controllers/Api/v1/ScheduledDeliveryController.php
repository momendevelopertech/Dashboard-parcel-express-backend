<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\ScheduledDelivery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="Other", description="Scheduled Delivery Management")
 */
class ScheduledDeliveryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/scheduled-deliveries",
     *     summary="Retrieve scheduled deliveries",
     *     description="Retrieves a list of scheduled deliveries with pagination.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter deliveries from this date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter deliveries to this date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="delivery_slot_id",
     *         in="query",
     *         description="Filter deliveries by delivery slot ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter deliveries by status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scheduled deliveries retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $query = ScheduledDelivery::with(['slot', 'driver', 'shipment']);

        if ($from = $request->query('date_from')) {
            $query->whereHas('slot', fn($q) => $q->where('date', '>=', $from));
        }
        if ($to = $request->query('date_to')) {
            $query->whereHas('slot', fn($q) => $q->where('date', '<=', $to));
        }
        if ($slot = $request->query('delivery_slot_id')) {
            $query->where('delivery_slot_id', $slot);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $deliveries = $query->orderBy('slot.date')->paginate(15);
        return sendResponse(
            'Scheduled deliveries retrieved successfully',
            $deliveries,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/scheduled-deliveries",
     *     summary="Create a new scheduled delivery",
     *     description="Creates a new scheduled delivery.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_id", type="string", description="Shipment ID"),
     *             @OA\Property(property="delivery_slot_id", type="integer", description="Delivery slot ID"),
     *             @OA\Property(property="driver_id", type="integer", description="Driver ID (nullable)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Scheduled delivery created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'shipment_id' => 'required|string|unique:scheduled_deliveries',
            'delivery_slot_id' => 'required|exists:delivery_slots,id',
            'driver_id' => 'nullable|exists:users,id',
        ]);
        $delivery = ScheduledDelivery::create($data);
        return sendResponse(
            'Scheduled delivery created successfully',
            $delivery,
            true,
            [],
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/scheduled-deliveries",
     *     summary="Update a scheduled delivery",
     *     description="Updates a scheduled delivery.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the scheduled delivery to update",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="delivery_slot_id", type="integer", description="Delivery slot ID"),
     *             @OA\Property(property="driver_id", type="integer", description="Driver ID (nullable)"),
     *             @OA\Property(property="status", type="string", description="Status (scheduled, in_progress, delivered, cancelled)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scheduled delivery updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Scheduled delivery not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'delivery_slot_id' => 'sometimes|exists:delivery_slots,id',
            'driver_id' => 'sometimes|nullable|exists:users,id',
            'status' => ['sometimes', Rule::in(['scheduled', 'in_progress', 'delivered', 'cancelled'])],
        ]);
        $delivery = ScheduledDelivery::findOrFail($request->id);
        $delivery->update($data);
        return sendResponse(
            'Scheduled delivery updated successfully',
            $delivery,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/scheduled-deliveries/delete",
     *     summary="Delete a scheduled delivery",
     *     description="Deletes a scheduled delivery.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the scheduled delivery to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Scheduled delivery deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Scheduled delivery not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy(Request $request)
    {
        $delivery = ScheduledDelivery::findOrFail($request->id);
        $delivery->delete();
        return sendResponse(
            'Scheduled delivery deleted successfully',
            null,
            true,
            [],
            204
        );
    }
}