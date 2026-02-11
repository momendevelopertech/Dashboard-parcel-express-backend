<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\DeliveryExceptionEnum;
use App\Enums\ShipmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\DriverShipmentAssignment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceShipment;
use App\Models\DriverRunsheetShipment;
use App\Notifications\ShipmentDeliverLaterTodayNotification;

class InstantDeliveryController extends Controller
{
    /**
     * 1. Show all offers (notifications) for the authenticated driver
     * These are shipments offered to the driver through NearestDriverService
     */
    public function getOffers(Request $request)
    {
        try {
            $driver = Auth::user();

            if (!$driver || !$driver->hasRole(['Driver', 'Guest Driver', 'Vendor Driver'])) {
                return sendResponse("Unauthorized access.", [], false, ['Only drivers can access offers'], 403);
            }

            $query = DriverShipmentAssignment::with([
                'shipment',
                'shipment.consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone',
                'shipment.consignee.country:id,name',
                'shipment.consignee.governorate:id,en_name,ar_name',
                'shipment.consignee.state:id,en_name,ar_name',
                'shipment.consignee.place:id,en_name,ar_name',
                'shipment.shipper:id,name,country_id,state_id,contact,zip_code,address',
                'shipment.shipper.country:id,name',
                'shipment.shipper.state:id,en_name,ar_name',
                'shipment.merchant:id,name',
                'shipment.shipment_information:id,shipment_id,zone_id,weight,height,width,length',
                'shipment.shipment_information.zone:id,name',
                'shipment.shipment_items',
                'shipment.core_status'
            ])
                ->where('driver_id', $driver->id)
                ->where('status', strtoupper('offered'))
                ->whereNull('accepted_at')
                ->whereNull('confirmed_at');

            // Add search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('shipment', function ($q) use ($search) {
                    $q->where('tracking_no', 'like', "%{$search}%")
                        ->orWhereHas('consignee', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('cellphone', 'like', "%{$search}%");
                        });
                });
            }

            $offers = $query->orderByDesc('offered_at')->paginate(15);

            if ($offers->isEmpty()) {
                return sendResponse("No offers available.", [], false, ['no offers found'], 404);
            }

            return sendResponse("Offers retrieved successfully.", [
                'offers' => $offers->items(),
                'pagination' => [
                    'current_page' => $offers->currentPage(),
                    'last_page' => $offers->lastPage(),
                    'per_page' => $offers->perPage(),
                    'total' => $offers->total(),
                    'from' => $offers->firstItem(),
                    'to' => $offers->lastItem()
                ]
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching offers.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * 2. Accept an offer and update status
     * This changes the DriverShipmentAssignment status and updates the Shipment
     */
    public function acceptOffer(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:driver_shipment_assignments,id',
        ]);

        DB::beginTransaction();
        try {
            $driver = Auth::user();

            if (!$driver || !$driver->hasRole(['Driver', 'Guest Driver', 'Vendor Driver'])) {
                return sendResponse("Unauthorized access.", [], false, ['Only drivers can accept offers'], 403);
            }

            $assignment = DriverShipmentAssignment::with(['shipment', 'shipment.consignee'])
                ->where('id', $request->assignment_id)
                ->where('driver_id', $driver->id)
                ->where('status', 'offered')
                ->first();

            if (!$assignment) {
                return sendResponse("Offer not found or already processed.", [], false, ['Invalid assignment'], 422);
            }

            // Update assignment status
            $assignment->update([
                'status' => 'ASSIGNED',
                'accepted_at' => now(),
            ]);

            // Update the shipment with driver information
            $shipment = $assignment->shipment;
            $shipment->update([
                'driver_id' => $driver->id,
                'assignment_id' => $assignment->id,
            ]);

            // Reject all other offers for this shipment
            DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->where('id', '!=', $assignment->id)
                ->where('status', strtoupper('offered'))
                ->update([
                    'status' => strtoupper('rejected_auto'),
                    'accepted_at' => now()
                ]);

            // Create shipment history
            $historyData = [
                "description" => "Shipment accepted by driver: " . $driver->name . " (" . $driver->email . ")",
                "shipment_id" => $shipment->id,
                "status" => strtoupper('DRIVER_ACCEPTED')
            ];
            shipmentHistory($historyData);

            // Log activity
            activityLog("driver_offer_accepted", "Driver {$driver->name} accepted offer for shipment #{$shipment->tracking_no}");

            DB::commit();

            return sendResponse("Offer accepted successfully.", [
                'assignment' => $assignment->load(['shipment', 'shipment.consignee', 'shipment.shipper', 'shipment.shipment_items']),
                'shipment' => new ShipmentResource($shipment->load(['consignee', 'shipper', 'shipment_items', 'shipment_information']))
            ]);
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while accepting offer.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * 3. Reject an offer
     * This only updates the DriverShipmentAssignment status
     */
    public function rejectOffer(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:driver_shipment_assignments,id',
        ]);

        try {
            $driver = Auth::user();

            if (!$driver || !$driver->hasRole(['Driver', 'Guest Driver', 'Vendor Driver'])) {
                return sendResponse("Unauthorized access.", [], false, ['Only drivers can reject offers'], 403);
            }

            $assignment = DriverShipmentAssignment::with(['shipment'])
                ->where('id', $request->assignment_id)
                ->where('driver_id', $driver->id)
                ->where('status', 'offered')
                ->first();

            if (!$assignment) {
                return sendResponse("Offer not found or already processed.", [], false, ['Invalid assignment'], 422);
            }

            // Update assignment status
            $assignment->update([
                'status' => strtoupper('rejected'),
                'accepted_at' => now(),
                'rejection_reason' => $request->reason
            ]);

            // Log activity
            activityLog("driver_offer_rejected", "Driver {$driver->name} rejected offer for shipment #{$assignment->shipment->tracking_no}" . ($request->reason ? " - Reason: {$request->reason}" : ""));

            return sendResponse("Offer rejected successfully.", [
                'assignment_id' => $assignment->id,
                'status' => $assignment->status
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while rejecting offer.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * 4. Show all accepted shipments for the authenticated driver
     * These are shipments that the driver has accepted and are in progress
     */
    public function getAcceptedShipments(Request $request)
    {
        try {
            $driver = Auth::user();

            if (!$driver || !$driver->hasRole(['Driver', 'Guest Driver', 'Vendor Driver'])) {
                return sendResponse("Unauthorized access.", [], false, ['Only drivers can access accepted shipments'], 403);
            }

            $query = DriverShipmentAssignment::with([
                'shipment',
                'shipment.consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone',
                'shipment.consignee.country:id,name',
                'shipment.consignee.governorate:id,en_name,ar_name',
                'shipment.consignee.state:id,en_name,ar_name',
                'shipment.consignee.place:id,en_name,ar_name',
                'shipment.shipper:id,name,country_id,state_id,contact,zip_code,address',
                'shipment.shipper.country:id,name',
                'shipment.shipper.state:id,en_name,ar_name',
                'shipment.merchant:id,name',
                'shipment.shipment_information:id,shipment_id,zone_id,weight,height,width,length',
                'shipment.shipment_information.zone:id,name',
                'shipment.shipment_items',
                'shipment.core_status',
                'shipment.shipmentHistories' => function ($query) {
                    $query->orderByDesc('created_at')->limit(5);
                }
            ])
                ->where('driver_id', $driver->id)
                ->whereIn('status', ['ASSIGNED', 'DISPATCH', 'PICKED_UP', 'IN_TRANSIT'])
                ->whereNotNull('accepted_at');

            // Add search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('shipment', function ($q) use ($search) {
                    $q->where('tracking_no', 'like', "%{$search}%")
                        ->orWhereHas('consignee', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('cellphone', 'like', "%{$search}%");
                        });
                });
            }

            // Add status filter
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            $acceptedShipments = $query->orderByDesc('accepted_at')->paginate(15);

            if ($acceptedShipments->isEmpty()) {
                return sendResponse("No accepted shipments found.", [], false, ['no accepted shipments'], 404);
            }

            // Count shipments by status for dashboard
            $statusCounts = DriverShipmentAssignment::where('driver_id', $driver->id)
                ->whereIn('status', ['ASSIGNED', 'DISPATCH', 'PICKED_UP', 'IN_TRANSIT'])
                ->whereNotNull('accepted_at')
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return sendResponse("Accepted shipments retrieved successfully.", [
                'shipments' => $acceptedShipments->items(),
                'pagination' => [
                    'current_page' => $acceptedShipments->currentPage(),
                    'last_page' => $acceptedShipments->lastPage(),
                    'per_page' => $acceptedShipments->perPage(),
                    'total' => $acceptedShipments->total(),
                    'from' => $acceptedShipments->firstItem(),
                    'to' => $acceptedShipments->lastItem()
                ],
                'status_counts' => $statusCounts
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching accepted shipments.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * 5. Pickup an accepted shipment
     * Driver confirms pickup of an shipment using existing infrastructure
     */
    public function pickupShipment(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:driver_shipment_assignments,id',
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $driver = Auth::user();

            if (!$driver || !$driver->hasRole(['Driver', 'Guest Driver', 'Vendor Driver'])) {
                return sendResponse("Unauthorized access.", [], false, ['Only drivers can pickup shipments'], 403);
            }

            $assignment = DriverShipmentAssignment::with(['shipment', 'shipment.consignee'])
                ->where('id', $request->assignment_id)
                ->where('driver_id', $driver->id)
                ->whereIn('status', ['ASSIGNED', 'DISPATCH'])
                ->first();

            if (!$assignment) {
                return sendResponse("Shipment not found or not ready for pickup.", [], false, ['Invalid assignment or status'], 422);
            }

            $shipment = $assignment->shipment;

            // Update assignment status to picked up
            $assignment->update([
                'status' => 'PICKED_UP',
                'confirmed_at' => now(),
            ]);

            // Update shipment delivery with pickup location if provided
            if ($request->pickup_lat && $request->pickup_lng) {
                $shipment->shipment_delivery->update([
                    'pickup_lat' => $request->pickup_lat,
                    'pickup_lng' => $request->pickup_lng,
                ]);
            }

            // Create shipment history using existing infrastructure
            $historyData = [
                "description" => "Shipment picked up by driver: " . $driver->name,
                "shipment_id" => $shipment->id,
                "status" => "PICKED"
            ];

            if ($request->notes) {
                $historyData["description"] .= " - Notes: " . $request->notes;
            }

            shipmentHistory($historyData);

            // Update shipment status using existing helper
            updateShipmentStatus($shipment->id, status('PICKED')['label']);

            // Log activity using existing helper
            activityLog("shipment_picked_up", "Shipment #{$shipment->tracking_no} picked up by driver {$driver->name}");

            DB::commit();

            return sendResponse("Shipment picked up successfully.", [
                'assignment' => $assignment->load(['shipment', 'shipment.consignee', 'shipment.shipper']),
                'shipment' => new ShipmentResource($shipment->load(['consignee', 'shipper', 'shipment_items', 'shipment_information']))
            ]);

        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while picking up shipment.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * 6. Deliver an shipment
     * Driver delivers an shipment using existing delivery infrastructure
     */
    public function deliverShipment(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:driver_shipment_assignments,id',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
            'payment_cash' => 'nullable|numeric|min:0',
            'payment_bank_transfer' => 'nullable|numeric|min:0',
            'customer_delivery_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'proof' => 'nullable|file|image|max:10240', // 10MB max
        ]);

        DB::beginTransaction();
        try {
            $driver = Auth::user();

            if (!$driver || !$driver->hasRole(['Driver', 'Guest Driver', 'Vendor Driver'])) {
                return sendResponse("Unauthorized access.", [], false, ['Only drivers can deliver shipments'], 403);
            }

            $assignment = DriverShipmentAssignment::with(['shipment', 'shipment.consignee', 'shipment.shipment_finance'])
                ->where('id', $request->assignment_id)
                ->where('driver_id', $driver->id)
                ->whereIn('status', ['PICKED_UP', 'IN_TRANSIT'])
                ->first();

            if (!$assignment) {
                return sendResponse("Shipment not found or not ready for delivery.", [], false, ['Invalid assignment or status'], 422);
            }

            $shipment = $assignment->shipment;

            $paymentCash = $request->payment_cash ?? 0;
            $paymentBankTransfer = $request->payment_bank_transfer ?? 0;
            $totalPayment = $paymentCash + $paymentBankTransfer;

            // Validate payment amounts for ALL shipments
            if ($totalPayment != $shipment->total_cod) {
                return sendResponse(
                    "The sum of cash and bank transfer payments does not match the shipment amount.",
                    [],
                    false,
                    ["Payment total mismatch: Received: {$totalPayment} vs Shipment amount: {$shipment->total_cod}"],
                    422
                );
            }

            // Update shipment delivery with payment and location info
            $shipment->shipment_delivery->update([
                'payment_cash' => $paymentCash,
                'payment_bank_transfer' => $paymentBankTransfer,
                'delivery_lat' => $request->delivery_lat ?? 0,
                'delivery_lng' => $request->delivery_lng ?? 0,
            ]);

            // Update assignment as delivered
            $assignment->update([
                'status' => 'DELIVERED',
                'delivered_at' => now(),
            ]);

            $status = "DELIVERED";
            $extraDescription = $request->description ?? '';

            // Prepare history data using existing infrastructure
            $historyData = [
                "status" => status($status)['label'],
                "description" => status($status)['description'],
                "shipment_id" => $shipment->id,
            ];

            // Handle proof upload for paid shipments
            if ($shipment->payment_type == 'Paid') {
                if (!$request->hasFile('proof')) {
                    return sendResponse("Please upload proof of delivery.", [], false, [], 422);
                }
                $historyData['proof'] = uploadFile($request->file('proof'), 'public/deliveries/proofs');

                // Handle customer delivery fee
                if ($shipment->fee_payer == "customer") {
                    if ($request->customer_delivery_fee) {
                        $shipment->shipment_finance->update(['delivery_fee_paid_by_customer' => $request->customer_delivery_fee]);
                    } else {
                        return sendResponse("Please enter customer delivery fees.", [], false, ["Customer delivery fee required"], 422);
                    }
                } else if ($shipment->fee_payer == "merchant") {
                    $merchant_invoice = Invoice::where('invoiceable_id', $shipment->merchant_id)
                        ->where('invoiceable_type', User::class)
                        ->where('status', 'pending')
                        ->first();

                    if (!$merchant_invoice) {
                        $merchant_invoice = Invoice::create([
                            'invoiceable_id' => $shipment->merchant_id,
                            'invoiceable_type' => User::class,
                            'status' => 'pending',
                            'amount' => 0,
                        ]);
                    }

                    InvoiceShipment::create([
                        "invoice_id" => $merchant_invoice->id,
                        "shipment_tracking_no" => $shipment->tracking_no
                    ]);
                }
            } else if ($shipment->payment_type == 'COD') {
                if (!empty($extraDescription)) {
                    $historyData['description'] .= ' [' . $extraDescription . ']';
                }
            }

            // Create shipment history using existing infrastructure
            shipmentHistory($historyData);

            // Update shipment status using existing helper
            updateShipmentStatus($shipment->id, status($status)['label']);

            // Update runsheet shipment status like DriverShipmentController
            DriverRunsheetShipment::where("shipment_tracking_no", $shipment->tracking_no)->update([
                "status" => "delivered"
            ]);

            // Handle financial transactions using existing infrastructure
            if ($shipment->payment_type == 'COD') {
                $transactionAmount = $shipment->total_cod ?? 0;

                $driverAccount = Account::where('accountable_id', $driver->id)
                    ->where('accountable_type', User::class)
                    ->first();

                $driverAccount->parcel_value -= $transactionAmount;
                $driverAccount->cash_balance += $transactionAmount;
                $driverAccount->save();
            }

            // Log activity using existing helper
            $now = now()->format('Y-m-d H:i:s');
            activityLog("instant_delivery_completed", "Instant delivery shipment #{$shipment->tracking_no} delivered at {$now} by {$driver->name}");

            DB::commit();

            return sendResponse("Shipment delivered successfully.", [
                'assignment' => $assignment->load(['shipment', 'shipment.consignee', 'shipment.shipper']),
                'shipment' => new ShipmentResource($shipment->load(['consignee', 'shipper', 'shipment_items', 'shipment_information', 'shipment_delivery']))
            ]);

        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while delivering shipment.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * 7. Handle delivery exception for instant delivery
     * Driver reports delivery exception using existing infrastructure
     */
    public function reportException(Request $request)
    {
        $validExceptions = implode(',', DeliveryExceptionEnum::all());

        $request->validate([
            'assignment_id' => 'required|exists:driver_shipment_assignments,id',
            'delivery_exception' => "required|in:{$validExceptions}",
            'future_delivery_date' => 'nullable|date|after:today',
            // دعم "بعد ساعتين" أو وقت محدد
            'deliver_later_minutes' => 'nullable|integer|min:30|max:240',
            'deliver_later_until' => 'nullable|date|after:now',
            'proof' => 'nullable|file|image|max:10240',
            'reason' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            $driver = Auth::user();

            if (!$driver || !$driver->hasRole(['Driver', 'Guest Driver', 'Vendor Driver'])) {
                return sendResponse("Unauthorized access.", [], false, ['Only drivers can report exceptions'], 403);
            }

            $assignment = DriverShipmentAssignment::with(['shipment', 'shipment.shipment_delivery', 'shipment.consignee'])
                ->where('id', $request->assignment_id)
                ->where('driver_id', $driver->id)
                ->whereIn('status', ['PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'])
                ->first();

            if (!$assignment) {
                return sendResponse("Shipment not found or not ready for exception reporting.", [], false, ['Invalid assignment or status'], 422);
            }

            $shipment = $assignment->shipment;
            $originalStatus = DeliveryExceptionEnum::normalize($request->delivery_exception);

            /**
             * 1) FUTURE_DELIVERY => تاريخ مستقبلي (بالأيام)
             */
            if ($originalStatus === DeliveryExceptionEnum::FUTURE_DELIVERY) {
                if (!$request->has('future_delivery_date')) {
                    return sendResponse("Future delivery date required", [], false, ["Future delivery date is missing"], 422);
                }

                $futureDate = Carbon::parse($request->future_delivery_date);
                if (!$futureDate->isFuture()) {
                    return sendResponse("Invalid Date", [], false, ["Future delivery date must be in the future"], 422);
                }

                // حفظ التاريخ في الحقل الموجود مسبقًا
                optional($shipment->shipment_delivery)->update(['future_delivery_date' => $futureDate]);

                // استاتسات "استثناء"
                $status = "DELIVERY_EXCEPTION";
                $shipment->update(['in_exception' => true]);
                $assignment->update([
                    'status' => 'EXCEPTION',
                    'delivered_at' => null,
                    'confirmed_at' => null
                ]);

                $historyData = [
                    "shipment_id" => $shipment->id,
                    "status" => $status,
                    "description" => "Marked as DELIVERY_EXCEPTION [FUTURE_DELIVERY] by driver: " . $driver->name . ($request->reason ? " - Reason: " . $request->reason : ""),
                    "type" => "FUTURE_DELIVERY",
                ];

                if ($request->hasFile('proof')) {
                    $historyData['proof'] = uploadFile($request->file('proof'), 'public/exception_proofs');
                }

                shipmentHistory($historyData);
                updateShipmentStatus($shipment->id, $status);
                activityLog("instant_delivery_exception", "Exception reported for instant delivery shipment #{$shipment->tracking_no}: FUTURE_DELIVERY");

                DB::commit();

                return sendResponse("Exception reported successfully.", [
                    'assignment' => $assignment->load(['shipment', 'shipment.consignee']),
                    'shipment' => new ShipmentResource($shipment->load(['consignee', 'shipper', 'shipment_items']))
                ]);
            }

            if ($originalStatus === DeliveryExceptionEnum::DELIVER_LATER_TODAY) {
                $deferUntil = $request->filled('deliver_later_until')
                    ? Carbon::parse($request->deliver_later_until)
                    : now()->addMinutes($request->integer('deliver_later_minutes', 120));

                if (!$deferUntil->isFuture()) {
                    return sendResponse("Invalid time", [], false, ["deliver_later_until must be in the future"], 422);
                }

                optional($shipment->shipment_delivery)->update([
                    'deliver_later_until' => $deferUntil,
                    'deliver_later_reason' => 'DELIVER_LATER_TODAY',
                ]);

                $assignment->update([
                    'status' => 'DEFERRED',
                    'delivered_at' => null,
                    'confirmed_at' => null
                ]);

                $shipment->update([
                    'in_exception' => false,
                    'status' => 'DEFERRED'
                ]);

                $desc = "Delivery deferred until " . $deferUntil->format('Y-m-d H:i');
                if ($request->reason) {
                    $desc .= " - Reason: " . $request->reason;
                }
                shipmentHistory([
                    "shipment_id" => $shipment->id,
                    "status" => "DEFERRED",
                    "description" => $desc,
                    "type" => "DELIVER_LATER_TODAY",
                ]);

                try {
                    $shipment->consignee->notify(new ShipmentDeliverLaterTodayNotification($shipment, $deferUntil));
                } catch (\Throwable $e) {
                    info("WhatsApp notify failed (DELIVER_LATER_TODAY): " . $e->getMessage());
                }

                activityLog("deliver_later_today", "Shipment #{$shipment->tracking_no} deferred until {$deferUntil->toDateTimeString()}");

                DB::commit();

                return sendResponse("Delivery will be attempted later today.", [
                    'deliver_later_until' => $deferUntil,
                    'assignment' => $assignment->fresh()->load(['shipment', 'shipment.consignee']),
                    'shipment' => new ShipmentResource($shipment->fresh()->load(['consignee', 'shipper', 'shipment_items']))
                ]);
            }


            $status = "DELIVERY_EXCEPTION";

            $shipment->update(['in_exception' => true]);

            $assignment->update([
                'status' => 'EXCEPTION',
                'delivered_at' => null,
                'confirmed_at' => null
            ]);

            $historyData = [
                "shipment_id" => $shipment->id,
                "status" => $status,
                "description" => "Marked as DELIVERY_EXCEPTION [$originalStatus] by driver: " . $driver->name,
                "type" => $originalStatus,
            ];

            if ($request->reason) {
                $historyData["description"] .= " - Reason: " . $request->reason;
            }

            if ($request->hasFile('proof')) {
                $historyData['proof'] = uploadFile($request->file('proof'), 'public/exception_proofs');
            }

            shipmentHistory($historyData);
            updateShipmentStatus($shipment->id, $status);
            activityLog("instant_delivery_exception", "Exception reported for instant delivery shipment #{$shipment->tracking_no}: {$originalStatus}");

            DB::commit();

            return sendResponse("Exception reported successfully.", [
                'assignment' => $assignment->load(['shipment', 'shipment.consignee']),
                'shipment' => new ShipmentResource($shipment->load(['consignee', 'shipper', 'shipment_items']))
            ]);

        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while reporting exception.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

}

