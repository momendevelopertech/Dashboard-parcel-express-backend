<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreShipmentItemRequest;
use App\Http\Requests\UpdateShipmentItemRequest;
use App\Http\Resources\ShipmentItemResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\ShipmentItem;

/**
 * @OA\Tag(
 *     name="OMS",
 *     description="Shipment Management System"
 * )
 * @OA\Server(url="/api")
 */
class ShipmentItemController extends Controller
{
    /**
     * @OA\Get(
     *     path="/shipment_items",
     *     summary="Get a list of shipment items",
     *     description="Retrieves a list of shipment items. You can search using the `query` parameter.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for shipment item name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Items retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index()
    {
        $shipment_items = ShipmentItem::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $shipment_items = $shipment_items
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $shipment_items = $shipment_items->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Shipment Items reterived successfully.", new ShipmentItemResource($shipment_items), []);
    }

    /**
     * @OA\Post(
     *     path="/shipment_items/store",
     *     summary="Create a new shipment item",
     *     description="Creates a new shipment item.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object"
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shipment Item created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(StoreShipmentItemRequest $request)
    {
        $request->validated();
        try {
            $shipment_item = ShipmentItem::create($request->all());
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Item created successfully.", new ShipmentItemResource($shipment_item));
    }

    /**
     * @OA\Post(
     *     path="/shipment_items/update",
     *     summary="Update an shipment item",
     *     description="Updates an existing shipment item.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the shipment item to update",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Item updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(UpdateShipmentItemRequest $request)
    {
        $request->validated();
        try {
            $shipment_item = ShipmentItem::find($request->id);
            $shipment_item->update($request->all());
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Item updated successfully.", new ShipmentItemResource($shipment_item));
    }

    /**
     * @OA\Post(
     *     path="/shipment_items/delete",
     *     summary="Delete an shipment item",
     *     description="Deletes an shipment item.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the shipment item to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Item deleted successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function delete(Request $request)
    {
        try {
            ShipmentItem::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Item deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/shipment_items/all",
     *     summary="Get all shipment items",
     *     description="Retrieves all shipment items.",
     *     tags={"OMS"},
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Items retrieved successfully",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function all()
    {
        return sendResponse("Shipment Items", new ShipmentItemResource(ShipmentItem::all()));
    }
}
