<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreShipmentAmountRequest;
use App\Http\Requests\UpdateShipmentAmountRequest;
use App\Http\Resources\ShipmentAmountResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\ShipmentAmount;

/**
 * @OA\Tag(name="OMS", description="Shipment Management System")
 * @OA\Controller(description="Manage shipment amounts.")
 */
class ShipmentAmountController extends Controller
{
    /**
     * @OA\Get(
     *     path="/shipment_amounts",
     *     summary="Get a list of shipment amounts.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for shipment amount name.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment amounts retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $shipment_amounts = ShipmentAmount::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $shipment_amounts = $shipment_amounts
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $shipment_amounts = $shipment_amounts->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Shipment Amounts reterived successfully.", new ShipmentAmountResource($shipment_amounts), []);
    }

    /**
     * @OA\Post(
     *     path="/shipment_amounts/store",
     *     summary="Create a new shipment amount.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment amount created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(StoreShipmentAmountRequest $request)
    {
        $request->validated();
        try {
            $shipment_amount = ShipmentAmount::create($request->all());
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Amount created successfully.", new ShipmentAmountResource($shipment_amount));
    }

    /**
     * @OA\Post(
     *     path="/shipment_amounts/update",
     *     summary="Update an shipment amount.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the shipment amount to update",
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
     *         description="Shipment amount updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(UpdateShipmentAmountRequest $request)
    {
        $request->validated();
        try {
            $shipment_amount = ShipmentAmount::find($request->id);
            $shipment_amount->update($request->all());
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Amount updated successfully.", new ShipmentAmountResource($shipment_amount));
    }

    /**
     * @OA\Post(
     *     path="/shipment_amounts/delete",
     *     summary="Delete an shipment amount.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment amount deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        try {
            ShipmentAmount::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Amount deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/shipment_amounts/all",
     *     summary="Get all shipment amounts.",
     *     tags={"OMS"},
     *     @OA\Response(
     *         response=200,
     *         description="Shipment amounts retrieved successfully."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function all()
    {
        return sendResponse("Shipment Amounts", new ShipmentAmountResource(ShipmentAmount::all()));
    }
}
