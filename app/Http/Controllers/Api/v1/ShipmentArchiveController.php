<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Shipment;
use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use App\Http\Resources\ShipmentResource;
use Illuminate\Database\QueryException;
use App\Models\Scopes\ShipmentScope;
/**
 * @OA\Tag(
 *     name="OMS",
 *     description="Shipment Management System - Archive API Endpoints"
 * )
 */
class ShipmentArchiveController extends Controller
{
    /**
     * List all archived shipments
     * 
     * @OA\Get(
     *   path="/shipments/archive",
     *   tags={"OMS"},
     *   summary="List all archived shipments",
     *   description="Get paginated list of soft-deleted shipments",
     *   operationId="getArchivedShipments",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for tracking number",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     description="Number of items per page",
     *     required=false,
     *     @OA\Schema(type="integer", default=10)
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Archived shipments retrieved successfully"
     *   )
     * )
     */
    public function index()
    {
        $trackingNo = request()->query('query');
        $status = request()->query('status');
        $today = request()->query('today');
        $date = request()->query('date');
        $perPage = request()->query('per_page', 8);
        $facilityId = request()->query('facility_id');
        $facilityType = request()->query('facility_type');
        $shipmentsQuery = Shipment::archived();

        if ($trackingNo) {
            $shipmentsQuery->where('tracking_no', "like", "%" . $trackingNo . "%");
        }
        if ($status) {
            switch ($status) {
                case 'in_exception':
                    $shipmentsQuery->where('in_exception', true);
                    break;
                case 'OFD':
                    $shipmentsQuery->where('status', 'OFD');
                    break;
                case 'DELIVERED':
                    $shipmentsQuery->where('status', 'DELIVERED');
                    break;
                default:
                    $shipmentsQuery->where('status', $status);
                    break;
            }
        }

        if ($today === "true") {
            $shipmentsQuery->whereDate('created_at', Carbon::today());
        } elseif ($date) {
            try {
                $selectedDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
                if ($status === 'DELIVERED') {
                    // For delivered shipments, filter by delivery date using shipment history
                    $shipmentsQuery->whereHas('shipmentHistories', function ($q) use ($selectedDate) {
                        $q->where('name', 'DELIVERED')
                            ->whereDate('time', $selectedDate);
                    });
                } else {
                    // For other statuses, filter by creation date
                    $shipmentsQuery->whereDate('created_at', $selectedDate);
                }
            } catch (Exception $e) {
                // Invalid date format, ignore the filter
            }
        }

        if (has_role("Merchant")) {
            $shipmentsQuery->where('merchant_id', Auth::id());
        }

        // Filter by facility if provided
        if ($facilityId && $facilityType) {
            $shipmentsQuery->where('owner_id', $facilityId)
                ->where('owner_type', $facilityType);
        }

        $shipmentsQuery->with([
            'shipper:id,name,country_id,state_id,contact,zip_code,address',
            'shipper.country:id,name',
            'shipper.state:id,en_name,ar_name',

            'merchant:id,name',
            'merchant.merchant.country:id,name',
            'merchant.merchant.governorate:id,en_name,ar_name',
            'merchant.merchant.state:id,en_name,ar_name',
            'merchant.merchant.place:id,en_name,ar_name',

            'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone,country_key_cellphone,country_key_alternatePhone,address_confirmed',
            'consignee.country:id,name',
            'consignee.governorate:id,en_name,ar_name',
            'consignee.state:id,en_name,ar_name',
            'consignee.place:id,en_name,ar_name',
            'consignee.old_address',

            'shipment_information:id,shipment_id,zone_id',
            'shipment_information.zone:id,name',

            'core_status',
            'shipmentHistories',
            'shipment_items',
            'shipment_delivery',
            'transactions',
        ]);

        $shipments = $shipmentsQuery->orderByDesc('id')->paginate($perPage);
        if ($shipments->isEmpty()) {
            return sendResponse("No Record.", [], false, ['no record']);
        }
        return sendResponse("Shipments retrieved successfully.", [
            'shipments' => new ShipmentResource($shipments),
        ]);
    }
    // public function index(Request $request)
    // {
    //     try {
    //         $query = Shipment::withoutGlobalScope(ShipmentScope::class)
    //             ->archived()
    //             ->with([
    //                 'consignee:id,name,cellphone',
    //                 'shipper:id,name,cellphone',
    //                 'merchant:id,name,email',
    //                 'shipment_type:id,name',
    //                 'shipmentHistories:id,shipment_id,name,created_at'
    //             ]);

    //         if ($request->has('query') && $request->query) {
    //             $query->where('tracking_no', 'like', '%' . $request->query . '%');
    //         }

    //         $shipments = $query->orderBy('deleted_at', 'desc')
    //             ->paginate($request->per_page ?? 10);

    //         return sendResponse(
    //             "Archived shipments retrieved successfully.",
    //             ShipmentResource::collection($shipments),
    //             true,
    //             200,
    //             $shipments
    //         );
    //     } catch (Exception $e) {
    //         return sendResponse("Error retrieving archived shipments.", [], false, [$e->getMessage()], 500);
    //     }
    // }

    /**
     * Restore multiple soft-deleted shipments
     * 
     * @OA\Post(
     *   path="/shipments/archive/restore",
     *   tags={"OMS"},
     *   summary="Restore multiple archived shipments",
     *   description="Restore multiple soft-deleted shipments back to active status",
     *   operationId="restoreArchivedShipments",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="tracking_numbers", type="array", @OA\Items(type="string"), description="Array of shipment tracking numbers to restore")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipments restored successfully"
     *   )
     * )
     */
    public function restore(Request $request)
    {
        try {
            $trackingNumbers = $request->input('tracking_numbers', []);
            
            if (empty($trackingNumbers)) {
                return sendResponse("No tracking numbers provided.", [], [], 400);
            }

            $shipments = Shipment::withoutGlobalScope(ShipmentScope::class)
                ->archived()
                ->whereIn('tracking_no', $trackingNumbers)
                ->get();

            if ($shipments->isEmpty()) {
                return sendResponse("No archived shipments found with the provided tracking numbers.", [], [], 404);
            }

            $restoredShipments = [];
            foreach ($shipments as $shipment) {
                $shipment->restore();
                $restoredShipments[] = new ShipmentResource($shipment);
            }

            return sendResponse("Shipments restored successfully.", $restoredShipments);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while restoring shipments.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Permanently delete multiple archived shipments
     * 
     * @OA\Post(
     *   path="/shipments/archive/permanent-delete",
     *   tags={"OMS"},
     *   summary="Permanently delete multiple archived shipments",
     *   description="Permanently delete multiple soft-deleted shipments (admin only)",
     *   operationId="permanentlyDeleteArchivedShipments",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="ids", type="array", @OA\Items(type="integer"), description="Array of shipment IDs to permanently delete")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipments permanently deleted successfully"
     *   )
     * )
     */
    public function permanentDelete(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return sendResponse("No shipment IDs provided.", [], [], 400);
            }

            $shipments = Shipment::withoutGlobalScope(ShipmentScope::class)
                ->archived()
                ->with(['shipment_items', 'shipmentHistories'])
                ->whereIn('id', $ids)
                ->get();

            if ($shipments->isEmpty()) {
                return sendResponse("No archived shipments found with the provided IDs.", [], [], 404);
            }

            foreach ($shipments as $shipment) {
                // Delete related data
                $shipment->shipment_items()->delete();
                $shipment->shipment_information()->delete();
                $shipment->shipment_finance()->delete();
                $shipment->shipment_delivery()->delete();
                $shipment->shipmentHistories()->delete();

                // Permanently delete the shipment
                $shipment->forceDelete();
            }

            return sendResponse("Shipments permanently deleted.", []);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while deleting shipments.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Get archived shipment details
     * 
     * @OA\Post(
     *   path="/shipments/archive/show",
     *   tags={"OMS"},
     *   summary="Get archived shipment details",
     *   description="Get detailed information about a specific archived shipment",
     *   operationId="getArchivedShipmentDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="tracking_no", type="string", description="Shipment tracking number")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Archived shipment details retrieved successfully"
     *   )
     * )
     */
    public function show(Request $request)
    {
        try {
            $shipment = Shipment::withoutGlobalScope(ShipmentScope::class)
                ->archived()
                ->with([
                    'consignee',
                    'shipper',
                    'merchant',
                    'shipment_type',
                    'shipmentHistories',
                    'shipment_items',
                    'shipment_information',
                    'shipment_delivery',
                    'shipment_finance'
                ])
                ->where('tracking_no', $request->tracking_no)
                ->first();

            if (!$shipment) {
                return sendResponse("Archived shipment not found.", [], false, ['no record'], 404);
            }

            return sendResponse("Archived shipment details retrieved successfully.", new ShipmentResource($shipment));
        } catch (Exception $e) {
            return sendResponse("Error retrieving archived shipment details.", [], false, [$e->getMessage()], 500);
        }
    }
}
