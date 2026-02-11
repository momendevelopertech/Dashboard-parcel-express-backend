<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreShipmentInformationRequest;
use App\Http\Requests\UpdateShipmentInformationRequest;
use App\Http\Resources\ShipmentInformationResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\ShipmentInformation;

/**
 * @OA\Tag(name="OMS", description="Shipment Management System")
 * @OA\Controller(description="Manage shipment information")
 */
class ShipmentInformationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/shipment_information",
     *     summary="Get a list of shipment information",
     *     description="Retrieve a list of shipment information. You can use the query parameter to filter by status.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for shipment status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Informations retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index()
    {
        $shipment_informations = ShipmentInformation::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $shipment_informations = $shipment_informations
                ->whereRaw('LOWER(status) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $shipment_informations = $shipment_informations->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Shipment Informations reterived successfully.", new ShipmentInformationResource($shipment_informations), []);
    }

    /**
     * @OA\Post(
     *     path="/shipment_information/store",
     *     summary="Create a new shipment information",
     *     description="Create a new shipment information.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Information created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function store(StoreShipmentInformationRequest $request)
    {
        $request->validated();
        try {
            $shipment_information = ShipmentInformation::create($request->all());
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Information created successfully.", new ShipmentInformationResource($shipment_information));
    }

    /**
     * @OA\Post(
     *     path="/shipment_information/update",
     *     summary="Update an shipment information",
     *     description="Update an existing shipment information.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the shipment information to update",
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
     *         description="Shipment Information updated successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(UpdateShipmentInformationRequest $request)
    {
        $request->validated();
        try {
            $shipment_information = ShipmentInformation::find($request->id);
            $shipment_information->update($request->all());
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Information updated successfully.", new ShipmentInformationResource($shipment_information));
    }

    /**
     * @OA\Post(
     *     path="/shipment_information/delete",
     *     summary="Delete an shipment information",
     *     description="Delete an existing shipment information.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *          @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Information deleted successfully",
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
            ShipmentInformation::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Information deleted successfully.", []);
    }

    /**
     * @OA\Get(
     *     path="/shipment_information/all",
     *     summary="Get all shipment information",
     *     description="Retrieve all shipment information.",
     *     tags={"OMS"},
     *     @OA\Response(
     *         response=200,
     *         description="Shipment Informations",
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function all()
    {
        return sendResponse("Shipment Informations", new ShipmentInformationResource(ShipmentInformation::all()));
    }
}
