<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;
use App\Http\Resources\OFDResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System"
 * )
 */
class OFDListController extends Controller
{
    /**
     * @OA\Post(
     *     path="/ofd",
     *     summary="Get a list of shipments that are out for delivery.",
     *     tags={"WMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search by tracking number.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="driver",
     *         in="query",
     *         description="Filter by driver ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="merchant",
     *         in="query",
     *         description="Filter by merchant ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="delivery_exception",
     *         in="query",
     *         description="Filter by delivery exception status.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="payment_type",
     *         in="query",
     *         description="Filter by payment type.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="ofd_times",
     *         in="query",
     *         description="Filter by OFD count.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter by from date (YYYY-MM-DD).",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter by to date (YYYY-MM-DD).",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully.",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/ofd",
     *     summary="Get OFD shipments with filters and pagination",
     *     tags={"OFD Shipments"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OFD shipments retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $shipments = Shipment::query();

            $query = $request->input('query');
            if ($request->has('query') && $request->filled('query')) {
                $shipments->where('tracking_no', 'like', '%' . $query . '%');
            }

            $driver_id = $request->input('driver');
            if ($request->has('driver') && $request->filled('driver')) {
                $shipments->whereHas('driverAssignments', function ($q) use ($driver_id) {
                    $q->where('driver_id', $driver_id);
                });
            }

            $merchant_id = $request->input('merchant');
            if ($request->has('merchant') && $request->filled('merchant')) {
                $shipments->whereHas('merchant', function ($q) use ($merchant_id) {
                    $q->where('id', $merchant_id);
                });
            }

            $delivery_exception = $request->input('delivery_exception');
            if ($request->has('delivery_exception') && $request->filled('delivery_exception')) {
                $shipments->where('status', $delivery_exception);
            }

            $payment_type = $request->input('payment_type');
            if ($request->has('payment_type') && $request->filled('payment_type')) {
                $shipments->where('payment_type', $payment_type);
            }

            $ofd_times = $request->input('ofd_times');
            if ($request->has('ofd_times') && $request->filled('ofd_times')) {
                $shipments->whereHas('shipment_delivery', function ($q) use ($ofd_times) {
                    $q->where('ofd_count', $ofd_times);
                });
            }

            // Add date filtering using from and to
            if ($request->has('from') && $request->has('to')) {
                try {
                    $fromDate = Carbon::createFromFormat('Y-m-d H:i', $request->input('from'));
                    $toDate = Carbon::createFromFormat('Y-m-d H:i', $request->input('to'));
                    if ($fromDate && $toDate) {
                        $shipments->whereHas('driverAssignments', function ($q) use ($fromDate, $toDate) {
                            $q->whereBetween('created_at', [$fromDate, $toDate]);
                        });
                    }
                } catch (\Exception $e) {
                    Log::warning("Invalid date format for from/to: {$request->input('from')} / {$request->input('to')}", ['exception' => $e->getMessage()]);
                }
            }

            $shipments = $shipments->whereHas("runsheet_shipment", function ($q) {
                $q->where("status", "confirmed")
                    ->orWhere("status", "delivered")
                    ->orWhere("status", "returned");
            })->with('consignee.governorate', 'consignee.state', 'consignee.place', 'shipment_information', 'shipment_delivery', 'driverAssignments')
                ->paginate($perPage);

            // Format the response to match the expected frontend structure
            $shipments =  OFDResource::collection($shipments)->response()->getData(true);

  if (isset($shipments['links'])) {
            $shipments['links'] = array_values((array) $shipments['links']);
        }

            return sendResponse("OFD shipments retrieved successfully.", $shipments);
        } catch (\Exception $e) {
            return response()->json([
                'data' => null,
                'message' => 'Error retrieving OFD shipments.',
                'success' => false,
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }
}
