<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\ShipmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\AssignShipmentToShelf;
use App\Models\Shipment;
use App\Services\ShipmentValidationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;


/**
 * @group RTO
 * 
 * RTO (Return to Origin) management operations for handling returned packages.
 */
class RTOController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/sorter/pick_rto",
     *     summary="Pick an RTO shipment from shelf.",
     *     description="Removes an assigned RTO shipment from its shelf location, records the status change and history entry.",
     *     tags={"RTO"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         required=true,
     *         description="Bearer access token, e.g. `Bearer eyJ0eXAiOiJKV1Qi…`",
     *         @OA\Schema(type="string", format="jwt", example="Bearer {token}")
     *     ),
     *     @OA\Parameter(
     *         name="X-Workspace-Key",
     *         in="header",
     *         required=true,
     *         description="Encrypted workspace identifier (Header set by merchant)",
     *         @OA\Schema(type="string", example="eyJpdiI6Ij…")
     *     ),
     *     @OA\Parameter(
     *         name="X-Workspace-Type",
     *         in="header",
     *         required=true,
     *         description="Workspace type, e.g. `Branch`, `Station` or `Hub`",
     *         @OA\Schema(type="string", example="Branch")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Tracking number of the RTO shipment to pick from shelf",
     *         @OA\JsonContent(
     *             required={"tracking_no"},
     *             @OA\Property(
     *                 property="tracking_no",
     *                 type="string",
     *                 description="Shipment tracking number",
     *                 example="PE040525590507"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="RTO shipment picked from shelf successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="RTO shipment picked from shelf successfully."),
     *             @OA\Property(property="data", type="object", description="Empty object on success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation or business rule failure.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only RTO shipments can be picked."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="array",
     *                 @OA\Items(type="string", example="Current status: DELIVERED")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected server error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error occurred while picking RTO shipment."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="array",
     *                 @OA\Items(type="string", example="SQLSTATE[HY000]: General error ...")
     *             )
     *         )
     *     )
     * )
     */
    public function pick_rto(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|string|exists:shipments,tracking_no',
        ]);

        DB::beginTransaction();
        try {
            $trackingNo = trim($request->tracking_no);

            $shipment = Shipment::where('tracking_no', $trackingNo)->firstOrFail();

            $validationService = new ShipmentValidationService();

            if (!$shipment->shipment_information->in_warehouse) {
                return sendResponse("Shipment is not in warehouse.", [], false, ["Shipment is not in warehouse."], 422);
            }

            if ($validationService->isAnShipmentToBeTransferred($shipment)) {
                return sendResponse("Shipment is a transfer shipment.", [], false, ["Shipment is a transfer shipment."], 422);
            }

            if (strtolower($shipment->status) !== 'rto') {
                return sendResponse(
                    "Only RTO shipments can be picked.",
                    [],
                    false,
                    ["Current status: {$shipment->status}"],
                    422
                );
            }

            $assignment = AssignShipmentToShelf::where('tracking_no', $trackingNo)->first();
            if (!$assignment) {
                return sendResponse(
                    "No shelf assignment found for this RTO shipment.",
                    [],
                    false,
                    ["Shipment not currently on shelf."],
                    422
                );
            }

            $assignment = AssignShipmentToShelf::where('tracking_no', $trackingNo)->first();
            if (!$assignment) {
                return sendResponse(
                    "No shelf assignment found for this RTO shipment.",
                    [],
                    false,
                    ["Shipment not currently on shelf."],
                    422
                );
            }

            $assignment->delete();

            $status = ShipmentStatusEnum::RTO_PICKED;
            shipmentHistory([
                'status' => $status,
                'description' => "RTO Shipment is picked from shelf",
                'shipment_id' => $shipment->id,
            ]);
            updateShipmentStatus($shipment->id, $status);

            DB::commit();
            return sendResponse("RTO shipment picked from shelf successfully.", []);
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse(
                "Database error while picking RTO shipment.",
                [],
                false,
                [$e->getMessage()],
                422
            );
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse(
                "Error occurred while picking RTO shipment.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    /**
     * Load a previously picked RTO shipment for outbound transit.
     * - Ensures shipment is in RTO_PICKED state
     * - Logs RTO_LOADED history
     * - Updates shipment status to RTO_LOADED
     */
    public function load_rto(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|string|exists:shipments,tracking_no',
        ]);

        DB::beginTransaction();
        try {
            $trackingNo = trim($request->tracking_no);

            $shipment = Shipment::where('tracking_no', $trackingNo)->firstOrFail();

            // if (strtolower($shipment->coreStatus()->name) !== 'rto_picked') {
            //     return sendResponse(
            //         "Shipment must be picked from shelf before loading.",
            //         [],
            //         false,
            //         ["Current status: {$shipment->coreStatus()->name}"],
            //         422
            //     );
            // }

            $validationService = new ShipmentValidationService();

            if (!$shipment->shipment_information->in_warehouse) {
                return sendResponse("Shipment is not in warehouse.", [], false, ["Shipment is not in warehouse."], 422);
            }

            if ($validationService->isAnShipmentToBeTransferred($shipment)) {
                return sendResponse("Shipment is a transfer shipment.", [], false, ["Shipment is a transfer shipment."], 422);
            }

            if ($shipment->assigned_to_shelf && strtolower($shipment->coreStatus()->name) !== 'rto_picked') {
                return sendResponse(
                    "Shipment must be picked from shelf before outbound.",
                    [],
                    false,
                    ["Current status: {$shipment->coreStatus()->name}"],
                    422
                );
            }

            if (strtolower($shipment->coreStatus()->name) == 'rto_loaded') {
                return sendResponse(
                    "Shipment is already outbounded.",
                    [],
                    false,
                    ["Current status: {$shipment->coreStatus()->name}"],
                    422
                );
            }



            if ($shipment->shipment_information) {
                $shipment->shipment_information->in_warehouse = false;
                $shipment->shipment_information->save();
            }

            $status = ShipmentStatusEnum::RTO_LOADED;
            shipmentHistory([
                'status' => $status,
                'description' => "RTO Shipment has been outbounded.",
                'shipment_id' => $shipment->id,
            ]);
            updateShipmentStatus($shipment->id, $status);
            $notificationTitle = "RTO Shipment Outbounded";
            $notificationContent = "RTO Shipment #{$shipment->tracking_no} has been successfully outbounded and is ready for transit.";
            create_notification(
                $shipment, 
                $notificationTitle,
                $notificationContent,
                [
                    'shipment_id' => $shipment->id,
                    'tracking_no' => $shipment->tracking_no,
                    'status' => $status,
                    'action_url' => route('shipments.show', $shipment->id)
                ],
                'RTO_LOADED'
            );
            DB::commit();
            return sendResponse("RTO shipment outbounded successfully.", []);
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse(
                "Database error while outbounding RTO shipment.",
                [],
                false,
                [$e->getMessage()],
                422
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse(
                "Error occurred while outbounding RTO shipment.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }
}
