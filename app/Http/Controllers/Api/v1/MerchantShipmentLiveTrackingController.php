<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ShipmentResource;
use Illuminate\Database\QueryException;

class MerchantShipmentLiveTrackingController extends Controller
{
    /**
     * Get Active Tracking Shipments
     *
     * @OA\Get(
     *     path="/merchant/shipments/active-tracking",
     *     summary="Get shipments available for live tracking",
     *     description="
     * Retrieve merchant shipments that are currently active for live tracking with driver locations.
     * 
     * **Features:**
     * - Shipments in delivery status (OFD, DISPATCH, PICKED)
     * - Driver location data included
     * - Consignee information included
     * - Real-time tracking capability
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped shipments only
     * ",
     *     operationId="getActiveTrackingShipments",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Active tracking shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Active tracking shipments retrieved successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function getActiveTrackingShipments()
    {
        try {
            $merchantId = Auth::id();
            
            $shipments = Shipment::where('merchant_id', $merchantId)
                ->whereIn('status', ['OFD', 'DISPATCH', 'PICKED'])
                ->with([
                    'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone,streetAddress,governorate_id,state_id,latitude,longitude',
                    'consignee.governorate:id,en_name,ar_name',
                    'consignee.state:id,en_name,ar_name',
                    'driver:id,name',
                    'driver_status:driver_id,latitude,longitude,location,last_updated'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            return sendResponse("Active tracking shipments retrieved successfully.", $shipments);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching shipments.", [], false, [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Get Shipment Tracking Details
     *
     * @OA\Get(
     *     path="/merchant/shipments/tracking/{trackingNo}",
     *     summary="Get detailed tracking information for specific shipment",
     *     description="
     * Retrieve comprehensive tracking details for a specific shipment including driver location and history.
     * 
     * **Features:**
     * - Complete shipment information
     * - Driver location and status
     * - Shipment history timeline
     * - Current assignment details
     * - Real-time updates
     * 
     * **Security:**
     * - Merchant authentication required
     * - Shipment ownership verified
     * ",
     *     operationId="getShipmentTrackingDetails",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="trackingNo",
     *         in="path",
     *         description="Shipment tracking number",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment tracking details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment tracking details retrieved successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function getShipmentTracking($trackingNo)
    {
        try {
            $merchantId = Auth::id();
            
            $shipment = Shipment::where('merchant_id', $merchantId)
                ->where('tracking_no', $trackingNo)
                ->with([
                    'consignee:id,name,cellphone,streetAddress,country_id,governorate_id,state_id,place_id,latitude,longitude',
                    'consignee.country:id,name',
                    'consignee.governorate:id,en_name,ar_name',
                    'consignee.state:id,en_name,ar_name',
                    'consignee.place:id,en_name,ar_name',
                    'driver:id,name,phone',
                    'driver_status:driver_id,latitude,longitude,location,last_updated',
                    'shipmentHistories' => function($query) {
                        $query->orderBy('created_at', 'desc');
                    },
                    'current_assignment.driver'
                ])
                ->first();

            if (!$shipment) {
                return sendResponse("Shipment not found.", [], false, ["Shipment with tracking number {$trackingNo} not found."], 404);
            }

            return sendResponse("Shipment tracking details retrieved successfully.", new ShipmentResource($shipment));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching shipment details.", [], false, [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Get Driver Location
     *
     * @OA\Get(
     *     path="/merchant/shipments/driver-location",
     *     summary="Get real-time driver location for shipment",
     *     description="
     * Retrieve current driver location and status for a specific shipment.
     * 
     * **Features:**
     * - Real-time driver location
     * - Driver contact information
     * - Location timestamp
     * - Shipment status context
     * 
     * **Security:**
     * - Merchant authentication required
     * - Shipment ownership verified
     * ",
     *     operationId="getDriverLocation",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="query",
     *         description="Shipment tracking number",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver location retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Driver location retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="driver", type="object"),
     *                 @OA\Property(property="location", type="object"),
     *                 @OA\Property(property="shipment_status", type="string")
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment/Driver not found or location unavailable",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function getDriverLocation(Request $request)
    {
        try {
            $request->validate([
                'tracking_no' => 'required|exists:shipments,tracking_no'
            ]);

            $merchantId = Auth::id();
            $trackingNo = $request->tracking_no;
            
            $shipment = Shipment::where('merchant_id', $merchantId)
                ->where('tracking_no', $trackingNo)
                ->with([
                    'driver:id,name,phone',
                    'driver_status:driver_id,latitude,longitude,location,last_updated'
                ])
                ->first();

            if (!$shipment) {
                return sendResponse("Shipment not found.", [], false, ["Shipment not found or not accessible."], 404);
            }

            if (!$shipment->driver_id) {
                return sendResponse("No driver assigned.", [], false, ["Shipment has not been assigned to a driver yet."], 404);
            }

            if (!$shipment->driver_status) {
                return sendResponse("Driver location not available.", [], false, ["Driver location data is not available."], 404);
            }

            return sendResponse("Driver location retrieved successfully.", [
                'driver' => $shipment->driver,
                'location' => $shipment->driver_status,
                'shipment_status' => $shipment->status
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching driver location.", [], false, [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Get Tracking Summary
     *
     * @OA\Get(
     *     path="/merchant/shipments/tracking-summary",
     *     summary="Get tracking summary statistics",
     *     description="
     * Retrieve summary statistics for merchant's active deliveries and tracking overview.
     * 
     * **Features:**
     * - Active shipments count by status
     * - Delivery performance metrics
     * - Today's delivery statistics
     * - Real-time dashboard data
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped statistics only
     * ",
     *     operationId="getTrackingSummary",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Tracking summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tracking summary retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_active", type="integer"),
     *                 @OA\Property(property="out_for_delivery", type="integer"),
     *                 @OA\Property(property="dispatched", type="integer"),
     *                 @OA\Property(property="picked_up", type="integer"),
     *                 @OA\Property(property="delivered_today", type="integer")
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function getTrackingSummary()
    {
        try {
            $merchantId = Auth::id();
            
            $summary = [
                'total_active' => Shipment::where('merchant_id', $merchantId)
                    ->whereIn('status', ['OFD', 'DISPATCH', 'PICKED'])
                    ->count(),
                'out_for_delivery' => Shipment::where('merchant_id', $merchantId)
                    ->where('status', 'OFD')
                    ->count(),
                'dispatched' => Shipment::where('merchant_id', $merchantId)
                    ->where('status', 'DISPATCH')
                    ->count(),
                'picked_up' => Shipment::where('merchant_id', $merchantId)
                    ->where('status', 'PICKED')
                    ->count(),
                'delivered_today' => Shipment::where('merchant_id', $merchantId)
                    ->where('status', 'DELIVERED')
                    ->whereDate('updated_at', today())
                    ->count()
            ];

            return sendResponse("Tracking summary retrieved successfully.", $summary);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching summary.", [], false, [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }
}
