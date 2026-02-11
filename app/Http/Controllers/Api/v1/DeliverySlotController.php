<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\DeliverySlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Helpers\helpers;

/**
 * @OA\Tag(name="Other", description="Delivery Slot Management")
 * @OA\Controller(description="API for managing delivery slots.")
 */
class DeliverySlotController extends Controller
{
    /**
     * @OA\Get(
     *     path="/delivery-slots",
     *     summary="Retrieve delivery slots",
     *     description="Retrieves a list of delivery slots based on optional query parameters.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Filter by date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status ('available' or 'full')",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery slots retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index(Request $request)
    {
        $query = DeliverySlot::query();

        if ($date = $request->query('date')) {
            $query->where('date', $date);
        }
        if ($status = $request->query('status')) {
            if ($status === 'available') {
                $query->has('scheduledDeliveries', '<', 'capacity');
            } elseif ($status === 'full') {
                $query->has('scheduledDeliveries', '>=', 'capacity');
            }
        }

        $slots = $query->orderBy('date')->orderBy('start_time')->paginate(10);

        // Append computed attributes
        $slots->getCollection()->transform(fn($slot) => array_merge(
            $slot->toArray(),
            [
                'used_count' => $slot->used_count,
                'status'     => $slot->status,
            ]
        ));

        return sendResponse(
            'Delivery slots retrieved successfully',
            $slots,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/delivery-slots",
     *     summary="Create a new delivery slot",
     *     description="Creates a new delivery slot.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="date", type="string", format="date", description="Date of the delivery slot"),
     *             @OA\Property(property="start_time", type="string", format="time", description="Start time of the delivery slot (HH:mm)"),
     *             @OA\Property(property="end_time", type="string", format="time", description="End time of the delivery slot (HH:mm)"),
     *             @OA\Property(property="capacity", type="integer", description="Capacity of the delivery slot"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Delivery slot created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'       => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'capacity'   => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return sendResponse(
                'Validation error',
                [],
                false,
                $validator->errors(),
                422
            );
        }
        $slot = DeliverySlot::create($validator->validated());
        return sendResponse(
            'Delivery slot created successfully',
            $slot,
            true,
            [],
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/delivery-slots/{deliverySlot}",
     *     summary="Retrieve a delivery slot",
     *     description="Retrieves a single delivery slot by ID.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="deliverySlot",
     *         in="path",
     *         description="ID of the delivery slot",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery slot retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Delivery slot not found"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function show(DeliverySlot $deliverySlot)
    {
        $slotData = array_merge(
            $deliverySlot->toArray(),
            [
                'used_count' => $deliverySlot->used_count,
                'status'     => $deliverySlot->status,
            ]
        );
        return sendResponse(
            'Delivery slot retrieved successfully',
            $slotData,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Put(
     *     path="/delivery-slots",
     *     summary="Update a delivery slot",
     *     description="Updates a delivery slot.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the delivery slot to update"),
     *             @OA\Property(property="date", type="string", format="date", description="Date of the delivery slot"),
     *             @OA\Property(property="start_time", type="string", format="time", description="Start time of the delivery slot (HH:mm)"),
     *             @OA\Property(property="end_time", type="string", format="time", description="End time of the delivery slot (HH:mm)"),
     *             @OA\Property(property="capacity", type="integer", description="Capacity of the delivery slot"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery slot updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Delivery slot not found"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'       => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time'   => 'sometimes|date_format:H:i|after:start_time',
            'capacity'   => 'sometimes|integer|min:1',
        ]);
        $deliverySlot = DeliverySlot::findOrFail($request->id);
        if ($validator->fails()) {
            return sendResponse(
                'Validation error',
                [],
                false,
                $validator->errors(),
                422
            );
        }
        $deliverySlot->update($validator->validated());

        return sendResponse(
            'Delivery slot updated successfully',
            $deliverySlot,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Delete(
     *     path="/delivery-slots/delete",
     *     summary="Delete a delivery slot",
     *     description="Deletes a delivery slot.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the delivery slot to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery slot deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Delivery slot not found"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function destroy(Request $request)
    {
        $deliverySlot = DeliverySlot::findOrFail($request->id);
        $deliverySlot->delete();
        return sendResponse(
            'Delivery slot deleted successfully',
            [],
            true,
            [],
            200
        );
    }
}
