<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\DeliveryExceptionEnum;
use App\Models\Shipment;
use Illuminate\Http\Request;
use App\Models\MerchantPickupTask;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SorterController;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Resources\MerchantWaybillResource;
use App\Http\Resources\DriverRunsheetResource;
use App\Http\Resources\DriverRunsheetDetailResource;
use App\Http\Resources\InvoiceResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ShipmentResource;
use App\Models\DriverShipmentAssignment;
use App\Models\Account;
use App\Models\Address;
use App\Models\Merchant;
use App\Models\MerchantCommission;
use App\Models\MerchantPickupShipment;
use App\Models\MerchantSetting;
use App\Models\MerchantWaybill;
use App\Models\Consignee;
use App\Models\DriverRunsheet;
use App\Models\DriverRunsheetShipment;
use App\Models\DriverWaybill;
use App\Models\Invoice;
use App\Models\InvoiceShipment;
use App\Models\ShipmentDelivery;
use App\Models\ShipmentFinance;
use App\Models\ShipmentHistory;
use App\Models\ShipmentInformation;
use App\Models\ShipmentItem;
use App\Models\Scopes\ConsigneeScope;
use App\Models\Setting;
use App\Models\Shipper;
use App\Models\ShipperCommission;
use App\Models\State;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\ConsigneeOFDNotification;
use App\Notifications\ShipmentCreatedNotification;
use App\Services\AddressUpdateLinkService;
use App\Services\ShipmentPickupService;
use App\Services\ShipmentValidationService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use App\Enums\MerchantPickupTaskStatusEnum;
use App\Enums\ShipmentStatusEnum;
use App\Models\DriverBonusesTransaction;
use App\Models\Scopes\ExcludeReturnShipmentsScope;


use OpenApi\Annotations as OA;

/**
 * @group Driver App
 *
 * Controller managing driver-specific shipment operations and delivery workflows
 *
 * Handles complete shipment lifecycle for drivers including:
 * - Shipment confirmation/acceptance
 * - Delivery proof management
 * - COD handling and payment tracking
 * - Return/exemption processing
 * - Mobile app integration endpoints
 * - Real-time delivery metrics
 */
class DriverShipmentController extends Controller
{
    /**
     * Get Driver's Pending Shipments
     *
     * Retrieve all unconfirmed pending shipments assigned to the authenticated driver.
     * These are shipments in DISPATCH status that need driver confirmation.
     *
     * @OA\Get(
     *     path="/driver/shipments/my-shipments/pending",
     *     summary="Get driveror's pending shipments",
     *     description="Retrieve all pending delivery shipments assigned to the authenticated driver",
     *     operationId="getDriverPendingShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Pending shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="My shipments"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/DriverShipment")
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function myPendingShipments()
    {
        try {
            $driverId = Auth::id();

            $shipments = Shipment::with(['consignee', 'shipment_information', 'current_assignment', 'driver.driver'])
                ->where('driver_id', $driverId)
                ->where('status', 'DISPATCH')
                ->get();

            if ($shipments->isEmpty()) {
                return sendResponse('No shipments assigned to this driver.', [], true, [], 200);
            }

            return sendResponse('My shipments', new ShipmentResource($shipments), true, [], 200);
        } catch (Exception $e) {
            return sendResponse('Something went wrong', [], true, $e->getMessage(), 500);
        }
    }

    /**
     * Get Driver's Confirmed Shipments
     *
     * Retrieve all confirmed shipments that are ready for delivery (OFD status).
     * These shipments have been signed and are out for delivery.
     *
     * @OA\Get(
     *     path="/driver/shipments/my-shipments/signed",
     *     summary="Get driver's confirmed shipments",
     *     description="Retrieve all confirmed shipments assigned to the authenticated driver that are ready for delivery",
     *     operationId="getDriverSignedShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Confirmed shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="My shipments"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/DriverShipment")
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function mySignedShipments()
    {
        try {
            $driverId = Auth::id();

            $shipments = Shipment::with([
                'consignee.place',
                'consignee.state',
                'consignee.governorate',
                'shipment_information',
                'shipment_delivery',
                'current_assignment',
            ])
                ->where('driver_id', $driverId)
                ->where('status', 'OFD')
                ->get();

            if ($shipments->isEmpty()) {
                return sendResponse('no confirmed shipments.', [], true, [], 200);
            }

            return sendResponse('My shipments', ShipmentResource::collection($shipments), true, [], 200);
        } catch (Exception $e) {
            return sendResponse('Something went wrong', [], true, $e->getMessage(), 500);
        }
    }
    /**
     * Get Mobile App Homepage Statistics
     *
     * Retrieve comprehensive dashboard statistics for the driver mobile app homepage.
     * Includes delivery metrics, payment totals, and pickup task breakdown.
     *
     * @OA\Get(
     *     path="/mobile_app_homepage_stats",
     *     summary="Get mobile app homepage statistics",
     *     description="Retrieve dashboard statistics for driver mobile app homepage including delivery metrics and payment totals",
     *     operationId="getMobileAppStats",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Homepage statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="My shipments"),
     *             @OA\Property(property="data", ref="#/components/schemas/HomepageStats"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function mobile_app_homepage_stats()
    {
        try {
            $driverId = Auth::id();

            $todayDeliveries = Shipment::where('driver_id', $driverId)
                ->whereDate('created_at', Carbon::today())
                ->get();

            $todayCash = $todayDeliveries->sum('payment_cash');
            $todayBankTransfer = $todayDeliveries->sum('payment_bank_transfer');

            $totalAssigned = Shipment::where('driver_id', $driverId)
                ->where('payment_type', 'COD')
                ->sum('total_cod');

            $notConfirmed = Shipment::where('driver_id', $driverId)
                ->where('status', 'DISPATCH')
                ->count();

            $needsToDeliver = Shipment::where('driver_id', $driverId)
                ->where('status', 'OFD')
                ->count();

            $delivered = Shipment::where('driver_id', $driverId)
                ->where('status', 'DELIVERED')
                ->count();

            $pickupTotalAssigned = MerchantPickupTask::where('driver_id', $driverId)
                ->sum('no_of_shipments');

            $pickupNotConfirmed = MerchantPickupTask::where('driver_id', $driverId)
                ->where('status', 'to_pick')
                ->count();

            $pickupNeedsToPickup = MerchantPickupTask::where('driver_id', $driverId)
                ->where('status', 'picked')
                ->count();

            $notPickedShipments = MerchantPickupTask::where('driver_id', $driverId)
                ->where('status', 'not_picked')
                ->count();

            $pickupDelivered = MerchantPickupTask::where('driver_id', $driverId)
                ->where('status', 'pickup_complete')
                ->count();

            return sendResponse('My shipments', [
                'delivery' => [
                    'total_assigned' => $totalAssigned,
                    'not_confirmed' => $notConfirmed,
                    'needs_to_deliver' => $needsToDeliver,
                    'delivered' => $delivered,
                    'today_payment_cash' => $todayCash,
                    'today_payment_bank' => $todayBankTransfer,
                ],
                'pickup' => [
                    'total_assigned' => $pickupTotalAssigned,
                    'to_pickup' => $pickupNotConfirmed,
                    'not_picked' => $notPickedShipments,
                    'picked' => $pickupNeedsToPickup,
                    'pickup_completed' => $pickupDelivered,
                ],
            ], true, [], 200);
        } catch (Exception $e) {
            return sendResponse('Something went wrong', [], true, $e->getMessage(), 500);
        }
    }

    /**
     * Get Driver's Completed Shipments
     *
     * Retrieve the delivery history of all successfully completed shipments.
     * Shows shipments with DELIVERED status including delivery timeline data.
     *
     * @OA\Get(
     *     path="/driver/shipments/my-shipments/completed",
     *     summary="Get driver's completed shipments",
     *     description="Retrieve all completed shipments delivered by the authenticated driver",
     *     operationId="getDriverCompletedShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Completed shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="My shipments"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/DriverShipment")
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function myCompletedShipments()
    {
        try {
            $driverId = Auth::id();

            // Shipments delivered by me
            $shipments = Shipment::with(['consignee', 'shipment_delivery', 'current_assignment'])
                ->where('driver_id', $driverId)
                ->where('status', 'DELIVERED') // delivered status label
                ->get();

            if ($shipments->isEmpty()) {
                return sendResponse('No completed shipments', [], true, [], 200);
            }

            return sendResponse('My shipments', ShipmentResource::collection($shipments), true, [], 200);
        } catch (Exception $e) {
            return sendResponse('Something went wrong', [], true, $e->getMessage(), 500);
        }
    }

    /**
     * Get Driver Shipments with Metrics
     *
     * Retrieve today's assigned shipments for drivers with comprehensive metrics.
     * Supports search functionality and provides detailed shipment analytics.
     *
     * @OA\Get(
     *     path="/driver/driver_shipments",
     *     summary="Get driver shipments with metrics",
     *     description="Retrieve driver shipments for today with COD/paid counts and shipment details",
     *     operationId="getDriverShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="Search query for driver name"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="My shipments"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="driver_id", type="integer", example=123),
     *                     @OA\Property(property="assigned_at", type="string", format="date-time"),
     *                     @OA\Property(property="cod_count", type="integer", example=5),
     *                     @OA\Property(property="paid_count", type="integer", example=3),
     *                     @OA\Property(property="shipment_count", type="integer", example=8),
     *                     @OA\Property(property="cod_amount", type="number", format="float", example=1250.50),
     *                     @OA\Property(property="shipments", type="array", @OA\Items(ref="#/components/schemas/DriverShipment"))
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function driver_shipments()
    {
        $query = request()->input('query');

        $assignmentsQuery = DriverShipmentAssignment::select('driver_id', DB::raw('MIN(assigned_at) as assigned_at'))
            ->groupBy('driver_id');

        if ($query) {
            $assignmentsQuery = $assignmentsQuery->whereHas('driver', function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
            });
            $assignments = $assignmentsQuery->with('driver')->get();
        } else {
            $assignments = $assignmentsQuery->with('driver')->paginate(8);
        }
        foreach ($assignments as $assignment) {
            $driverId = $assignment->driver_id;

            $shipmentsQuery = Shipment::whereHas('driverAssignments', function ($q) use ($driverId) {
                $q->where('driver_id', $driverId)
                    ->whereDate('created_at', now());
            })->with('driverAssignments', 'consignee.governorate', 'consignee.state', 'consignee.place');
            $shipmentCollection = $shipmentsQuery->get();

            $assignment['cod_count'] = $shipmentCollection->where('payment_type', 'COD')->count();
            $assignment['paid_count'] = $shipmentCollection->where('payment_type', 'paid')->count();
            $assignment['shipment_count'] = $shipmentCollection->count();

            $assignment['shipments'] = $shipmentCollection;
            $assignment['cod_amount'] = $shipmentsQuery
                ->where('payment_type', 'COD')
                ->sum('total_cod');
        }

        return sendResponse('My shipments', new ShipmentResource($assignments));
    }

    /**
     * Assign Local Shipment to Driver
     *
     * Assign a local merchant shipment to the authenticated driver.
     * Creates shipment assignment and logs the assignment history.
     *
     * @OA\Post(
     *     path="/driver/assign_local_shipment",
     *     summary="Assign local shipment to driver",
     *     description="Assign a local shipment to driver and create assignment history",
     *     operationId="assignLocalShipment",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_no", type="string", example="PE041225123456", description="Shipment tracking number from merchant waybill")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment assigned successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="shipment_id", type="integer"),
     *                     @OA\Property(property="driver_id", type="integer"),
     *                     @OA\Property(property="assigned_at", type="string", format="date-time"),
     *                     @OA\Property(property="delivered_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Shipment already assigned or validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Assignment error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function assign_local_shipment(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:merchant_waybills,tracking_no',
        ]);

        $trackingNo = trim($request->tracking_no);
        $driver_id = Auth::id();

        try {

            $shipment = Shipment::where('tracking_no', $trackingNo)->firstOrFail();

            $existingAssignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->where('driver_id', $driver_id)
                ->first();

            if ($existingAssignment) {
                return sendResponse("", [], false, ["This shipment is already assigned to you."], 422);
            }

            $existingAssignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)->first();

            if ($existingAssignment) {
                $existingAssignment->delete();
            }

            $driverShipmentAssignment = new DriverShipmentAssignment();
            $driverShipmentAssignment->shipment_id = $shipment->id;
            $driverShipmentAssignment->driver_id = $driver_id;
            $driverShipmentAssignment->from_merchant = 1;
            $driverShipmentAssignment->assigned_by = Auth::id();
            $driverShipmentAssignment->assigned_at = now();

            $status = "LOCAL_ORDER_ASSIGNED";

            $driverShipmentAssignment->save();
            $shipment->save();

            $historyData = [
                "status" => status($status)['label'],
                "description" => status($status)['description'],
                "shipment_id" => $shipment->id,
            ];

            shipmentHistory($historyData);

            updateShipmentStatus($shipment->id, status($status)['label']);
            $driver_shipments = DriverShipmentAssignment::where('driver_id', $driver_id)
                ->with('shipment', 'driver')
                ->get();
            return sendResponse("Shipment assigned successfully.", new ShipmentResource($driver_shipments));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendResponse("Shipment not found.", [], [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("An error occurred while assigning the shipment.", [], [$e->getMessage()], 500);
        }
    }

    /**
     * Log Customer Contact Attempt
     *
     * Record a contact attempt made by the driver for delivery coordination.
     * Tracks call metrics and increments the contact attempt counter.
     *
     * @OA\Post(
     *     path="/driver/shipments/contact_count",
     *     summary="Log contact attempt for shipment",
     *     description="Record contact attempt made by driver for delivery coordination with call metrics",
     *     operationId="logContactAttempt",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_id", type="integer", example=1234),
     *             @OA\Property(property="number", type="string", example="+966512345678"),
     *             @OA\Property(property="ring_duration", type="integer", example=15),
     *             @OA\Property(property="call_duration", type="integer", example=30)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact attempt logged successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Count updated"),
     *             @OA\Property(property="data", ref="#/components/schemas/DriverShipment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function contact_count(Request $request)
    {
        $shipment = Shipment::find($request->shipment_id);
        if (!$shipment) {
            return sendResponse("Shipment not found.", [], false, ["Shipment not found"], 404);
        }

        $description = "Number: " . $request->number . " Ring Duration: " . $request->ring_duration . " Call Duration: " . $request->call_duration ?? "N\A";

        DB::transaction(function () use ($shipment, $description) {
            $shipment->shipment_delivery->increment('driver_call_count');

            $historyData = [
                "status" => "CONTACT",
                "description" => $description,
                "shipment_id" => $shipment->id,
            ];
            shipmentHistory($historyData);
        });

        return sendResponse("Count updated", $shipment->fresh()->load('consignee', 'shipment_delivery'));
    }

    /**
     * Complete Shipment Delivery
     *
     * Mark an shipment as delivered with payment processing and proof of delivery.
     * Handles COD transactions, updates financial records, and creates delivery history.
     *
     * @OA\Post(
     *     path="/driver/shipments/deliver",
     *     summary="Mark shipment as delivered",
     *     description="Complete shipment delivery with proof, payment handling, and location tracking",
     *     operationId="deliverShipment",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="assignment_id", type="integer", example=1234, description="Shipment ID to deliver"),
     *                 @OA\Property(property="shipment_id", type="integer", example=1234, description="Shipment ID to deliver"),
     *                 @OA\Property(property="delivery_lat", type="number", format="float", example=24.7136, description="Delivery latitude"),
     *                 @OA\Property(property="delivery_lng", type="number", format="float", example=46.6753, description="Delivery longitude"),
     *                 @OA\Property(property="payment_cash", type="number", format="float", example=250.50, description="Cash payment amount"),
     *                 @OA\Property(property="payment_bank_transfer", type="number", format="float", example=0, description="Bank transfer amount"),
     *                 @OA\Property(property="customer_delivery_fee", type="number", format="float", example=15.00, description="Customer paid delivery fee"),
     *                 @OA\Property(property="description", type="string", example="Delivered to front door", description="Additional delivery notes"),
     *                 @OA\Property(property="proof", type="string", format="binary", description="Proof of delivery image")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment delivered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment delivered successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/DriverShipment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=402,
     *         description="Missing payment proof",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Payment amount mismatch",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */

    // مثال داخل Order::approveDeliveryAddressOnDeliver أو في Service التسليم


    public function deliver(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required_without:shipment_id|exists:driver_shipment_assignments,id',
            'shipment_id' => 'required_without:assignment_id|exists:shipments,id',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
            'payment_cash' => 'nullable|numeric|min:0',
            'payment_bank_transfer' => 'nullable|numeric|min:0',
            'customer_delivery_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'proof' => 'nullable|file|image|max:10240',
        ]);

        DB::beginTransaction();
        try {
            $shipment = null;
            $assignment = null;

            if ($request->filled('assignment_id')) {
                $assignment = DriverShipmentAssignment::with('shipment.shipment_delivery', 'shipment.deliveryAddress')->find($request->assignment_id);
                if (
                    !$assignment ||
                    $assignment->driver_id !== Auth::id() ||
                    !in_array($assignment->status, ['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DEFERRED'])
                ) {
                    return sendResponse("Shipment not found or not ready for delivery.", [], false, ['Invalid assignment or status'], 422);
                }
                $shipment = $assignment->shipment;
            } elseif ($request->filled('shipment_id')) {
                $shipment = Shipment::withoutGlobalScope(ExcludeReturnShipmentsScope::class)->with('shipment_delivery', 'deliveryAddress')->find($request->shipment_id);
                if (!$shipment) {
                    return sendResponse("Shipment not found.", [], false, ['Shipment not found'], 422);
                }
                $assignment = DriverShipmentAssignment::where('shipment_tracking_no', $shipment->tracking_no)->first();
            } else {
                return sendResponse("Either assignment_id or shipment_id is required.", [], false, ['Missing required parameter'], 422);
            }

            $hasDeferFlag = \App\Models\ShipmentDelivery::where('shipment_id', $shipment->id)
                ->whereRaw("UPPER(TRIM(deliver_later_reason)) = ?", [DeliveryExceptionEnum::DELIVER_LATER_TODAY])
                ->exists();

            $hasHistoryFlag = \App\Models\ShipmentHistory::where('shipment_id', $shipment->id)
                ->whereRaw("UPPER(TRIM(type)) = ?", [DeliveryExceptionEnum::DELIVER_LATER_TODAY])
                ->orderByDesc('id')
                ->exists();

            $isLaterToday = $hasDeferFlag || $hasHistoryFlag;
            $deferUntil = optional($shipment->shipment_delivery)->deliver_later_until;
            $pastDeferTime = !$deferUntil || now()->greaterThanOrEqualTo($deferUntil);
            // -------------------------------------------------------------------------------

            $validationService = new ShipmentValidationService();
            if ($validationService->isShipmentDelivered($shipment)) {
                return sendResponse("Shipment is already delivered.", [], false, ["Shipment is already delivered."], 422);
            }

            if (!$validationService->isShipmentAssigned($shipment, $assignment->driver_id ?? null)) {
                return sendResponse("Shipment is not assigned.", [], false, ["Shipment is not assigned."], 422);
            }

            if ($shipment->shipment_information->in_warehouse ?? false) {
                return sendResponse("Delivery Error: Shipment is in warehouse.", [], false, ['Delivery Error: Shipment is in warehouse cannot deliver'], 422);
            }

            if (!$shipment->current_assignment) {
                return sendResponse("Delivery Error: Shipment is not assigned.", [], false, ['Delivery Error: Shipment is not assigned'], 422);
            }

            $shipmentInException = $validationService->isShipmentInException($shipment) || (bool) $shipment->in_exception;

            if ($shipmentInException) {
                // مسموح لو الاستثناء Later Today فقط، ويُفضّل يكون عدى وقته لو فيه until
                if (!($isLaterToday && $pastDeferTime)) {
                    return sendResponse("Shipment is in exception.", [], false, ["Shipment is in exception."], 422);
                }
            } else {
                // المسار الطبيعي: لازم يكون OFD
                if (!$validationService->isShipmentOFD($shipment)) {
                    return sendResponse("Shipment is not out for delivery.", [], false, ['Shipment is not out for delivery'], 422);
                }
            }
            // ----------------------------------------------------------------

            $extra_description = $request->description ?? '';
            $paymentCash = $request->payment_cash ?? 0;
            $paymentBankTransfer = $request->payment_bank_transfer ?? 0;
            $totalPayment = $paymentCash + $paymentBankTransfer;
            $expectedCollectible = (float) ($shipment->total_cod ?? 0);
            if (abs($totalPayment - $expectedCollectible) > 0.009) {
                return sendResponse(
                    "The sum of cash and bank transfer payments does not match the shipment amount.",
                    [],
                    false,
                    ["Payment total mismatch: Received: {$totalPayment} vs Expected: " . number_format($expectedCollectible, 2, '.', '')],
                    422
                );
            }

            // حفظ بيانات التسليم
            $shipment->shipment_delivery->payment_cash = $paymentCash;
            $shipment->shipment_delivery->payment_bank_transfer = $paymentBankTransfer;
            $shipment->shipment_delivery->delivery_lat = $request->delivery_lat ?? 0;
            $shipment->shipment_delivery->delivery_lng = $request->delivery_lng ?? 0;

            // نظّف أعلام التأجيل فقط لو كنا في سيناريو Later Today
            if ($isLaterToday) {
                $shipment->shipment_delivery->deliver_later_reason = null;
                $shipment->shipment_delivery->deliver_later_until = null;
            }

            $shipment->shipment_delivery->save();
            $driverId = $assignment->driver_id ?? Auth::id();

            $lat = $shipment->shipment_delivery->delivery_lat ?? null;
            $lng = $shipment->shipment_delivery->delivery_lng ?? null;

            $shipment->approveDeliveryAddressOnDeliver((int) $driverId, $lat, $lng);

            $status = "DELIVERED";
            $historyData = [
                "status" => status($status)['label'],
                "description" => trim(status($status)['description'] . ' ' . ($extra_description ? '[' . $extra_description . ']' : '')),
                "shipment_id" => $shipment->id,
            ];

            if ($request->has('proof')) {
                $historyData['proof'] = uploadFile($request->proof, 'public/deliveries/proofs');
            }

            if ($shipment->payment_type == 'Paid') {
                // if (empty($historyData['proof'])) {
                //     return sendResponse("Please upload proof.", [], false, 402);
                // }

                if ($shipment->fee_payer == "customer") {
                    if ($request->customer_delivery_fee) {
                        $shipment->shipment_finance->update(['delivery_fee_paid_by_customer' => $request->customer_delivery_fee]);
                        $shipment->shipment_finance->save();
                    } else {
                        return sendResponse("Error occured", [], false, ["Please Enter Customer Delivery fees"], 500);
                    }
                } else if ($shipment->fee_payer == "client") {
                    $client_invoice = Invoice::where('invoiceable_id', $shipment->client_id)
                        ->where('invoiceable_type', User::class)
                        ->where('status', 'pending')
                        ->first();

                    if (!$client_invoice) {
                        $client_invoice = Invoice::create([
                            'invoiceable_id' => $shipment->client_id,
                            'invoiceable_type' => User::class,
                            'status' => 'pending',
                            'amount' => 0,
                        ]);
                    }

                    InvoiceShipment::create([
                        "invoice_id" => $client_invoice->id,
                        "shipment_tracking_no" => $shipment->tracking_no
                    ]);
                }
            }
            // حسابات السائق - لأوامر COD فقط
            if (strtoupper($shipment->payment_type) !== 'PAID') {
                $transactionAmount = (float) (($shipment->shipment_delivery->payment_cash ?? 0) + ($shipment->shipment_delivery->payment_bank_transfer ?? 0));

                if ($transactionAmount > 0) {
                    $driverId = $assignment->driver_id ?? $shipment->driver_id;

                    $driverAccount = Account::firstOrCreate([
                        'accountable_id' => $driverId,
                        'accountable_type' => User::class
                    ]);

                    $driverAccount->parcel_value = (float) ($driverAccount->parcel_value ?? 0) - $transactionAmount;

                    $driverAccount->cash_balance = (float) ($driverAccount->cash_balance ?? 0) + $transactionAmount;
                    $driverAccount->save();
                }
                $isBackofficeCreated = $shipment->owner_id && $shipment->owner_type &&
                    !(
                        $shipment->owner_type === User::class &&
                        (int) $shipment->owner_id === (int) ($shipment->client_id ?? 0)
                    );

                if ($isBackofficeCreated) {
                    Transaction::create([
                        "from_id" => $shipment->client_id,
                        "from_type" => User::class,
                        "to_id" => $shipment->owner_id,
                        "to_type" => $shipment->owner_type,
                        "shipment_id" => $shipment->id,
                        "amount" => (float) ($shipment->total_cod ?? 0),
                        "type" => "client_delivered",
                    ]);
                } else {
                    // حالة الطلب اللي اتعمل من العميل نفسه — كنا زمان بنسجل to_id = Auth::user()->id
                    // دلوقتي بنسجل للعميل نفسه (client_id) وقت التسليم
                    if ($shipment->client_id) {
                        Transaction::create([
                            "to_id" => $shipment->client_id,
                            "to_type" => User::class,
                            "shipment_id" => $shipment->id,
                            "amount" => (float) ($shipment->total_cod ?? 0),
                            "type" => "client_delivered",
                        ]);
                    }
                }

                // DUAL-WRITE: Record COD in unified merchant transactions
                if ($shipment->merchant_id) {
                    \Illuminate\Support\Facades\Log::info("Web DriverShipmentController: unconditional recordCOD for Shipment #{$shipment->id}");
                    app(\App\Services\MerchantTransactionService::class)->recordCOD($shipment);
                }
            }
            if ($shipment->client_id) {
                if (strtoupper($shipment->payment_type) !== 'PAID') {
                    $clientAccount = Account::firstOrCreate(
                        [
                            'accountable_type' => User::class,
                            'accountable_id' => $shipment->client_id,
                        ],
                        [
                            'parcel_value' => 0,
                            'balance' => 0,
                        ]
                    );

                    $clientAccount->parcel_value = (float) ($clientAccount->parcel_value ?? 0) + (float) ($shipment->total_cod ?? 0);
                    $clientAccount->save();
                }
            }


            if ($assignment) {
                $assignment->update([
                    'delivered_at' => now(),
                    'status' => 'DELIVERED'
                ]);
            }

            shipmentHistory($historyData);

            updateShipmentStatus($shipment->id, status($status)['label']);

            DriverRunsheetShipment::where("shipment_tracking_no", $shipment->tracking_no)->update([
                "status" => "delivered"
            ]);

            $shipment->update(["delivered_at" => now()]);

            if (($shipment->direction ?? null) === 'return_to_origin'
                && ($shipment->return_kind ?? null) === 'reverse_pickup'
                && $shipment->parent_reverse_shipment_id) {
                try {
                    app(\App\Services\ReversePickup\ReverseShipmentService::class)
                        ->markAsReturnedToMerchant($shipment->parent_reverse_shipment_id);
                } catch (\Throwable $syncEx) {
                    \Log::warning('Failed to sync reverse shipment on return leg delivery', [
                        'shipment_id' => $shipment->id,
                        'reverse_shipment_id' => $shipment->parent_reverse_shipment_id,
                        'error' => $syncEx->getMessage(),
                    ]);
                }
            }

            // حسابات السائق (تمت معالجتها أعلاه لأوامر COD). لا حركة نقدية لأوامر Paid هنا.
            if (strtoupper($shipment->payment_type) !== 'PAID') {
                // تم تحديث أرصدة السائق بالفعل بناءً على $totalPayment في القسم السابق
            }

            $now = now()->format('Y-m-d H:i:s');
            activityLog("shipment_delivered", "Shipment #{$shipment->tracking_no} has been delivered at {$now} by {$shipment->driver->name}");
            $driverId = $assignment->driver_id ?? Auth::id();

            DB::commit();

            return sendResponse("Shipment marked as Delivered", $shipment->load('consignee', 'shipment_delivery', 'shipment_delivery'));
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error occured", [], false, [$e->getMessage()], 500);
        }
    }



    /**
     * Return Shipment with Proof
     *
     * Process shipment return by driver with required proof of return.
     * Creates return history and stores proof documentation.
     *
     * @OA\Post(
     *     path="/driver/return_shipment",
     *     summary="Return shipment with proof",
     *     description="Process shipment return with proof of return documentation",
     *     operationId="returnShipment",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="shipment_id", type="integer", example=1234, description="Shipment ID to return"),
     *                 @OA\Property(property="proof", type="string", format="binary", description="Proof of return image/document")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment returned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment returned successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/DriverShipment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=402,
     *         description="Proof of return required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function return_shipment(Request $request)
    {
        $shipment = Shipment::find($request->shipment_id);
        if ($request->has('proof')) {
            $status = "RETURN";
            $historyData = [
                "status" => status($status)['label'],
                "description" => status($status)['description'],
                "shipment_id" => $shipment->id,
                "proof" => uploadFile($request->proof, 'public/returns/proofs')
            ];
            shipmentHistory($historyData);

            updateShipmentStatus($shipment->id, status($status)['label']);
        } else {
            return sendResponse("Please upload proof.", [], [], 402);
        }
    }

    /**
     * Get Today's Driver Shipments
     *
     * Retrieve today's shipment assignments with driver details and metrics.
     * Supports search functionality and provides shipment counts per driver.
     *
     * @OA\Get(
     *     path="/driver/today_shipments",
     *     summary="Get today's driver shipments",
     *     description="Retrieve today's shipment assignments with driver search and shipment counts",
     *     operationId="getTodayShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         description="Search query for driver name"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Today's shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="My shipments"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="driver_id", type="integer", example=123),
     *                     @OA\Property(property="shipment_count", type="integer", example=8),
     *                     @OA\Property(property="driver", ref="#/components/schemas/DriverUser"),
     *                     @OA\Property(property="shipment", ref="#/components/schemas/DriverShipment"),
     *                     @OA\Property(property="shipments", type="array", @OA\Items(ref="#/components/schemas/DriverShipment"))
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function today_shipments()
    {
        $shipments = DriverShipmentAssignment::query();
        if (request()->has('query')) {
            $query = request()->input('query');
            $shipments = $shipments
                ->whereHas('driver', function ($q) use ($query) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
                })

                ->with('driver', 'shipment')
                ->get();
        } else {
            $shipments = $shipments->with('driver', 'shipment')->paginate(8);
        }

        foreach ($shipments as $shipment) {
            $driver_id = $shipment->driver_id;
            $shipment['shipment_count'] = DriverShipmentAssignment::where('driver_id', $driver_id)->count();
            $shipment['shipments'] = Shipment::whereHas('driverAssignments', function ($q) use ($driver_id) {
                $q->where('driver_id', $driver_id);
            })->get();
        }

        return sendResponse('My shipments', new ShipmentResource($shipments));
    }

    /**
     * Generate WhatsApp message template for customer
     *
     * @param Request $request Requires shipmentId
     * @return \Illuminate\Http\JsonResponse
     *   - Preformatted delivery message
     *   - 404 if parcel not found
     */
    /**
     * Send WhatsApp Message to Customer
     *
     * Generate and send a WhatsApp notification message to the customer
     * using predefined templates for delivery updates.
     *
     * @OA\Post(
     *     path="/driver/shipments/sendWhatsappMessage",
     *     summary="Send WhatsApp message to customer",
     *     description="Send delivery notification via WhatsApp to customer using shipment templates",
     *     operationId="sendWhatsAppMessage",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipmentId", type="integer", example=1234, description="Shipment ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="WhatsApp message template generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Hello! Your shipment PE041225123456 is out for delivery...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Parcel not found")
     *         )
     *     )
     * )
     */
    public function sendWhatsappMessage(Request $request)
    {

        $parcel = Shipment::findOrFail($request->shipmentId);
        if (!$parcel) {
            return response()->json(['error' => 'Parcel not found'], 404);
        }
        $message = whatsAppTemplate($parcel);

        return response()->json([
            'message' => $message
        ]);
    }

    /**
     * Get Undelivered Shipments
     *
     * Retrieve shipments that could not be delivered due to various exceptions.
     * Shows shipments with in_exception flag and their delivery attempt history.
     *
     * @OA\Get(
     *     path="/driver/not_deliver",
     *     summary="Get undelivered shipments",
     *     description="Retrieve shipments that could not be delivered due to exceptions",
     *     operationId="getUndeliveredShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Undelivered shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipments not delivered"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/DriverShipment")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function not_deliver()
    {
        $driverId = Auth::id();
        $shipments = Shipment::with(['core_exception', 'shipment_delivery', 'consignee', 'current_assignment', 'driver.driver'])
            ->where('driver_id', $driverId)
            ->where('in_exception', true)
            ->get();

        return sendResponse('Shipments not delivered', ShipmentResource::collection($shipments));
    }
    /**
     * Confirm Shipment Delivery
     *
     * Validate and confirm shipment delivery by retrieving complete shipment details.
     * Used for final delivery confirmation with all location and shipment information.
     *
     * @OA\Post(
     *     path="/driver/shipments/delivery_confirmation",
     *     summary="Confirm shipment delivery",
     *     description="Validate shipment for delivery confirmation and retrieve complete shipment details",
     *     operationId="confirmDelivery",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_no", type="string", example="PE041225123456", description="Shipment tracking number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment details retrieved for confirmation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment fetched successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/DriverShipment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or query error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function shipment_confirmation(Request $request)
    {
        $request->validate([
            "tracking_no" => "required"
        ]);
        try {
            $shipment = Shipment::with(
                "consignee.city",
                "shipper.country",
                "shipper.city",
                "shipment_information",
                "shipment_amounts"
            )
                ->where('tracking_no', request()->tracking_no)
                ->firstOrFail();
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching the shipment.", [], [$e->getMessage()], 422);
        }

        return sendResponse("Shipment fetched successfully.", new ShipmentResource($shipment));
    }


    /**
     * Reverse Shipment Cancellation
     *
     * Reverse a cancelled shipment exception and restore it to out-for-delivery status.
     * Used when customer changes mind after cancellation.
     *
     * @OA\Post(
     *     path="/driver/shipments/reverse_cancellation",
     *     summary="Reverse shipment cancellation",
     *     description="Reverse cancelled shipment exception and restore to delivery status",
     *     operationId="reverseCancellation",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_id", type="integer", example=1234, description="Shipment ID to reverse cancellation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cancellation reversed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment cancellation has been reversed successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/DriverShipment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid shipment state or validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Database transaction error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function reverse_cancellation(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id'
        ]);

        DB::beginTransaction();
        try {
            $shipment = Shipment::findOrFail($request->shipment_id);

            if (!$shipment->in_exception || $shipment->status !== 'DELIVERY_EXCEPTION' || $shipment->coreStatus()->type !== DeliveryExceptionEnum::CANCELLED) {
                return sendResponse("Shipment is not in a cancelled state.", [], false, ["Invalid shipment state"], 422);
            }

            $driverAssignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)->first();

            if ($driverAssignment) {
                $driverAssignment->returned_at = null;
                $driverAssignment->save();
            }

            DriverRunsheetShipment::where("shipment_tracking_no", $shipment->tracking_no)->update([
                "status" => "assigned"
            ]);

            shipmentHistory([
                "shipment_id" => $shipment->id,
                "status" => "CANCELLATION_REVERSED",
                "description" => "Shipment cancellation has been reversed by the driver."
            ]);

            shipmentHistory([
                "shipment_id" => $shipment->id,
                "status" => "OFD",
                "description" => "Out for Delivery after reversing cancellation."
            ]);

            $shipment->in_exception = false;
            $shipment->status = 'OFD';
            $shipment->save();

            DB::commit();
            return sendResponse("Shipment cancellation has been reversed successfully.", $shipment->fresh(), true);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while reversing the cancellation.", [], false, [$e->getMessage()], 500);
        }
    }


    /**
     * Reverse Shipment Exception
     *
     * Reverse various shipment exceptions (CANCELLED, NOANSWER, WRONGCITY, FUTUREDELIVERY)
     * and restore shipment to out-for-delivery status.
     *
     * @OA\Post(
     *     path="/driver/shipments/reverse_exception",
     *     summary="Reverse shipment exception",
     *     description="Reverse any shipment exception and restore to delivery status",
     *     operationId="reverseException",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_id", type="integer", example=1234, description="Shipment ID to reverse exception")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exception reversed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Exception reversed successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/DriverShipment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Shipment not in exception state",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Database transaction error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function reverse_exception(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id'
        ]);

        DB::beginTransaction();
        try {
            $shipment = Shipment::findOrFail($request->shipment_id);

            // Ensure the shipment is in exception
            if (!$shipment->in_exception) {
                return sendResponse("Shipment is not in an exception state.", [], false, ["Invalid shipment state"], 422);
            }

            // Determine the exception type (e.g. CANCELLED, NOANSWER, WRONGCITY, FUTUREDELIVERY)
            $exceptionType = optional($shipment->core_status)->type;

            // Roll back driver assignment for cancellations
            if ($exceptionType === DeliveryExceptionEnum::CANCELLED) {
                $assignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)->first();
                if ($assignment) {
                    $assignment->returned_at = null;
                    $assignment->save();
                }
            }

            // Always reset runsheet shipment back to assigned
            DriverRunsheetShipment::where('shipment_tracking_no', $shipment->tracking_no)
                ->update(['status' => 'assigned']);

            // Log a reversal event
            shipmentHistory([
                'shipment_id' => $shipment->id,
                'status' => "{$exceptionType}_REVERSED",
                'description' => "Reversed exception: {$exceptionType}."
            ]);

            // Log transition back to OFD
            shipmentHistory([
                'shipment_id' => $shipment->id,
                'status' => 'OFD',
                'description' => 'Out for Delivery after reversing exception.'
            ]);

            $shipment->in_exception = false;
            $shipment->status = 'OFD';
            $shipment->save();

            DB::commit();
            return sendResponse("Exception reversed successfully.", $shipment->fresh(), true);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse(
                "An error occurred while reversing the exception.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }


    /**
     * Get Delivered Shipments with Commissions
     *
     * Retrieve delivered shipments for the authenticated driver with commission calculations.
     * Supports date range filtering for performance tracking.
     *
     * @OA\Post(
     *     path="/driver/delivered_shipments",
     *     summary="Get delivered shipments with commissions",
     *     description="Retrieve delivered shipments with commission calculations and date filtering",
     *     operationId="getDeliveredShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="from_date", type="string", format="date", example="2024-01-01", description="Start date filter"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2024-01-31", description="End date filter")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivered shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivered shipments with commissions"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_shipments", type="integer", example=25),
     *                 @OA\Property(property="total_commission", type="number", format="float", example=750.50),
     *                 @OA\Property(
     *                     property="shipments",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="shipment_id", type="integer", example=1234),
     *                         @OA\Property(property="tracking_no", type="string", example="PE041225123456"),
     *                         @OA\Property(property="delivered_at", type="string", format="date-time"),
     *                         @OA\Property(property="shipment_value", type="number", format="float", example=250.00),
     *                         @OA\Property(property="payment_type", type="string", example="COD"),
     *                         @OA\Property(
     *                             property="commission",
     *                             type="object",
     *                             @OA\Property(property="delivery_fee", type="number", format="float", example=15.00),
     *                             @OA\Property(property="pickup_fee", type="number", format="float", example=10.00),
     *                             @OA\Property(property="total_commission", type="number", format="float", example=25.00)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving delivered shipments",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function delivered_shipments(Request $request)
    {
        try {
            $driverId = Auth::id();
            $query = DriverShipmentAssignment::with(['shipment.state'])
                ->where('driver_id', $driverId)
                ->whereNotNull('delivered_at')
                ->whereHas('shipment', function ($q) {
                    $q->where('payment_status', 'completed');
                });

            // Date filtering
            if ($request->has('from_date') || $request->has('to_date')) {
                $request->validate([
                    'from_date' => 'nullable|date',
                    'to_date' => 'nullable|date|after_or_equal:from_date'
                ]);

                $query->when($request->from_date, function ($q) use ($request) {
                    $q->whereDate('delivered_at', '>=', Carbon::parse($request->from_date));
                })
                    ->when($request->to_date, function ($q) use ($request) {
                        $q->whereDate('delivered_at', '<=', Carbon::parse($request->to_date));
                    });
            } else {
                // Default to today's deliveries
                $query->whereDate('delivered_at', Carbon::today());
            }

            $shipments = $query->get()->map(function ($assignment) {
                $shipment = $assignment->shipment;
                $commission = $assignment->commission;

                return [
                    'shipment_id' => $shipment->id,
                    'tracking_no' => $shipment->tracking_no,
                    'delivered_at' => $assignment->delivered_at,
                    'commission' => [
                        'delivery_fee' => $commission->delivery_fee ?? 0,
                        'pickup_fee' => $commission->pickup_fee ?? 0,
                        'total_commission' => ($commission->delivery_fee ?? 0) + ($commission->pickup_fee ?? 0)
                    ],
                    'shipment_value' => $shipment->total_cod,
                    'payment_type' => $shipment->payment_type
                ];
            });

            return sendResponse(
                'Delivered shipments with commissions',
                [
                    'total_shipments' => $shipments->count(),
                    'total_commission' => $shipments->sum('commission.total_commission'),
                    'shipments' => $shipments
                ],
                true
            );
        } catch (Exception $e) {
            return sendResponse(
                'Error retrieving delivered shipments',
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }


    /**
     * Get Driver Runsheets
     *
     * Retrieve driver runsheets with assigned shipments and delivery details.
     * Supports date filtering for specific time periods.
     *
     * @OA\Get(
     *     path="/driver/driver_runsheets",
     *     summary="Get driver runsheets",
     *     description="Retrieve driver runsheets with assigned shipments and comprehensive location details",
     *     operationId="getDriverRunsheets",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date"),
     *         description="Filter runsheets by date (YYYY-MM-DD)"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver runsheets retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Driver Runsheets retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=123),
     *                         @OA\Property(property="status", type="string", example="confirmed"),
     *                         @OA\Property(property="confirmed_at", type="string", format="date-time"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(
     *                        property="assigned_shipments",
     *                        type="array",
     *                        @OA\Items(
     *                            type="object",
     *                            @OA\Property(property="id", type="integer"),
     *                            @OA\Property(property="shipment_tracking_no", type="string"),
     *                            @OA\Property(property="runsheet_id", type="integer"),
     *                            @OA\Property(property="status", type="string")
     *                       )
     *                    )
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */


    public function driver_runsheets_new(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'tz' => 'nullable|string',
            'query' => 'nullable|string|max:255',
            'include_shipments' => 'nullable|in:true,false,1,0',
            'shipments_per_page' => 'nullable|integer|min:1|max:100',
            'shipments_page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return sendResponse('Invalid input.', [], false, [$validator->errors()], 422);
        }

        $user = $request->user();

        if (!$user || !$user->hasRole(['Driver', 'Vendor Driver', 'Guest Driver'])) {
            return sendResponse(
                'You are not authorized to access the system.',
                [],
                false,
                [],
                403
            );
        }

        $driverId = $user->id;

        // ✅ Use TimezoneService to resolve timezone from request or user preference
        $timezoneService = app(\App\Services\TimezoneService::class);
        // Timezone is resolved directly from the user accessor
        $tz = $user->timezone;

        if (! $timezoneService->isValidTimezone($tz)) {
            return sendResponse('Invalid timezone identifier.', [], false, [], 422);
        }
        // Parse dates to UTC ranges using resolved timezone
        $dateRange = parseInputToUtcRange($request->date, $tz);
        $fromRange = parseInputToUtcRange($request->from, $tz);
        $toRange = parseInputToUtcRange($request->to, $tz);

        $search = $request->query('query');
        $perPageRunsheets = (int) $request->input('per_page', 15);
        $includeShipments = filter_var($request->input('include_shipments', 'true'), FILTER_VALIDATE_BOOLEAN);
        $shipmentsPerPage = $request->input('shipments_per_page');
        $shipmentsPage = (int) $request->input('shipments_page', 1);

        $runsheetsQuery = DriverRunsheet::query()
            ->where('driver_id', $driverId)
            ->when($dateRange, function ($query) use ($dateRange) {
                // Highest priority: date
                $query->whereBetween('created_at', $dateRange);
            }, function ($query) use ($fromRange, $toRange) {
                // Fallback: from / to
                if ($fromRange && $toRange) {
                    $query->whereBetween('created_at', [
                        $fromRange[0],
                        $toRange[1],
                    ]);
                } elseif ($fromRange) {
                    $query->where('created_at', '>=', $fromRange[0]);
                } elseif ($toRange) {
                    $query->where('created_at', '<=', $toRange[1]);
                }
            })
            ->select(
                'driver_runsheets.id',
                'driver_runsheets.status',
                'driver_runsheets.confirmed_at',
                'driver_runsheets.created_at'
            )
            ->withSum('deliveredShipments as total_delivered_cod', 'total_cod')
            ->with([
                'driver:id,name',
                'submission' => function ($q) {
                    $select = ['id', 'driver_runsheet_id', 'created_at'];
                    if (Schema::hasColumn('driver_runsheet_submissions', 'status')) {
                        $select[] = 'status';
                    } elseif (Schema::hasColumn('driver_runsheet_submissions', 'state')) {
                        $select[] = DB::raw('state as status');
                    } elseif (Schema::hasColumn('driver_runsheet_submissions', 'result')) {
                        $select[] = DB::raw('result as status');
                    }
                    $q->select($select)->orderByDesc('id');
                },
                'invoice' => function ($q) {
                    $select = ['id', 'driver_runsheet_id', 'invoice_no', 'created_at'];
                    if (Schema::hasColumn('invoices', 'total')) {
                        $select[] = 'total';
                    } elseif (Schema::hasColumn('invoices', 'grand_total')) {
                        $select[] = DB::raw('grand_total as total');
                    } elseif (Schema::hasColumn('invoices', 'amount')) {
                        $select[] = DB::raw('amount as total');
                    }
                    $q->select($select);
                }
            ])
            ->withCount([
                'assigned_shipments as shipments_count',
                'delivered_shipments as delivered_count',
                'not_delivered_shipments as not_delivered_count',
                'returned_shipments as returned_count',
                'holding_shipments as holding_count',
                'difference_shipments as difference_count',
            ]);

        // 🔍 Search logic untouched
        if ($search) {
            $runsheetsQuery->where(function ($outer) use ($search) {
                $outer->whereHas('assigned_shipments', function ($q) use ($search) {
                    $q->where('shipment_tracking_no', 'like', "%{$search}%");
                })
                    ->orWhereHas('assigned_shipments.shipment', function ($q) use ($search) {
                        $q->where(function ($w) use ($search) {
                            $w->where('tracking_no', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%");
                        });
                    });
            });
        }

        $runsheetsPaginator = $runsheetsQuery
            ->orderByDesc('created_at')
            ->paginate($perPageRunsheets)
            ->appends($request->query());

        $runsheetsWithShipments = $runsheetsPaginator->getCollection()->map(function ($runsheet) use ($includeShipments, $shipmentsPerPage, $shipmentsPage, $search, $request) {
            $result = [
                'id' => $runsheet->id,
                'timezone' => $runsheet->timezone,
                // 'status' => $runsheet->status,
                'confirmed_at' => $runsheet->confirmed_at,
                'created_at' => $runsheet->created_at,
                'created_at_in_timezone' => $runsheet->created_at_in_timezone,
                'shipments_count' => $runsheet->shipments_count,
                'delivered_count' => $runsheet->delivered_count,
                'not_delivered_count' => $runsheet->not_delivered_count,
                'returned_count' => $runsheet->returned_count,
                'holding_count' => $runsheet->holding_count,
                'difference_count' => $runsheet->difference_count,
                'matched_shipments_count' => isset($runsheet->matched_shipments_count) ? $runsheet->matched_shipments_count : null,
                'total_delivered_COD' => (float) ($runsheet->total_delivered_cod ?? 0),

                'driver' => $runsheet->relationLoaded('driver') && $runsheet->driver ? [
                    'id' => $runsheet->driver->id,
                    'name' => $runsheet->driver->name,
                ] : null,
                'invoice' => $runsheet->relationLoaded('invoice') && $runsheet->invoice ? [
                    'id' => $runsheet->invoice->id,
                    'invoice_no' => $runsheet->invoice->invoice_no ?? null,
                    'total' => $runsheet->invoice->total ?? null,
                    'created_at' => $runsheet->invoice->created_at,
                ] : null,
                'submission' => $runsheet->relationLoaded('submission') && $runsheet->submission ? [
                    'id' => $runsheet->submission->id,
                    'status' => $runsheet->submission->status ?? null,
                    'created_at' => $runsheet->submission->created_at,
                ] : null,
            ];

            if (!$includeShipments) {
                return $result + ['shipments' => ['data' => [], 'pagination' => null]];
            }
            $ccCache = [];
            $shipmentsQ = $runsheet->assigned_shipments()
                ->select('id', 'shipment_tracking_no', 'runsheet_id', 'status')
                ->with([

                    'shipment:id,consignee_id,merchant_id,tracking_no,value,total_cod,delivery_fee,delivery_fee_before_discount,delivery_fee_discount,payment_type,fee_payer,payment_type,status,exception_type,customer_name,delivery_address_id,is_return,return_to_type',

                    'shipment.shipment_delivery:id,shipment_id,deliver_later_reason,deliver_later_until',

                    'shipment.shipmentHistories' => function ($q) {
                        $select = ['id', 'shipment_id'];

                        // alias لعمود الحالة في shipment_histories
                        if (Schema::hasColumn('shipment_histories', 'status')) {
                            $select[] = 'status';
                            $statusCol = 'status';
                        } elseif (Schema::hasColumn('shipment_histories', 'state')) {
                            $select[] = DB::raw('state as status');
                            $statusCol = 'state';
                        } elseif (Schema::hasColumn('shipment_histories', 'event')) {
                            $select[] = DB::raw('event as status');
                            $statusCol = 'event';
                        } else {
                            $statusCol = null;
                        }

                        // alias لعمود نوع الاستثناء
                        if (Schema::hasColumn('shipment_histories', 'type')) {
                            $select[] = 'type';
                            $typeCol = 'type';
                        } elseif (Schema::hasColumn('shipment_histories', 'reason')) {
                            $select[] = DB::raw('reason as type');
                            $typeCol = 'reason';
                        } elseif (Schema::hasColumn('shipment_histories', 'code')) {
                            $select[] = DB::raw('code as type');
                            $typeCol = 'code';
                        } else {
                            $typeCol = null;
                        }

                        $q->select($select)->orderByDesc('id')->limit(1);

                        // فلترة حسب قيمة DELIVERY_EXCEPTION لو عندنا عمود حالة
                        if ($statusCol) {
                            $q->where($statusCol, 'DELIVERY_EXCEPTION');
                        }
                    },

                    'shipment.consignee:id,name,governorate_id,state_id,place_id,cellphone,alternatePhone,streetAddress,longitude,latitude',
                    'shipment.consignee.governorate:id,en_name,ar_name,lat,lng',
                    'shipment.consignee.state:id,en_name,ar_name,lat,lng',
                    'shipment.consignee.place:id,en_name,ar_name,lat,lng',
                    'shipment.deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,zipcode,streetAddress,longitude,latitude,location_url,label,approved,is_active',
                ])
                ->orderByDesc('id');


            if ($search) {
                $shipmentsQ->where(function ($qq) use ($search) {
                    $qq->where('shipment_tracking_no', 'like', "%{$search}%")
                        ->orWhereHas('shipment', function ($oq) use ($search) {
                            $oq->where('tracking_no', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%");
                        });
                });
            }

            if (filled($shipmentsPerPage)) {
                $shipmentsPaginator = $shipmentsQ->paginate(
                    (int) $shipmentsPerPage,
                    ['*'],
                    'shipments_page',
                    max($shipmentsPage, 1)
                )->appends($request->query());

                // ضمّن exception_type لكل عنصر
                $items = collect($shipmentsPaginator->items())->map(function ($item) use (&$ccCache) {
                    $exceptionType = null;
                    $addressPayload = null;

                    if ($item->relationLoaded('shipment') && $item->shipment) {
                        // Use total_cod directly since it's already stored correctly
                        try {
                            $totalCod = (float) ($item->shipment->total_cod ?? 0);
                            $item->shipment->setAttribute('total_cod', number_format($totalCod, 2, '.', ''));
                        } catch (\Throwable $e) {
                        }

                        $merchantId = $item->shipment->merchant_id ?? null;
                        $stateId = optional($item->shipment->consignee)->state_id ?? null;

                        if ($merchantId && $stateId) {
                            $cacheKey = "{$merchantId}:{$stateId}";
                            if (!array_key_exists($cacheKey, $ccCache)) {
                                $base = \DB::table('merchant_commissions')
                                    ->where('merchant_id', $merchantId)
                                    ->where('state_id', $stateId)
                                    ->value('base_delivery_fee');

                                $ccCache[$cacheKey] = $base !== null ? (float) $base : null;
                            }
                            if ($ccCache[$cacheKey] !== null) {
                                $item->shipment->setAttribute('delivery_fee', number_format($ccCache[$cacheKey], 3, '.', ''));
                            }
                        }

                        if (
                            optional($item->shipment)->status === 'DEFERRED' &&
                            $item->shipment->relationLoaded('shipment_delivery') &&
                            optional($item->shipment->shipment_delivery)->deliver_later_reason
                        ) {
                            $exceptionType = $item->shipment->shipment_delivery->deliver_later_reason;
                        }

                        if (!$exceptionType && optional($item->shipment)->status === 'DELIVERY_EXCEPTION') {
                            if ($item->shipment->relationLoaded('shipmentHistories') && $item->shipment->shipmentHistories && $item->shipment->shipmentHistories->count()) {
                                $h = $item->shipment->shipmentHistories->first();
                                $exceptionType =$shipment->exception_type?? $h->type ?? $h->reason ?? $h->code ?? null;
                            }
                        }

                        // هنا بقى نجهز العنوان اللي هنبعته في الـ JSON
                        if ($item->shipment->relationLoaded('deliveryAddress') && $item->shipment->deliveryAddress) {
                            $addr = $item->shipment->deliveryAddress;
                            $addressPayload = [
                                'id' => $addr->id,
                                'label' => $addr->label,
                                'street' => $addr->streetAddress,
                                'location_url' => $addr->location_url,
                                'longitude' => $addr->longitude,
                                'latitude' => $addr->latitude,
                                'city_id' => $addr->city_id,
                                'place_id' => $addr->place_id,
                                'state_id' => $addr->state_id,
                                'governorate_id' => $addr->governorate_id,
                            ];
                        }
                    }

                    $item->exception_type = $exceptionType;
                    $item->address = $addressPayload;

                    return $item;
                });



                $shipmentsPayload = [
                    'data' => $items,
                    'pagination' => [
                        'current_page' => $shipmentsPaginator->currentPage(),
                        'per_page' => $shipmentsPaginator->perPage(),
                        'total' => method_exists($shipmentsPaginator, 'total') ? $shipmentsPaginator->total() : null,
                        'last_page' => method_exists($shipmentsPaginator, 'lastPage') ? $shipmentsPaginator->lastPage() : null,
                        'has_more' => $shipmentsPaginator->hasMorePages(),
                    ],
                ];
            } else {
                $shipments = $shipmentsQ->get()->map(function ($item) use (&$ccCache) {
                    $exceptionType = null;
                    $addressPayload = null;

                    if ($item->relationLoaded('shipment') && $item->shipment) {
                        // نفس الكود اللي عندك هنا

                        if ($item->shipment->relationLoaded('deliveryAddress') && $item->shipment->deliveryAddress) {
                            $addr = $item->shipment->deliveryAddress;
                            $addressPayload = [
                                'id' => $addr->id,
                                'label' => $addr->label,
                                'street' => $addr->streetAddress,
                                'location_url' => $addr->location_url,
                                'longitude' => $addr->longitude,
                                'latitude' => $addr->latitude,
                                'city_id' => $addr->city_id,
                                'place_id' => $addr->place_id,
                                'state_id' => $addr->state_id,
                                'governorate_id' => $addr->governorate_id,
                            ];
                        }
                    }

                    $item->exception_type = $exceptionType;
                    $item->address = $addressPayload;

                    return $item;
                });

                $shipmentsPayload = [
                    'data' => $shipments,
                    'pagination' => null,
                ];
            }

            return $result + ['shipments' => $shipmentsPayload];
        })->values();

        // Process runsheets through DriverRunsheetDetailResource to remove geojson fields
        $processedRunsheets = $runsheetsWithShipments->map(function ($runsheet) {
            return (new DriverRunsheetDetailResource($runsheet))->toArray(request());
        });

        return sendResponse(
            $runsheetsPaginator->count() ? 'Runsheets retrieved successfully.' : 'No runsheet found.',
            [
                'runsheets' => $processedRunsheets,
                'pagination' => [
                    'current_page' => $runsheetsPaginator->currentPage(),
                    'per_page' => $runsheetsPaginator->perPage(),
                    'total' => method_exists($runsheetsPaginator, 'total') ? $runsheetsPaginator->total() : null,
                    'last_page' => method_exists($runsheetsPaginator, 'lastPage') ? $runsheetsPaginator->lastPage() : null,
                    'has_more' => $runsheetsPaginator->hasMorePages(),
                ],
            ]
        );
    }




    // public function driver_runsheets_new(Request $request)
    // {
    //     // Validate request parameters
    //     $validator = Validator::make($request->all(), [
    //         'date' => 'nullable|date_format:Y-m-d'
    //     ]);

    //     if ($validator->fails()) {
    //         return sendResponse('Invalid date format. Use YYYY-MM-DD.', [], false, [$validator->errors()], 422);
    //     }

    //     $driverId = Auth::id();
    //     $timezone = config('app.timezone');
    //     $dateInput = $request->input('date');

    //     // Determine target date with timezone awareness
    //     $targetDate = $dateInput
    //         ? Carbon::parse($dateInput, $timezone)->startOfDay()
    //         : Carbon::today($timezone);

    //     // Convert to UTC timestamps for database query
    //     $utcStart = $targetDate->copy()->utc();
    //     $utcEnd = $targetDate->copy()->endOfDay()->utc();

    //     // Fetch runsheet with optimized relations
    //     $runsheet = DriverRunsheet::query()
    //         ->where('driver_id', $driverId)
    //         ->whereBetween('created_at', [$utcStart, $utcEnd])
    //         ->select('id', 'status', 'confirmed_at', 'created_at')
    //         ->with([
    //             // Optimized eager loading with duplicate handling
    //             'assigned_shipments' => function ($query) {
    //                 $query->select('id', 'shipment_tracking_no', 'runsheet_id', 'status')
    //                     ->distinct('shipment_tracking_no'); // Database-level deduplication
    //             },
    //             // Nested relationships remain unchanged
    //             'assigned_shipments.shipment:id,consignee_id,tracking_no,value,amount,delivery_fee,payment_type,status,customer_name',
    //             'assigned_shipments.shipment.consignee:id,governorate_id,state_id,place_id,cellphone,alternatePhone,streetAddress,longitude,latitude',
    //             'assigned_shipments.shipment.consignee.governorate:id,en_name,ar_name,lat,lng',
    //             'assigned_shipments.shipment.consignee.state:id,en_name,ar_name,lat,lng',
    //             'assigned_shipments.shipment.consignee.place:id,en_name,ar_name,lat,lng'
    //         ])
    //         ->get();

    //     return sendResponse(
    //         $runsheet ? "Runsheet retrieved successfully" : "No runsheet found",
    //         $runsheet
    //     );
    // }



    // public function driver_runsheets(Request $request)
    // {
    //     // Validate request parameters
    //     $validator = Validator::make($request->all(), [
    //         'date' => 'nullable|date_format:Y-m-d'
    //     ]);

    //     if ($validator->fails()) {
    //         return sendResponse('Invalid date format. Use YYYY-MM-DD.', [], false, [$validator->errors()], 422);
    //     }

    //     $driverId = Auth::id();
    //     $timezone = config('app.timezone');
    //     $dateInput = $request->input('date');

    //     // Determine target date with timezone awareness
    //     $targetDate = $dateInput
    //         ? Carbon::parse($dateInput, $timezone)->startOfDay()
    //         : Carbon::today($timezone);

    //     // Convert to UTC timestamps for database query
    //     $utcStart = $targetDate->copy()->utc();
    //     $utcEnd = $targetDate->copy()->endOfDay()->utc();

    //     // Fetch runsheet with optimized relations
    //     $runsheet = DriverRunsheet::query()
    //         ->where('driver_id', $driverId)
    //         ->whereBetween('created_at', [$utcStart, $utcEnd])
    //         ->select('id', 'status', 'confirmed_at', 'created_at')
    //         ->with([
    //             'assigned_shipments:id,shipment_tracking_no,runsheet_id,status',
    //             'assigned_shipments.shipment' => function ($query) {
    //                 $query->select(
    //                     'id',
    //                     'consignee_id',
    //                     'tracking_no',
    //                     'customer_name',
    //                     'status',
    //                     'value'
    //                 );
    //             },
    //             'assigned_shipments.shipment.consignee' => function ($query) {
    //                 $query->select(
    //                     'id',
    //                     'governorate_id',
    //                     'state_id',
    //                     'place_id',
    //                     'cellphone',
    //                     'streetAddress',
    //                     'latitude',
    //                     'longitude'
    //                 );
    //             },
    //             'assigned_shipments.shipment.consignee.governorate:id,en_name,ar_name',
    //             'assigned_shipments.shipment.consignee.state:id,en_name,ar_name',
    //             'assigned_shipments.shipment.consignee.place:id,en_name,ar_name'
    //         ])
    //         ->first();

    //     // Handle duplicates at database level
    //     if ($runsheet) {
    //         $runsheet->load([
    //             'assigned_shipments' => function ($query) {
    //                 $query->distinct('shipment_tracking_no');
    //             }
    //         ]);
    //     }

    //     return sendResponse(
    //         $runsheet ? "Runsheet retrieved successfully" : "No runsheet found",
    //         $runsheet
    //     );
    // }

    public function driver_runsheets(Request $request)
    {
        $runsheets = DriverRunsheet::where('driver_id', Auth::id());

        if ($request->has('date') && $request->date) {
            try {
                $date = Carbon::parse($request->date)->startOfDay();
                $runsheets->whereDate('created_at', $date);
            } catch (Exception $e) {
                Log::warning('Invalid date format provided for driver runsheets filter', [
                    'driver_id' => Auth::id(),
                    'date' => $request->date,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $runsheets = $runsheets->select("id", "status", "confirmed_at", "created_at")
            ->with([
                // Eager load assigned_shipments, but filter out duplicates by grouping by shipment_tracking_no
                'assigned_shipments' => function ($query) {
                    $query->select('id', 'shipment_tracking_no', 'runsheet_id', 'status')
                        ->groupBy('shipment_tracking_no', 'id', 'runsheet_id', 'status');
                },
                'assigned_shipments.shipment:id,consignee_id,tracking_no,value,total_cod,delivery_fee,fee_payer,payment_type,status,customer_name',
                'assigned_shipments.shipment.consignee:id,governorate_id,state_id,place_id,cellphone,alternatePhone,streetAddress,longitude,latitude',
                'assigned_shipments.shipment.consignee.governorate:id,en_name,ar_name,lat,lng',
                'assigned_shipments.shipment.consignee.state:id,en_name,ar_name,lat,lng',
                'assigned_shipments.shipment.consignee.place:id,en_name,ar_name,lat,lng'
            ])
            ->paginate(10);

        // Remove duplicate assigned_shipments from the result set
        $runsheets->getCollection()->transform(function ($runsheet) {
            if ($runsheet->relationLoaded('assigned_shipments')) {
                $runsheet->setRelation(
                    'assigned_shipments',
                    $runsheet->assigned_shipments->unique('shipment_tracking_no')->values()
                );
            }
            return $runsheet;
        });


        return sendResponse("Driver Runsheets retrieved successfully.", new ShipmentResource($runsheets));
    }


    /**
     * Get Driver Invoices
     *
     * Retrieve driver invoices with shipment details and financial information.
     * Supports date and status filtering.
     *
     * @OA\Get(
     *     path="/driver/driver_invoices",
     *     summary="Get driver invoices",
     *     description="Retrieve driver invoices with comprehensive shipment and financial details",
     *     operationId="getDriverInvoices",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", format="date"),
     *         description="Filter invoices by date (YYYY-MM-DD)"
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "paid", "overdue"}),
     *         description="Filter invoices by status"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver invoices retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoices retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=123),
     *                         @OA\Property(property="invoice_no", type="string", example="INV-2024-001"),
     *                         @OA\Property(property="status", type="string", example="pending"),
     *                         @OA\Property(
     *                             property="invoice_shipments",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer"),
     *                                 @OA\Property(property="invoice_id", type="integer"),
     *                                 @OA\Property(property="shipment_tracking_no", type="string")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25)
     *             )
     *         )
     *     )
     * )
     */
    public function driver_invoices(Request $request)
    {
        $query = Invoice::where("invoiceable_id", Auth::id())->whereHas('invoiceable.driver');

        if (is_filled($request->date)) {
            $query->whereDate('created_at', Carbon::parse($request->date)->startOfMinute());
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $invoices = $query->select("id", "invoice_no", "status")->with([
            'invoice_shipments:id,invoice_id,shipment_tracking_no',
            'invoice_shipments.shipment:id,consignee_id,tracking_no,value,total_cod,delivery_fee,fee_payer,payment_type,status,customer_name',
            'invoice_shipments.shipment_finance:id,shipment_tracking_no,status,driver_delivery_fee,merchant_balance',
            'invoice_shipments.shipment.consignee:id,governorate_id,cellphone,alternatePhone,streetAddress',
        ])->orderBy('id', 'desc')->paginate(10);

        return sendResponse("Invoices retrieved successfully.", new InvoiceResource($invoices), []);
    }

    /**
     * Check Contact History
     *
     * Retrieve the contact attempt history for a specific shipment.
     * Shows how many times the driver has attempted to contact the customer.
     *
     * @OA\Post(
     *     path="/driver/shipments/check_contacts",
     *     summary="Check contact history for shipment",
     *     description="Retrieve contact attempt history for a specific shipment by tracking number",
     *     operationId="checkContactHistory",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/TrackingRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contact history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoices retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="contact_count", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid tracking number",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function check_contacts(Request $request)
    {
        $request->validate([
            "tracking_no" => "required|exists:shipments,tracking_no"
        ]);

        $shipment = Shipment::where('tracking_no', $request->tracking_no)->first();
        $contact_count = $shipment->shipmentHistories->where('name', 'CONTACT')->count();
        return sendResponse("Invoices retrieved successfully.", new ShipmentResource(['contact_count' => $contact_count]), []);
    }

    /**
     * Confirm Shipment Delivery with OTP
     *
     * Verify the one-time password for shipment delivery confirmation.
     * This ensures secure delivery verification between driver and customer.
     *
     * @OA\Post(
     *     path="/driver/shipments/confirm_shipment_otp",
     *     summary="Confirm shipment with OTP",
     *     description="Confirm shipment delivery using one-time password verification",
     *     operationId="confirmShipmentOTP",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_no", type="string", example="PE041225123456", description="Shipment tracking number"),
     *             @OA\Property(property="otp", type="string", example="123456", description="One-time password"),
     *             @OA\Property(property="driver_id", type="integer", example=15, description="Driver ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP confirmed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP confirmed. Proceed with delivery."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid OTP or shipment not assigned",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function confirm_shipment_otp(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
            'otp' => 'required|string',
            'driver_id' => 'required|exists:users,id',
        ]);
        try {
            $shipment = Shipment::where('tracking_no', $request->tracking_no)->firstOrFail();
            $assignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->where('driver_id', $request->driver_id)
                ->first();

            if (!$assignment) {
                return sendResponse(
                    "This shipment is not assigned to the selected driver.",
                    [],
                    false,
                    [],
                    422
                );
            }

            $delivery = $shipment->shipment_delivery;

            info("delivry: ", ['deli' => $delivery, 'dotp' => $delivery->delivery_otp, 'otp' => intval($request->otp)]);
            if (!$delivery || $delivery->delivery_otp !== intval($request->otp)) {
                return sendResponse("Invalid OTP provided.", [], false, [], 422);
            }
            $delivery->update(['otp_verified' => true]);

            $historyData = [
                "status" => "OTP_VERIFIED",
                "description" => "OTP verified for shipment {$shipment->tracking_no} by driver " . $assignment->driver->name,
                "shipment_id" => $shipment->id,
            ];

            shipmentHistory($historyData);

            return sendResponse("OTP confirmed. Proceed with delivery.", []);
        } catch (ModelNotFoundException $e) {
            return sendResponse("Shipment not found.", [], false, [], 422);
        } catch (Exception $e) {
            return sendResponse(
                "An error occurred while confirming OTP.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    /**
     * Confirm OTP via Direct Link
     *
     * Confirm shipment delivery OTP verification via a direct link.
     * This provides an alternative way to verify OTP without going through the main app flow.
     *
     * @OA\Get(
     *     path="/driver/shipments/confirm_otp/{tracking_no}/{otp}/{driver_id}",
     *     summary="Confirm OTP via link",
     *     description="Confirm shipment delivery OTP via direct link with URL parameters",
     *     operationId="confirmOTPLink",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", example="PE041225123456"),
     *         description="Shipment tracking number"
     *     ),
     *     @OA\Parameter(
     *         name="otp",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", example="123456"),
     *         description="One-time password"
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=15),
     *         description="Driver user ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP confirmed via link successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP confirmed. Proceed with delivery."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid parameters or shipment not assigned",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function confirm_otp_link($tracking_no, $otp, $driver_id)
    {
        info("here");
        try {
            $shipment = Shipment::where('tracking_no', $tracking_no)->firstOrFail();
            $assignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->where('driver_id', $driver_id)
                ->first();
            if (!$assignment) {
                return sendResponse(
                    "This shipment is not assigned to the selected driver.",
                    [],
                    false,
                    [],
                    422
                );
            }
            $delivery = $shipment->shipment_delivery;
            info("delivry: ", ['deli' => $delivery, 'dotp' => $delivery->delivery_otp, 'otp' => intval($otp)]);
            if (!$delivery || $delivery->delivery_otp !== intval($otp)) {
                return sendResponse("Invalid OTP provided.", [], false, [], 422);
            }
            $delivery->update(['otp_verified' => true]);
            return sendResponse("OTP confirmed. Proceed with delivery.", []);
        } catch (ModelNotFoundException $e) {
            return sendResponse("Shipment not found.", [], false, [], 422);
        } catch (Exception $e) {
            return sendResponse(
                "An error occurred while confirming OTP.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    public function check_waybill(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:merchant_waybills,tracking_no',
        ]);

        $sticer = MerchantWaybill::where('tracking_no', $request->tracking_no)->first();
        return sendResponse("Waybill found.", new MerchantWaybillResource($sticer));
    }


    /**
     * Regenerate Delivery OTP
     *
     * Generate a new one-time password for shipment delivery verification.
     * Limited to 2 regeneration attempts per shipment for security.
     *
     * @OA\Post(
     *     path="/driver/shipments/regenerate_otp",
     *     summary="Regenerate delivery OTP",
     *     description="Generate new one-time password for shipment delivery with attempt limits",
     *     operationId="regenerateOTP",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_no", type="string", example="PE041225123456", description="Shipment tracking number"),
     *             @OA\Property(property="driver_id", type="integer", example=15, description="Driver ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP regenerated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="New OTP sent successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="OTP limit reached or invalid request",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function regenerate_otp(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
            'driver_id' => 'required|exists:users,id',
        ]);
        try {
            $shipment = Shipment::where('tracking_no', $request->tracking_no)->firstOrFail();
            $assignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->where('driver_id', $request->driver_id)
                ->first();
            if (!$assignment) {
                return sendResponse("This shipment is not assigned to the selected driver.", [], false, [], 422);
            }
            $delivery = $shipment->shipment_delivery;
            if ($delivery->otp_attempts >= 2) {
                return sendResponse("OTP resend limit reached.", [], false, [], 422);
            }
            $delivery->increment('otp_attempts');
            $otp = generate_otp();
            $delivery->update([
                'delivery_otp' => $otp,
                'otp_generated_at' => now()
            ]);
            $shipment->consignee->notify(new ConsigneeOFDNotification($shipment));
            return sendResponse("New OTP sent successfully.", []);
        } catch (ModelNotFoundException $e) {
            return sendResponse("Shipment not found.", [], false, [], 422);
        } catch (Exception $e) {
            return sendResponse("An error occurred while regenerating OTP.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Create Shipment from Driver Application
     *
     * Create a new shipment from the driver application. If merchant_id is provided, the shipment is associated with that merchant.
     * If merchant_id is null, a new guest merchant is created similar to walk-in logic.
     *
     * @OA\Post(
     *     path="/driver/shipments/create",
     *     summary="Create shipment from driver app",
     *     description="Create a new shipment with optional merchant_id. If null, creates a guest merchant.",
     *     operationId="createShipmentFromDriver",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="merchant_id", type="integer", nullable=true, example=null, description="Merchant ID (optional)"),
     *             @OA\Property(property="merchant_name", type="string", example="John Doe", description="Merchant name (required if merchant_id null)"),
     *             @OA\Property(property="merchant_phone", type="string", example="+966123456789", description="Merchant phone (required if merchant_id null)"),
     *             @OA\Property(property="sender_country_id", type="integer", example=1, description="Sender country ID (required if merchant_id null)"),
     *             @OA\Property(property="sender_governorate_id", type="integer", example=2, description="Sender governorate ID"),
     *             @OA\Property(property="sender_state_id", type="integer", example=3, description="Sender state ID"),
     *             @OA\Property(property="sender_place_id", type="integer", example=4, description="Sender place ID"),
     *             @OA\Property(property="sender_city_id", type="integer", example=5, description="Sender city ID"),
     *             @OA\Property(property="sender_streetAddress", type="string", example="123 Main St", description="Sender street address"),
     *             @OA\Property(property="sender_latitude", type="number", format="float", example=24.7136, description="Sender latitude"),
     *             @OA\Property(property="sender_longitude", type="number", format="float", example=46.6753, description="Sender longitude"),
     *             @OA\Property(property="sender_email", type="string", format="email", example="john@example.com", description="Sender email (optional)"),
     *             @OA\Property(property="name", type="string", example="Recipient Name", description="Consignee name"),
     *             @OA\Property(property="email", type="string", format="email", example="recipient@example.com", description="Consignee email"),
     *             @OA\Property(property="cellphone", type="string", example="+966987654321", description="Consignee cellphone"),
     *             @OA\Property(property="alternatePhone", type="string", example="+966555555555", description="Consignee alternate phone"),
     *             @OA\Property(property="district", type="string", example="District Name", description="Consignee district"),
     *             @OA\Property(property="country_id", type="integer", example=1, description="Consignee country ID"),
     *             @OA\Property(property="governorate_id", type="integer", example=2, description="Consignee governorate ID"),
     *             @OA\Property(property="state_id", type="integer", example=3, description="Consignee state ID"),
     *             @OA\Property(property="place_id", type="integer", example=4, description="Consignee place ID"),
     *             @OA\Property(property="city_id", type="integer", example=5, description="Consignee city ID"),
     *             @OA\Property(property="zipcode", type="string", example="12345", description="Consignee zipcode"),
     *             @OA\Property(property="streetAddress", type="string", example="456 Recipient St", description="Consignee street address"),
     *             @OA\Property(property="identify", type="string", example="ID123", description="Consignee identify"),
     *             @OA\Property(property="taxNumber", type="string", example="TAX456", description="Consignee tax number"),
     *             @OA\Property(property="longitude", type="number", format="float", example=46.6753, description="Consignee longitude"),
     *             @OA\Property(property="latitude", type="number", format="float", example=24.7136, description="Consignee latitude"),
     *             @OA\Property(property="shipper_id", type="integer", example=1, description="Shipper ID"),
     *             @OA\Property(property="notes", type="string", example="Handle with care", description="Shipment notes"),
     *             @OA\Property(property="payment_type", type="string", example="COD", description="Payment type"),
     *             @OA\Property(property="value", type="number", format="float", example=100.50, description="Shipment value"),
     *             @OA\Property(property="delivery_fee", type="number", format="float", example=10.00, description="Delivery fee"),
     *             @OA\Property(property="fee_payer", type="string", example="merchant", description="Fee payer"),
     *             @OA\Property(property="is_outsourced", type="boolean", example=false, description="Is outsourced"),
     *             @OA\Property(property="allow_return", type="boolean", example=true, description="Allow return"),
     *             @OA\Property(property="delivery_priority", type="string", example="high", description="Delivery priority"),
     *             @OA\Property(property="delivery_time", type="string", format="date-time", example="2024-10-17T14:00:00", description="Delivery time"),
     *             @OA\Property(property="sender_district", type="string", example="Sender District", description="Sender district"),
     *             @OA\Property(property="sender_location_url", type="string", example="https://maps.example.com/sender", description="Sender location URL"),
     *             @OA\Property(property="sender_notes", type="string", example="Sender notes", description="Sender notes"),
     *             @OA\Property(property="sender_streetAddress", type="string", example="Sender Street", description="Sender street address"),
     *             @OA\Property(property="sender_zipcode", type="string", example="54321", description="Sender zipcode"),
     *             @OA\Property(property="need_invoice", type="boolean", example=true, description="Need invoice"),
     *             @OA\Property(property="unit_id", type="integer", example=1, description="Unit ID"),
     *             @OA\Property(property="zone_id", type="integer", example=2, description="Zone ID"),
     *             @OA\Property(property="package_id", type="integer", example=3, description="Package ID"),
     *             @OA\Property(property="weight", type="number", format="float", example=5.5, description="Weight"),
     *             @OA\Property(property="height", type="number", format="float", example=10.0, description="Height"),
     *             @OA\Property(property="width", type="number", format="float", example=15.0, description="Width"),
     *             @OA\Property(property="length", type="number", format="float", example=20.0, description="Length"),
     *             @OA\Property(property="status", type="integer", example=0, description="Status"),
     *             @OA\Property(property="item_name", type="array", @OA\Items(type="string"), description="Item names"),
     *             @OA\Property(property="quantity", type="array", @OA\Items(type="integer"), description="Quantities"),
     *             @OA\Property(property="category", type="array", @OA\Items(type="string"), description="Categories")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Shipment")
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

    /**

     * @OA\Post(
     *     path="/api/v1/driver/shipments/create",
     *     summary="Create new shipment by driver",
     *     description="Driver creates a new shipment from the mobile app.",
     *     tags={"Driver Shipments"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"consignee_name","consignee_phone","address","cod_amount"},
     *             @OA\Property(property="consignee_name", type="string", example="Ahmed Ali"),
     *             @OA\Property(property="consignee_phone", type="string", example="01001234567"),
     *             @OA\Property(property="address", type="string", example="Alexandria, Smouha, Street ١٠"),
     *             @OA\Property(property="cod_amount", type="number", format="float", example=250.5),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Deliver after 5 PM")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shipment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="tracking_no", type="string", example="PE301125491431"),
     *                 @OA\Property(property="status", type="string", example="CREATED")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function createShipment(StoreShipmentRequest $request)
    {
        if (!$request->filled('tracking_no') && !$request->filled('pre_id')) {
            $request->merge(['pre_id' => generate_pre_id()]);
        }

        if (!$request->filled('pickup_task_id')) {
            return sendResponse(
                "Driver cannot create shipment without Pickup Task",
                [],
                false,
                ["Driver cannot create shipment without Pickup Task"],
                422
            );
        }

        $request->validate([
            'pickup_task_id' => 'required|exists:merchant_pickup_tasks,id'
        ]);
        $request->validated();
        DB::beginTransaction();

        $originalUser = Auth::user();
        $sendWhatsappSetting = Setting::where('key', 'send_whatsapp_after_create_shipment')->value('value');
        $shouldSendWhatsapp = $sendWhatsappSetting === 'yes';

        try {
            $passedTracking = trim((string) $request->input('tracking_no', ''));
            $passedPreId = trim((string) $request->input('pre_id', ''));

            $consigneeData = $request->only([
                "name",
                "email",
                "cellphone",
                "alternatePhone",
                "district",
                "country_id",
                "governorate_id",
                "state_id",
                "place_id",
                "city_id",
                "zipcode",
                "streetAddress",
                "identify",
                "taxNumber",
                "longitude",
                "latitude",
                "location_url",
            ]);

            $cellphoneSplit = splitPhoneNumber($consigneeData['cellphone']);
            $consigneeData['country_key_cellphone'] = $cellphoneSplit['country_code'];
            $consigneeData['cellphone'] = $cellphoneSplit['national_number'];

            $alt = $request->input('alternatePhone');
            if ($alt) {
                $alternatePhoneSplit = splitPhoneNumber($alt);
                $consigneeData['country_key_alternatePhone'] = $alternatePhoneSplit['country_code'];
                $consigneeData['alternatePhone'] = $alternatePhoneSplit['national_number'];
            } else {
                $consigneeData['country_key_alternatePhone'] = null;
                $consigneeData['alternatePhone'] = null;
            }

            $existingConsignee = Consignee::withoutGlobalScope(ConsigneeScope::class)
                ->where('cellphone', $cellphoneSplit['national_number'])
                ->where('country_key_cellphone', $cellphoneSplit['country_code'])
                ->when($consigneeData['country_id'] ?? null, fn($q, $cid) => $q->where('country_id', $cid))
                ->first();

            if ($existingConsignee) {
                if (collect($consigneeData)->diffAssoc($existingConsignee->only(array_keys($consigneeData)))->isNotEmpty()) {
                    $existingConsignee->update($consigneeData);
                }
                $consignee = $existingConsignee;
            } else {
                $consignee = Consignee::create($consigneeData);
            }

            $shipmentData = $request->only([
                "shipper_id",
                "notes",
                "payment_type",
                "value",
                "delivery_fee",
                "merchant_id",
                "fee_payer",
                "is_outsourced",
                "allow_return",
                "delivery_priority",
                "delivery_time",
                "sender_district",
                "sender_location_url",
                "sender_notes",
                "sender_streetAddress",
                "sender_zipcode",
                // "sender_country_id",
                // "sender_governorate_id",
                // "sender_state_id",
                // "sender_place_id",
                // "sender_city_id",
                // "sender_latitude",
                // "sender_longitude",
                "need_invoice"
            ]);

            $shipmentData['tracking_no'] = null;
            $shipmentData['pre_id'] = null;

            $actingUserId = Auth::id();
            $passedMerchantId = $request->input('merchant_id');

            // Normalize merchant_id: convert string "null" or empty string to null
            if ($passedMerchantId === 'null' || $passedMerchantId === '') {
                $passedMerchantId = null;
            }

            // CRITICAL: If pickup_task_id is provided but merchant_id is not,
            // derive merchant_id from the pickup task (Cases 6 and 8)
            if ($passedMerchantId === null && $request->filled('pickup_task_id')) {
                $pickupTask = MerchantPickupTask::find($request->pickup_task_id);
                if ($pickupTask && $pickupTask->merchant_id) {
                    $passedMerchantId = $pickupTask->merchant_id;
                }
            }

            $guestMerchantUserId = null;
            // Only create guest merchant if we still don't have a merchant_id
            // (not from request AND not from pickup_task)
            if ($passedMerchantId === null) {
                $shipmentData['is_walkin'] = 1;
                $shipmentData['customer_name'] = $request->merchant_name;
                $shipmentData['customer_phone'] = $request->merchant_phone;

                $phoneSplit = splitPhoneNumber($request->merchant_phone);

                $merchantData = [
                    'country_id' => $request->sender_country_id ?? null,
                    'governorate_id' => $request->sender_governorate_id ?? null,
                    'state_id' => $request->sender_state_id ?? null,
                    'place_id' => $request->sender_place_id ?? null,
                    'city_id' => $request->sender_city_id ?? null,
                    'address' => $request->sender_streetAddress ?? null,
                    'country_code' => $phoneSplit['country_code'],
                    'contact_no' => $phoneSplit['national_number'],
                    'lat' => $request->sender_latitude ?? null,
                    'lng' => $request->sender_longitude ?? null,
                    'is_guest' => true,
                    'owner_id' => facility("id"),
                    'owner_type' => facility("type"),
                ];

                $existingMerchant = Merchant::withoutGlobalScope('scopeByOwner')
                    ->where('contact_no', $phoneSplit['national_number'])
                    ->where('country_code', $phoneSplit['country_code'])
                    ->when($merchantData['country_id'], fn($q, $cid) => $q->where('country_id', $cid))
                    ->first();

                if ($existingMerchant) {
                    if (collect($merchantData)->diffAssoc($existingMerchant->only(array_keys($merchantData)))->isNotEmpty()) {
                        $existingMerchant->update($merchantData);
                    }
                    $guestMerchant = $existingMerchant;
                    $guestMerchantUserId = $existingMerchant->user_id;
                } else {
                    $userData = [
                        'name' => $request->merchant_name,
                        'email' => $request->sender_email ?? 'guest_' . time() . '@example.com',
                        'phone' => $phoneSplit['national_number'],
                        'country_code' => $phoneSplit['country_code'],
                        'password' => bcrypt('guest_password'),
                        'owner_id' => facility("id"),
                        'owner_type' => facility("type"),
                    ];
                    $user = User::create($userData);
                    $user->assignRole("Merchant");

                    $merchantData['user_id'] = $user->id;
                    $guestMerchant = Merchant::create($merchantData);
                    $guestMerchantUserId = $user->id;

                    $states = State::select('id')->get();
                    $defaultMerchantCommission = optional(Setting::where('key', 'default_merchant_commission')->first())->value ?? 1;
                    foreach ($states as $state) {
                        MerchantCommission::create([
                            'merchant_id' => $user->id,
                            'country_id' => $merchantData['country_id'] ?? null,
                            'state_id' => $state->id,
                            'delivery_fee' => $defaultMerchantCommission,
                            'return_fee' => 1.00,
                        ]);
                    }

                    Wallet::create(['user_id' => $user->id, 'balance' => 0]);
                }

                $shipmentData['merchant_id'] = $guestMerchantUserId;
                $shipmentData['created_by'] = $shipmentData['merchant_id'];
            } else {
                // Verify the merchant user exists before proceeding
                $merchantUser = \App\Models\User::find($passedMerchantId);
                if (!$merchantUser) {
                    DB::rollBack();
                    return sendResponse("Invalid merchant ID. The specified merchant does not exist.", [], false, [
                        "The merchant_id ({$passedMerchantId}) does not exist in the users table."
                    ], 422);
                }

                $shipmentData['merchant_id'] = $passedMerchantId;
                $shipmentData['created_by'] = $passedMerchantId;
            }

            if (!$request->shipper_id) {
                $shipmentData['shipper_id'] = Shipper::pe()->id;
            }
            $shipmentData['owner_id'] = Auth::user()->owner_id;
            $shipmentData['owner_type'] = Auth::user()->owner_type;
            $shipmentData['facility_id'] = Auth::user()->facility_id;
            $shipmentData['facility_type'] = Auth::user()->facility_type;
            // ================= Handle tracking_no from Driver or Merchant =================
            $waybillSource = null;   // 'driver' | 'merchant' | null
            $dWaybill = null;
            $mWaybill = null;

            if ($passedTracking !== '') {
                // طبّع التتبع (لو في مسافات/حروف كبيرة وصغيرة)
                $normalizedTracking = strtoupper(preg_replace('/\s+/', '', $passedTracking));

                // 1) جرّب DriverWaybill لنفس السواق
                $dWaybill = DriverWaybill::withoutGlobalScopes() // لو عليه سكوبات
                    ->where('driver_id', Auth::id())
                    ->whereRaw('REPLACE(UPPER(tracking_no)," ","") = ?', [$normalizedTracking])
                    ->lockForUpdate()
                    ->first();

                if ($dWaybill) {
                    if ($dWaybill->used) {
                        DB::rollBack();
                        return sendResponse("Waybill already used.", [], false, [
                            "Driver waybill already used previously"
                        ], 422);
                    }
                    $shipmentData['tracking_no'] = $dWaybill->tracking_no;
                    $shipmentData['pre_id'] = null;
                    $waybillSource = 'driver';
                    $request->merge(['_waybill_type' => 'driver']);
                } else {
                    $merchantUserId = (int) $shipmentData['merchant_id'];

                    $merchantRowFromUser = Merchant::withoutGlobalScopes()
                        ->where('user_id', $merchantUserId)->value('id');

                    // ولو هو أصلًا merchants.id (بعض الشاشات أو APIs بتبعت كده)
                    $merchantRowSelf = Merchant::withoutGlobalScopes()
                        ->where('id', $merchantUserId)->value('id');
                    $userFromRow = Merchant::withoutGlobalScopes()
                        ->where('id', $merchantUserId)->value('user_id');

                    // اجمع كل الاحتمالات وشيل الدوبلكيتس
                    $possibleMerchantIds = collect([
                        $merchantUserId,        // users.id لو الشحنة ماسكة user_id
                        $merchantRowFromUser,   // merchants.id المناظر لـ user_id
                        $merchantRowSelf,       // merchants.id لو الممرّر كان row id
                        $userFromRow,           // users.id المناظر لـ merchants.id
                    ])->filter(fn($v) => !is_null($v))->unique()->values()->all();

                    // سيبك من أي Global Scope ممكن يخفي السطر
                    $mWaybill = MerchantWaybill::withoutGlobalScopes()
                        ->whereRaw('REPLACE(UPPER(tracking_no)," ","") = ?', [$normalizedTracking])
                        ->where(function ($q) use ($possibleMerchantIds) {
                            if (!empty($possibleMerchantIds)) {
                                $q->whereIn('merchant_id', $possibleMerchantIds)
                                    ->orWhereNull('merchant_id'); // pool عام
                            } else {
                                $q->whereNull('merchant_id');   // fallback
                            }
                        })
                        ->lockForUpdate()
                        ->first();

                    if ($mWaybill) {
                        if ($mWaybill->used) {
                            DB::rollBack();
                            return sendResponse("Waybill already used.", [], false, [
                                "Merchant waybill already used previously"
                            ], 422);
                        }

                        // لو عامة اربطها بالتاجر الحالي — اختار نوع الربط حسب اللي متبع في جدولك
                        if (is_null($mWaybill->merchant_id)) {
                            // إن كان جدولك بيخزن merchants.id خليها merchants.id، غير كده users.id
                            // هنا بنفضّل merchants.id إن وُجد وإلا users.id
                            $mWaybill->merchant_id = $merchantRowFromUser ?? $merchantRowSelf ?? $merchantUserId;
                            $mWaybill->save();
                        }

                        // CRITICAL: Update shipmentData with the actual merchant from the waybill
                        // This ensures discount lookup uses the correct merchant (not guest)
                        if ($mWaybill->merchant_id) {
                            $shipmentData['merchant_id'] = $mWaybill->merchant_id;
                            $shipmentData['created_by'] = $mWaybill->merchant_id;
                            $shipmentData['is_walkin'] = 0; // Not a walk-in anymore
                        }

                        $shipmentData['tracking_no'] = $mWaybill->tracking_no;
                        $shipmentData['pre_id'] = null;
                        $waybillSource = 'merchant';
                        $request->merge(['_waybill_type' => 'merchant']);
                    } else {
                        DB::rollBack();
                        return sendResponse("Waybill not found for this driver or merchant.", [], false, [
                            "Invalid tracking_no for the authenticated driver or merchant"
                        ], 422);
                    }
                }
            } else {
                // مفيش tracking → نشتغل بـ pre_id
                $shipmentData['tracking_no'] = null;
                $shipmentData['pre_id'] = $passedPreId !== '' ? $passedPreId : generate_pre_id();
            }



            $shipperCommissionFee = null;
            $merchantCommissionFee = null;
            $deliveryDiscount = 0.0;
            $returnFeeBase = 0.0;
            $returnDiscount = 0.0;
            $merchant_commission = null;

            if ($request->filled("shipper_id")) {
                $shipper_commission = ShipperCommission::where('shipper_id', $request->shipper_id)
                    ->where('state_id', $consigneeData['state_id'])
                    ->first();
                $shipperCommissionFee = optional($shipper_commission)->delivery_fee;
            }

            if (!empty($shipmentData['merchant_id'])) {
                $merchant_commission = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
                    ->where('state_id', $consigneeData['state_id'])
                    ->first();
                $merchantCommissionFee = optional($merchant_commission)->base_delivery_fee;
                // Read discount fields from merchant_commissions (always read, not conditionally)
                $deliveryDiscount = (float) optional($merchant_commission)->delivery_discount_amount ?? 0.0;
                $returnFeeBase = (float) optional($merchant_commission)->base_return_fee ?? 0.0;
                $returnDiscount = (float) optional($merchant_commission)->return_discount_amount ?? 0.0;
            }

            // Calculate base delivery fee (before discount)
            $baseDeliveryFee = null;
            if ($shipperCommissionFee !== null && $merchantCommissionFee !== null) {
                $baseDeliveryFee = max($shipperCommissionFee, $merchantCommissionFee);
            } elseif ($merchantCommissionFee !== null) {
                $baseDeliveryFee = $merchantCommissionFee;
            } elseif ($shipperCommissionFee !== null) {
                $baseDeliveryFee = $shipperCommissionFee;
            } else {
                // Fallback: use request value if valid, otherwise 0 (removed hardcoded 2)
                $baseDeliveryFee = (isset($shipmentData['delivery_fee']) && is_numeric($shipmentData['delivery_fee']) && $shipmentData['delivery_fee'] > 0)
                    ? (float) $shipmentData['delivery_fee'] : 0.0;
            }

            // Set discount fields (ALWAYS, not conditionally)
            // Always set delivery_fee_before_discount in shipmentData for getTotalCOD() calculation
            $shipmentData['delivery_fee_before_discount'] = !is_null($merchantCommissionFee)
                ? (float) $merchantCommissionFee
                : (float) ($baseDeliveryFee ?? 0.0);

            if (Schema::hasColumn('shipments', 'delivery_fee_before_discount')) {
                // Also set in shipmentData for database storage
            }
            if (Schema::hasColumn('shipments', 'delivery_fee_discount')) {
                $shipmentData['delivery_fee_discount'] = (float) $deliveryDiscount;
            }

            // Apply discount to delivery_fee (net fee after discount)
            $shipmentData['delivery_fee'] = max(0, ($baseDeliveryFee ?? 0.0) - $deliveryDiscount);

            // Handle return fee discount (only if fee_payer is 'merchant')
            if (isset($merchant_commission) && strtolower($shipmentData['fee_payer'] ?? '') === 'merchant') {
                if ($returnFeeBase > 0) {
                    $shipmentData['return_fee_before_discount'] = $returnFeeBase;
                    if ($returnDiscount > 0) {
                        $shipmentData['return_fee_discount'] = $returnDiscount;
                        $shipmentData['return_fee'] = max(0, $returnFeeBase - $returnDiscount);
                    } else {
                        $shipmentData['return_fee'] = $returnFeeBase;
                    }
                }
            }

            $shipmentData['consignee_id'] = $consignee->id;

            // // Set default fee_payer if not provided (default is 'customer')
            // if (!isset($shipmentData['fee_payer']) || $shipmentData['fee_payer'] === null) {
            //     $shipmentData['fee_payer'] = 'customer';
            // }

            // Use centralized calculation service for total_cod
            $calculationService = app(\App\Services\CalculationLogicService::class);
            $shipmentObj = (object) $shipmentData; // Convert to object for calculation
            $shipmentData['total_cod'] = $calculationService->getTotalCOD($shipmentObj);

            if (empty($shipmentData['tracking_no']) && $passedTracking !== '') {
                $driverId = Auth::id();
                $waybillFound = false;
                $waybillType = null; // 'driver' or 'merchant'

                // Normalize tracking_no for waybill lookup
                $trackingNo = $passedTracking;
                $prefix = substr($trackingNo, 0, 2);

                // Generate possible tracking_no formats to try
                $possibleTrackingNos = [$trackingNo];
                if ($prefix === 'PE' && strlen($trackingNo) > 2) {
                    $withoutPE = substr($trackingNo, 2);
                    $withoutPEPrefix = substr($withoutPE, 0, 2);
                    // If after removing PE it already has ME or DR prefix, use it directly
                    if ($withoutPEPrefix === 'ME' || $withoutPEPrefix === 'DR') {
                        $possibleTrackingNos[] = $withoutPE;
                    } elseif (strlen($withoutPE) >= 12) {
                        // If no prefix after PE, try with DR prefix for driver waybill
                        $possibleTrackingNos[] = 'DR' . $withoutPE;
                        // Also try with ME prefix for merchant waybill
                        $possibleTrackingNos[] = 'ME' . $withoutPE;
                    }
                } elseif ($prefix !== 'ME' && $prefix !== 'DR' && $prefix !== 'PE') {
                    // If no prefix, try with DR prefix for driver waybill
                    $possibleTrackingNos[] = 'DR' . $trackingNo;
                    // Also try with ME prefix for merchant waybill
                    $possibleTrackingNos[] = 'ME' . $trackingNo;
                }

                // First, check if it's a driver waybill - try all possible formats
                $dWaybill = null;
                foreach ($possibleTrackingNos as $possibleTrackingNo) {
                    $dWaybill = DriverWaybill::where('driver_id', $driverId)
                        ->where('tracking_no', $possibleTrackingNo)
                        ->lockForUpdate()
                        ->first();
                    if ($dWaybill) {
                        $passedTracking = $possibleTrackingNo; // Update to correct format
                        break;
                    }
                }

                if ($dWaybill) {
                    if ($dWaybill->used) {
                        DB::rollBack();
                        return sendResponse("Waybill already used.", [], false, [
                            "Waybill already used previously"
                        ], 422);
                    }
                    $waybillFound = true;
                    $waybillType = 'driver';
                } else {
                    // If not a driver waybill, check if it's a merchant waybill
                    // Only allow merchant waybill if merchant_id is provided and matches
                    if (!empty($shipmentData['merchant_id'])) {
                        $mWaybill = null;
                        // Try all possible formats for merchant waybill
                        foreach ($possibleTrackingNos as $possibleTrackingNo) {
                            $mWaybill = MerchantWaybill::where('merchant_id', $shipmentData['merchant_id'])
                                ->where('tracking_no', $possibleTrackingNo)
                                ->lockForUpdate()
                                ->first();
                            if ($mWaybill) {
                                $passedTracking = $possibleTrackingNo; // Update to correct format
                                break;
                            }
                        }

                        if ($mWaybill) {
                            if ($mWaybill->used) {
                                DB::rollBack();
                                return sendResponse("Waybill already used.", [], false, [
                                    "Waybill already used previously"
                                ], 422);
                            }
                            $waybillFound = true;
                            $waybillType = 'merchant';
                        }
                    }
                }

                if (!$waybillFound) {
                    DB::rollBack();
                    return sendResponse("Waybill not found for this driver.", [], false, [
                        "Invalid tracking_no for the authenticated driver or merchant"
                    ], 422);
                }

                $shipmentData['tracking_no'] = $passedTracking;
                $shipmentData['pre_id'] = null; // مش محتاجينه في الحالة دي

                // Store waybill type for later use when marking as used
                $request->merge(['_waybill_type' => $waybillType]);
            } elseif (empty($shipmentData['tracking_no'])) {
                // مفيش tracking → نشتغل بـ pre_id
                $shipmentData['tracking_no'] = null;
                $shipmentData['pre_id'] = $passedPreId !== '' ? $passedPreId : generate_pre_id();
            }

            // ====== إنشاء الشحنة ======
            $shipmentData['status'] = ShipmentStatusEnum::PICKED;
            $shipmentData['created_source'] = 'driver';
            $shipmentData['driver_id'] = Auth::id();

            $shipmentData['driver_id'] = Auth::id();

            $shipment = Shipment::create($shipmentData);
            $shipment->load('consignee');

            // ====== عنوان التسليم ======
            $addressData = [
                'consignee_id' => $consignee->id,
                'country_id' => $consigneeData['country_id'] ?? null,
                'governorate_id' => $consigneeData['governorate_id'] ?? null,
                'state_id' => $consigneeData['state_id'] ?? null,
                'place_id' => $consigneeData['place_id'] ?? null,
                'city_id' => $consigneeData['city_id'] ?? null,
                'zipcode' => $consigneeData['zipcode'] ?? null,
                'streetAddress' => $consigneeData['streetAddress'] ?? null,
                'longitude' => $consigneeData['longitude'] ?? null,
                'latitude' => $consigneeData['latitude'] ?? null,
                'location_url' => $request->input('location_url'),
                'label' => null,
                'approved' => false,
                'is_active' => true,
            ];

            if ($request->filled('delivery_address_id')) {
                $addr = Address::where('id', (int) $request->delivery_address_id)
                    ->where('consignee_id', $consignee->id)
                    ->first();
                if (!$addr) {
                    throw new \Exception("Delivery address not found for this consignee.");
                }
            } else {
                $addr = Address::create($addressData);
            }

            $shipment->update(['delivery_address_id' => $addr->id]);
            $shipment->load(['deliveryAddress.state', 'deliveryAddress.governorate', 'deliveryAddress.place']);

            // ====== Set hub information ======
            $hubService = app(\App\Services\HubInformationService::class);
            $hubService->setFinalHub($shipment);
            $hubService->setInitialCurrentHub($shipment);
            $shipment->save();

            $zone = $shipment->zone(); // Still needed for ShipmentInformation below

            if ((int) ($shipment->is_outsourced ?? 0) !== 1 && $shouldSendWhatsapp) {
                $link = app(AddressUpdateLinkService::class)->generate($shipment);
                $shipment->consignee->notify(new ShipmentCreatedNotification(
                    $shipment,
                    $link['url'],
                    $link['otp']
                ));
            }

            $merchantAccount = Account::firstOrCreate(
                ['accountable_type' => User::class, 'accountable_id' => $shipment->merchant_id],
                ['parcel_value' => 0, 'balance' => 0]
            );
            // Use CalculationLogicService to get merchant COD (goods value only for COD shipments)
            // Reuse the calculation service instance that was created earlier
            $merchantCOD = $calculationService->getMerchantCOD($shipment);
            $merchantAccount->parcel_value += $merchantCOD;
            $merchantAccount->save();

            // علّم DriverWaybill أو MerchantWaybill مستخدم لو اتستخدم
            if ($shipment->tracking_no) {
                $waybillType = $request->input('_waybill_type');

                if ($waybillType === 'driver') {
                    $dwb = DriverWaybill::where("tracking_no", $shipment->tracking_no)->first();
                    if ($dwb) {
                        $dwb->used = 1;
                        $dwb->save();
                    }
                } elseif ($waybillType === 'merchant') {
                    $mwb = MerchantWaybill::where("tracking_no", $shipment->tracking_no)->first();
                    if ($mwb) {
                        $mwb->used = 1;
                        $mwb->save();
                    }
                } else {
                    // Fallback: try both if type wasn't stored (backward compatibility)
                    $dwb = DriverWaybill::where("tracking_no", $shipment->tracking_no)->first();
                    if ($dwb) {
                        $dwb->used = 1;
                        $dwb->save();
                    } else {
                        $mwb = MerchantWaybill::where("tracking_no", $shipment->tracking_no)->first();
                        if ($mwb) {
                            $mwb->used = 1;
                            $mwb->save();
                        }
                    }
                }
            }

            Transaction::create([
                "to_id" => $shipment->merchant_id,
                "to_type" => User::class,
                "shipment_id" => $shipment->id,
                "amount" => $shipment->total_cod,
                "type" => "merchant_created",
            ]);

            ShipmentInformation::create([
                'shipment_id' => $shipment->id,
                'merchant_id' => $shipment->merchant_id,
                'unit_id' => $request->unit_id,
                'zone_id' => $zone->id ?? $request->zone_id,
                'package_id' => $request->package_id,
                'tracking_no' => $shipment->tracking_no, // ممكن يكون null
                'pre_id' => $shipment->pre_id,      // بديل في حالة عدم وجود tracking
                'in_warehouse' => true,
                'weight' => $request->weight,
                'height' => $request->height,
                'width' => $request->width,
                'length' => $request->length,
                'status' => $request->status ?? 0,
            ]);

            ShipmentDelivery::create(['shipment_id' => $shipment->id]);

            // if ($shipment->tracking_no) {
            //     ShipmentFinance::create(['shipment_tracking_no' => $shipment->tracking_no]);
            // } else {
            //     ShipmentFinance::create(['shipment_pre_id' => $shipment->pre_id]);
            // }
            ShipmentFinance::create([
                'shipment_tracking_no' => $shipment->tracking_no,
                'shipment_pre_id' => $shipment->pre_id,
            ]);

            // ====== Items ======
            if ($request->has('item_name') && is_array($request->item_name)) {
                foreach ($request->item_name as $id => $itemName) {
                    $quantity = $request->input("quantity.$id");
                    $category = $request->input("category.$id");
                    if ($itemName && $quantity && $category) {
                        ShipmentItem::create([
                            'shipment_id' => $shipment->id,
                            'name' => $itemName,
                            'quantity' => $quantity,
                            'category' => $category,
                        ]);
                    }
                }
            }

            // ====== Pickup Task (لو عندك تدفق مختلف للسواق سيبه أو عدّله لاحقاً) ======
            $task = MerchantPickupTask::find($request->pickup_task_id);
            if (!$task) {
                DB::rollBack();
                return sendResponse("Pickup task not found.", [], false, ["Invalid pickup_task_id"], 422);
            }

            // Add Extra shipment to the Merchant Pickup Task
            $this->updatePickupExtraShipmentsCount($task);

            $merchantPickupShipment = MerchantPickupShipment::create([
                'pickup_task_id' => $task->id,
                'merchant_id' => $shipment->merchant_id,
                'shipment_id' => $shipment->id,
                'shipment_tracking_no' => $shipment->tracking_no, // هيبقى null لو شاغلين بـ pre_id
                'pre_id' => $shipment->pre_id, // Add pre_id as well
                'status' => MerchantPickupTaskStatusEnum::PICKED,
                'driver_id' => $actingUserId,
            ]);

            // ========= Increase picked_shipments_no for this driver/task =========
            if ((int) $task->driver_id === (int) $actingUserId) {
                // لو عندك العمود picked_shipments_no في جدول merchant_pickup_tasks
                $task->increment('picked_shipments_no');
            }
            // ====== OTP + إشعار ======
            try {
                $link = app(AddressUpdateLinkService::class)->generate($shipment);
            } catch (\Exception $e) {
                \Log::error("Failed to generate address link for shipment " . ($shipment->tracking_no ?: $shipment->pre_id) . ": " . $e->getMessage());
                $link = null;
            }

            $shipment->shipment_delivery->update([
                "delivery_otp" => generate_otp(),
                "otp_generated_at" => now()
            ]);

            // ====== History ======
            $status1 = "ORDER_COLLECTED";
            $status2 = "PICKED";
            $timestamp = operation_now()->addSeconds(10)->toDateTimeString();
            shipmentHistory([
                "description" => status($status1)['description'],
                "shipment_id" => $shipment->id,
                "status" => status($status1)['label'],
                "time" => $timestamp
            ]);



            $settings = MerchantSetting::firstOrCreate(
                ['merchant_id' => $shipment->merchant_id],
                ['created_shipment_notification' => 1]
            );
            if ($settings->created_shipment_notification && $link) {
                $shipment->consignee->notify(new ShipmentCreatedNotification($shipment, $link['url'], $link['otp']));
            }

            // ====== Proofs (لو سيستمك محتاج identifier مرن) ======
            $proofsResponse = [];
            try {

                $pickupService = app(ShipmentPickupService::class);
                // لو الخدمة بتقبل tracking بس، سيبها كده.
                // لو عايز تدعم pre_id، خلّي الخدمة تقبل identifier_type + identifier.
                $result = $pickupService->handleShipmentPickup($request, ($shipment->tracking_no ?: $shipment->pre_id), $actingUserId, $shipment);


                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }
                $proofsResponse = $result['data']['proofs'] ?? [];
                shipmentHistory([
                    "description" => status($status2)['description'],
                    "shipment_id" => $shipment->id,
                    "status" => status($status2)['label'],
                    "time" => $timestamp,
                    'proof' => $proofsResponse[0]['url'] ?? $proofsResponse[0]['path'] ?? null
                ]);
                // Update MerchantPickupShipment with pickup_proof (full URL)
                if (!empty($proofsResponse) && isset($merchantPickupShipment)) {
                    // Use full URL from service response
                    $proofUrl = $proofsResponse[0]['url'] ?? $proofsResponse[0]['path'] ?? null;

                    if ($proofUrl) {
                        // TODO [PHASE_E]: Remove - orchestrator should set pickup_proof
                        if (empty($merchantPickupShipment->pickup_proof)) {
                            $merchantPickupShipment->pickup_proof = $proofUrl;
                            $merchantPickupShipment->save();
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error("Shipment pickup failed for shipment " . ($shipment->tracking_no ?: $shipment->pre_id) . ": " . $e->getMessage());
            }

            // PICKUP_BONUS_UNIFICATION: Create pickup bonus for driver-created shipments
            // This handles cases where driver registers AND picks up shipment in one step
            try {
                \App\Domain\Pickup\ShipmentPickupFactory::addPickupBonus($shipment, $actingUserId);
            } catch (\Throwable $e) {
                // Bonus failure should NOT fail the shipment creation
                Log::warning('Pickup bonus creation failed in createShipment', [
                    'shipment_id' => $shipment->id ?? null,
                    'driver_id' => $actingUserId,
                    'error' => $e->getMessage(),
                ]);
            }

            DB::commit();

            // نرجّع نوع المعرف وقيمته عشان الفرونت يعرف يتعامل
            $identifierType = $shipment->tracking_no ? 'tracking_no' : 'pre_id';
            $identifier = $shipment->tracking_no ?: $shipment->pre_id;

            $responseData = new ShipmentResource($shipment->load("consignee", "shipment_items", "deliveryAddress"));
            $responseData = array_merge($responseData->toArray($request), [
                'proofs' => $proofsResponse,
                'identifier_type' => $identifierType,
                'identifier' => $identifier,
            ]);

            return sendResponse("Shipment created successfully.", $responseData);
        } catch (QueryException $e) {
            DB::rollBack();
            Sanctum::actingAs($originalUser, ['*']);
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            Sanctum::actingAs($originalUser, ['*']);
            return sendResponse("Unexpected Error Occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    private function updatePickupExtraShipmentsCount(MerchantPickupTask $task): void
    {
        $task->extra_shipments_no = ($task->extra_shipments_no ?? 0) + 1;
        $task->picked_shipments_no = ($task->picked_shipments_no ?? 0) + 1;

        $task->save();
    }

    // public function createShipment(StoreShipmentRequest $request)
    // {
    //     if (!$request->filled('tracking_no') && !$request->filled('pre_id')) {
    //         $request->merge(['pre_id' => generate_pre_id()]);
    //     }
    //     $request->validated();
    //     DB::beginTransaction();

    //     $originalUser = Auth::user();
    //     $sendWhatsappSetting = Setting::where('key', 'send_whatsapp_after_create_shipment')->value('value');
    //     $shouldSendWhatsapp = $sendWhatsappSetting === 'yes';

    //     try {
    //         $consigneeData = $request->only([
    //             "name",
    //             "email",
    //             "cellphone",
    //             "alternatePhone",
    //             "district",
    //             "country_id",
    //             "governorate_id",
    //             "state_id",
    //             "place_id",
    //             "city_id",
    //             "zipcode",
    //             "streetAddress",
    //             "identify",
    //             "taxNumber",
    //             "longitude",
    //             "latitude",
    //             "location_url",
    //         ]);

    //         $cellphoneSplit = splitPhoneNumber($consigneeData['cellphone']);
    //         $consigneeData['country_key_cellphone'] = $cellphoneSplit['country_code'];
    //         $consigneeData['cellphone'] = $cellphoneSplit['national_number'];

    //         $alt = $request->input('alternatePhone');
    //         if ($alt) {
    //             $alternatePhoneSplit = splitPhoneNumber($alt);
    //             $consigneeData['country_key_alternatePhone'] = $alternatePhoneSplit['country_code'];
    //             $consigneeData['alternatePhone'] = $alternatePhoneSplit['national_number'];
    //         } else {
    //             $consigneeData['country_key_alternatePhone'] = null;
    //             $consigneeData['alternatePhone'] = null;
    //         }

    //         $existingConsignee = Consignee::withoutGlobalScope(ConsigneeScope::class)
    //             ->where('cellphone', $cellphoneSplit['national_number'])
    //             ->where('country_key_cellphone', $cellphoneSplit['country_code'])
    //             ->when($consigneeData['country_id'] ?? null, fn($q, $cid) => $q->where('country_id', $cid))
    //             ->first();

    //         if ($existingConsignee) {
    //             if (collect($consigneeData)->diffAssoc($existingConsignee->only(array_keys($consigneeData)))->isNotEmpty()) {
    //                 $existingConsignee->update($consigneeData);
    //             }
    //             $consignee = $existingConsignee;
    //         } else {
    //             $consignee = Consignee::create($consigneeData);
    //         }

    //         $shipmentData = $request->only([
    //             "shipper_id",
    //             "notes",
    //             "payment_type",
    //             "value",
    //             "delivery_fee",
    //             "merchant_id",
    //             "fee_payer",
    //             "is_outsourced",
    //             "allow_return",
    //             "delivery_priority",
    //             "delivery_time",
    //             "sender_district",
    //             "sender_location_url",
    //             "sender_notes",
    //             "sender_streetAddress",
    //             "sender_zipcode",
    //             "need_invoice"
    //         ]);
    //         $shipmentData['tracking_no'] = generate_tracking_no();

    //         $actingUserId = Auth::id();
    //         $passedMerchantId = $request->input('merchant_id');

    //         $guestMerchantUserId = null;
    //         if (!$request->filled('merchant_id') || $request->merchant_id === null) {
    //             $shipmentData['is_walkin'] = 1;
    //             $shipmentData['customer_name'] = $request->merchant_name;
    //             $shipmentData['customer_phone'] = $request->merchant_phone;

    //             $phoneSplit = splitPhoneNumber($request->merchant_phone);

    //             $merchantData = [
    //                 'country_id' => $request->sender_country_id ?? null,
    //                 'governorate_id' => $request->sender_governorate_id ?? null,
    //                 'state_id' => $request->sender_state_id ?? null,
    //                 'place_id' => $request->sender_place_id ?? null,
    //                 'city_id' => $request->sender_city_id ?? null,
    //                 'address' => $request->sender_streetAddress ?? null,
    //                 'country_code' => $phoneSplit['country_code'],
    //                 'contact_no' => $phoneSplit['national_number'],
    //                 'lat' => $request->sender_latitude ?? null,
    //                 'lng' => $request->sender_longitude ?? null,
    //                 'is_guest' => true,
    //                 'owner_id' => facility("id"),
    //                 'owner_type' => facility("type"),
    //             ];

    //             $existingMerchant = Merchant::withoutGlobalScope('scopeByOwner')
    //                 ->where('contact_no', $phoneSplit['national_number'])
    //                 ->where('country_code', $phoneSplit['country_code'])
    //                 ->when($merchantData['country_id'], fn($q, $cid) => $q->where('country_id', $cid))
    //                 ->first();

    //             if ($existingMerchant) {
    //                 if (collect($merchantData)->diffAssoc($existingMerchant->only(array_keys($merchantData)))->isNotEmpty()) {
    //                     $existingMerchant->update($merchantData);
    //                 }
    //                 $guestMerchant = $existingMerchant;
    //                 $guestMerchantUserId = $existingMerchant->user_id;
    //             } else {
    //                 $userData = [
    //                     'name' => $request->merchant_name,
    //                     'email' => $request->sender_email ?? 'guest_' . time() . '@example.com',
    //                     'phone' => $phoneSplit['national_number'],
    //                     'country_code' => $phoneSplit['country_code'],
    //                     'password' => bcrypt('guest_password'),
    //                     'owner_id' => facility("id"),
    //                     'owner_type' => facility("type"),
    //                 ];
    //                 $user = User::create($userData);
    //                 $user->assignRole("Merchant");

    //                 $merchantData['user_id'] = $user->id;
    //                 $guestMerchant = Merchant::create($merchantData);
    //                 $guestMerchantUserId = $user->id;

    //                 $states = State::select('id')->get();
    //                 $defaultMerchantCommission = optional(Setting::where('key', 'default_merchant_commission')->first())->value ?? 1;
    //                 foreach ($states as $state) {
    //                     MerchantCommission::create([
    //                         'merchant_id' => $user->id,
    //                         'country_id' => $merchantData['country_id'] ?? null,
    //                         'state_id' => $state->id,
    //                         'delivery_fee' => $defaultMerchantCommission,
    //                         'return_fee' => 1.00,
    //                     ]);
    //                 }

    //                 Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    //             }

    //             $shipmentData['merchant_id'] = $guestMerchantUserId;
    //             $shipmentData['created_by'] = $shipmentData['merchant_id'];

    //             $waybill = MerchantWaybill::create([
    //                 "merchant_id" => $shipmentData['merchant_id'],
    //                 "tracking_no" => generate_driver_tracking_no()
    //             ]);
    //             $shipmentData['tracking_no'] = $waybill->tracking_no;

    //         } else {
    //             $shipmentData['merchant_id'] = $passedMerchantId;
    //             $shipmentData['created_by'] = $passedMerchantId;
    //         }

    //         if (!$request->shipper_id) {
    //             $shipmentData['shipper_id'] = Shipper::pe()->id;
    //         }
    //         $shipmentData['owner_id'] = Auth::user()->owner_id;
    //         $shipmentData['owner_type'] = Auth::user()->owner_type;
    //         $shipmentData['facility_id'] = Auth::user()->facility_id;
    //         $shipmentData['facility_type'] = Auth::user()->facility_type;

    //         $shipperCommissionFee = null;
    //         $merchantCommissionFee = null;

    //         if ($request->filled("shipper_id")) {
    //             $shipper_commission = ShipperCommission::where('shipper_id', $request->shipper_id)
    //                 ->where('state_id', $consigneeData['state_id'])
    //                 ->first();
    //             $shipperCommissionFee = optional($shipper_commission)->delivery_fee;
    //         }

    //         if (!empty($shipmentData['merchant_id'])) {
    //             $merchant_commission = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
    //                 ->where('state_id', $consigneeData['state_id'])
    //                 ->first();
    //             $merchantCommissionFee = optional($merchant_commission)->delivery_fee;
    //         }

    //         if ($shipperCommissionFee !== null && $merchantCommissionFee !== null) {
    //             $shipmentData['delivery_fee'] = max($shipperCommissionFee, $merchantCommissionFee);
    //         } elseif ($merchantCommissionFee !== null) {
    //             $shipmentData['delivery_fee'] = $merchantCommissionFee;
    //         } elseif ($shipperCommissionFee !== null) {
    //             $shipmentData['delivery_fee'] = $shipperCommissionFee;
    //         } else {
    //             $shipmentData['delivery_fee'] = (isset($shipmentData['delivery_fee']) && is_numeric($shipmentData['delivery_fee']) && $shipmentData['delivery_fee'] > 0)
    //                 ? $shipmentData['delivery_fee'] : 2;
    //         }

    //         $shipmentData['consignee_id'] = $consignee->id;
    //         $shipmentData['amount'] = (float) ($shipmentData['delivery_fee'] ?? 0) + (float) ($request->value ?? 0);

    //         if ($request->filled('tracking_no')) {
    //             $shipmentData['tracking_no'] = $request->tracking_no;
    //         }

    //         $shipment = Shipment::create($shipmentData);
    //         $shipment->load('consignee');

    //         $addressData = [
    //             'consignee_id' => $consignee->id,
    //             'country_id' => $consigneeData['country_id'] ?? null,
    //             'governorate_id' => $consigneeData['governorate_id'] ?? null,
    //             'state_id' => $consigneeData['state_id'] ?? null,
    //             'place_id' => $consigneeData['place_id'] ?? null,
    //             'city_id' => $consigneeData['city_id'] ?? null,
    //             'zipcode' => $consigneeData['zipcode'] ?? null,
    //             'streetAddress' => $consigneeData['streetAddress'] ?? null,
    //             'longitude' => $consigneeData['longitude'] ?? null,
    //             'latitude' => $consigneeData['latitude'] ?? null,
    //             'location_url' => $request->input('location_url'),
    //             'label' => null,
    //             'approved' => false,
    //             'is_active' => true,
    //         ];

    //         if ($request->filled('delivery_address_id')) {
    //             $addr = Address::where('id', (int) $request->delivery_address_id)
    //                 ->where('consignee_id', $consignee->id)
    //                 ->first();
    //             if (!$addr) {
    //                 throw new \Exception("Delivery address not found for this consignee.");
    //             }
    //         } else {
    //             $addr = Address::create($addressData);
    //         }

    //         $shipment->update(['delivery_address_id' => $addr->id]);
    //         $shipment->load(['deliveryAddress.state', 'deliveryAddress.governorate', 'deliveryAddress.place']);

    //         $zone = $shipment->zone();
    //         if ($zone) {
    //             $shipment->destination_owner_id = $zone->owner_id;
    //             $shipment->destination_owner_type = $zone->owner_type;
    //             $shipment->save();
    //         }

    //         if ((int) ($shipment->is_outsourced ?? 0) !== 1 && $shouldSendWhatsapp) {
    //             $link = app(AddressUpdateLinkService::class)->generate($shipment);
    //             $shipment->consignee->notify(new ShipmentCreatedNotification(
    //                 $shipment,
    //                 $link['url'],
    //                 $link['otp']
    //             ));
    //         }

    //         $merchantAccount = Account::firstOrCreate(
    //             ['accountable_type' => User::class, 'accountable_id' => $shipment->merchant_id],
    //             ['parcel_value' => 0, 'balance' => 0]
    //         );
    //         $merchantAccount->parcel_value += $shipmentData['amount'];
    //         $merchantAccount->save();

    //         if ($request->filled('tracking_no')) {
    //             $merchantWaybill = MerchantWaybill::where("tracking_no", $shipmentData['tracking_no'])->first();
    //             if ($merchantWaybill) {
    //                 $merchantWaybill->used = 1;
    //                 $merchantWaybill->save();
    //             }
    //         }

    //         Transaction::create([
    //             "to_id" => $shipment->merchant_id,
    //             "to_type" => User::class,
    //             "shipment_id" => $shipment->id,
    //             "amount" => $shipment->amount,
    //             "type" => "merchant_created",
    //         ]);

    //         ShipmentInformation::create([
    //             'shipment_id' => $shipment->id,
    //             'merchant_id' => $shipment->merchant_id,
    //             'unit_id' => $request->unit_id,
    //             'zone_id' => $zone->id ?? $request->zone_id,
    //             'package_id' => $request->package_id,
    //             'tracking_no' => $shipment->tracking_no,
    //             'in_warehouse' => true,
    //             'weight' => $request->weight,
    //             'height' => $request->height,
    //             'width' => $request->width,
    //             'length' => $request->length,
    //             'status' => $request->status ?? 0,
    //         ]);

    //         ShipmentDelivery::create(['shipment_id' => $shipment->id]);
    //         ShipmentFinance::create(['shipment_tracking_no' => $shipment->tracking_no]);

    //         if ($request->has('item_name') && is_array($request->item_name)) {
    //             foreach ($request->item_name as $id => $itemName) {
    //                 $quantity = $request->input("quantity.$id");
    //                 $category = $request->input("category.$id");
    //                 if ($itemName && $quantity && $category) {
    //                     ShipmentItem::create([
    //                         'shipment_id' => $shipment->id,
    //                         'name' => $itemName,
    //                         'quantity' => $quantity,
    //                         'category' => $category,
    //                     ]);
    //                 }
    //             }
    //         }

    //         $task = MerchantPickupTask::firstOrCreate(
    //             ['merchant_id' => $shipment->merchant_id, 'status' => 'created'],
    //             ['merchant_id' => $shipment->merchant_id]
    //         );

    //         MerchantPickupShipment::create([
    //             'pickup_task_id' => $task->id,
    //             'merchant_id' => $shipment->merchant_id,
    //             'shipment_tracking_no' => $shipment->tracking_no,
    //             'status' => 'created'
    //         ]);

    //         try {
    //             $link = app(AddressUpdateLinkService::class)->generate($shipment);
    //         } catch (\Exception $e) {
    //             \Log::error("Failed to generate address link for shipment {$shipment->tracking_no}: " . $e->getMessage());
    //             $link = null;
    //         }

    //         $shipment->shipment_delivery->update([
    //             "delivery_otp" => generate_otp(),
    //             "otp_generated_at" => now()
    //         ]);

    //         $status1 = "ORDER_COLLECTED";
    //         $timestamp = \Carbon\Carbon::now()->addSeconds(10)->toDateTimeString();
    //         shipmentHistory([
    //             "description" => status($status1)['description'],
    //             "shipment_id" => $shipment->id,
    //             "status" => status($status1)['label'],
    //             "time" => $timestamp
    //         ]);

    //         $settings = MerchantSetting::firstOrCreate(
    //             ['merchant_id' => $shipment->merchant_id],
    //             ['created_shipment_notification' => 1]
    //         );
    //         if ($settings->created_shipment_notification && $link) {
    //             $shipment->consignee->notify(new ShipmentCreatedNotification($shipment, $link['url'], $link['otp']));
    //         }

    //         $proofsResponse = [];
    //         try {
    //             $pickupService = app(ShipmentPickupService::class);
    //             $result = $pickupService->handleShipmentPickup($request, $shipment->tracking_no, $actingUserId, $shipment);
    //             if (!$result['success']) {
    //                 throw new \Exception($result['message']);
    //             }
    //             $proofsResponse = $result['data']['proofs'] ?? [];
    //         } catch (\Exception $e) {
    //             \Log::error("Shipment pickup failed for shipment {$shipment->tracking_no}: " . $e->getMessage());
    //         }

    //         DB::commit();

    //         $responseData = new ShipmentResource($shipment->load("consignee", "shipment_items", "deliveryAddress"));
    //         $responseData = array_merge($responseData->toArray($request), ['proofs' => $proofsResponse]);

    //         return sendResponse("Shipment created successfully.", $responseData);

    //     } catch (QueryException $e) {
    //         DB::rollBack();
    //         Sanctum::actingAs($originalUser, ['*']);
    //         return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         Sanctum::actingAs($originalUser, ['*']);
    //         return sendResponse("Unexpected Error Occurred.", [], false, [$e->getMessage()], 500);
    //     }
    // }


}
