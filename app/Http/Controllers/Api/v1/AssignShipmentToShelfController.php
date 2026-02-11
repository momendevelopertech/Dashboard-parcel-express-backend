<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\ShipmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Services\ShipmentValidationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\AssignShipmentToShelf;
use App\Models\CrmTask;
use App\Models\Shipment;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(name="WMS", description="Warehouse Management System")
 * @OA\Controller(description="Manage assignment of shipments to shelves.")
 */
class AssignShipmentToShelfController extends Controller
{
    /**
     * @OA\Get(
     *     path="/assign_shipment_to_shelf",
     *     summary="Get a list of shipments assigned to shelves.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for shipment name.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully."
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
        $shipments = AssignShipmentToShelf::byOwner();
        if (request()->has('query')) {
            $query = request()->input('query');
            $shipments = $shipments
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->with('shipment', 'shelf', 'assigned_by')
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $shipments = $shipments->with('shipment', 'shelf', 'assigned_by')->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Shipments in Shelves reterived successfully.", new ShipmentResource(resource: $shipments), []);
    }

    /**
     * @OA\Post(
     *     path="/assign_shipment_to_shelf/store",
     *     summary="Assign an shipment to a shelf.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_no", type="string", description="Shipment tracking number. |exists:shipments,tracking_no"),
     *             @OA\Property(property="barcode", type="string", description="Shelf barcode. |exists:shelves,barcode")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment assigned to shelf successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or shipment already assigned."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            "tracking_no" => "required|exists:shipments,tracking_no",
            "barcode" => "required|exists:shelves,barcode",
        ]);
        try {
            if (AssignShipmentToShelf::where('tracking_no', $request->tracking_no)->count() > 0) {
                return sendResponse("Error Occured.", [], true, ["Shipment already assigned to shelf."], 422);
            }

            $shipment = Shipment::where("tracking_no", $request->tracking_no)->first();

            $validationService = new ShipmentValidationService();

            if ($validationService->isShipmentDelivered($shipment)) {
                return sendResponse("Error Occured.", [], true, ["Shipment is delivered and cannot be added to shelf."], 422);
            }

            if (!$validationService->isShipmentInWarehouse($shipment)) {
                return sendResponse("Error Occured.", [], true, ["Shipment is not in warehouse and cannot be added to shelf."], 422);
            }

            $data = $request->all();
            $data['assigned_by'] = Auth::id();
            $delivery_exception = $shipment->core_exception;

            if ($delivery_exception) {
                if (strtolower($delivery_exception->name) == "cancelled") {
                    CrmTask::create([
                        'title' => "Shipment requires CRM attention: ",
                        'shipment_id' => $shipment->id,
                        'status' => 'created',
                        'owner_id' => facility("id"),
                        'owner_type' => facility("type"),
                    ]);
                    shipmentHistory([
                        "shipment_id" => $shipment->id,
                        "status" => ShipmentStatusEnum::CRM_TASK,
                        "description" => "CRM task has been created due to cancelled exception",
                        "type" => $delivery_exception->type
                    ]);
                }
            }

            $assignShipmentToShelf = AssignShipmentToShelf::create($data);
            $status = ShipmentStatusEnum::ASSIGNED_TO_SHELF;
            $historyData = [
                "status" => status($status)['label'],
                "description" => status($status)['description'] . " - Shelf: " . $assignShipmentToShelf->shelf->barcode,
                "shipment_id" => $assignShipmentToShelf->shipment->id,
                "data" => json_encode([
                    'shelf_barcode' => $assignShipmentToShelf->shelf->barcode,
                    'shelf_id' => $assignShipmentToShelf->shelf->id,
                ])
            ];
            $assignShipmentToShelf->shipment->shipment_information->in_warehouse = true;
            $assignShipmentToShelf->shipment->shipment_information->save();
            shipmentHistory($historyData);
            $targetStatus = ShipmentStatusEnum::WAITING_CRM;
            if ($delivery_exception && $delivery_exception->type === 'FUTURE_DELIVERY') {
                $targetStatus = ShipmentStatusEnum::ASSIGNED_TO_SHELF;
            }

            updateShipmentStatus($shipment->id, $targetStatus);
            // updateShipmentStatus($shipment->id, "WAITING_CRM");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", false, [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipment Assigned To Shelf successfully.", $assignShipmentToShelf);
    }

    /**
     * @OA\Post(
     *     path="/assign_shipment_to_shelf/shipments/{barcode}",
     *     summary="Get shipments for a specific shelf.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="barcode",
     *         in="path",
     *         description="Shelf barcode.",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for shipment tracking number.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shelf shipments retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function shelf_shipments($barcode)
    {
        $shipments = AssignShipmentToShelf::where('barcode', $barcode);
        if (request()->has('query')) {
            $query = request()->input('query');
            $shipments = $shipments
                ->whereHas("shipment", function ($q) use ($query) {
                    $q->whereRaw('LOWER(tracking_no) LIKE ?', ['%' . strtolower($query) . '%']);
                })
                ->with('shipment', 'shipment.consignee.governorate', 'shipment.consignee.state', 'shipment.consignee.place', 'shelf', 'assigned_by')
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $shipments = $shipments->with('shipment', 'shipment.consignee.governorate', 'shipment.consignee.state', 'shipment.consignee.place', 'shelf', 'assigned_by')->paginate(8);
        }
        $count = AssignShipmentToShelf::where('barcode', $barcode)->count();
        return sendResponse("Shelf shipments retrieved successfully.", ['count' => $count, 'shipments' => $shipments]);
    }
}
