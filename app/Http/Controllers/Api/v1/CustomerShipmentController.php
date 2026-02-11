<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Account;
use App\Models\MerchantCommission;
use App\Models\MerchantWaybill;
use App\Models\Consignee;
use App\Models\DriverShipmentAssignment;
use App\Models\Shipment;
use App\Models\ShipmentDelivery;
use App\Models\ShipmentFinance;
use App\Models\ShipmentInformation;
use App\Models\ShipmentItem;
use App\Models\Shipper;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Scopes\ConsigneeScope;
use App\Models\ShipperCommission;
use App\Services\AddressService;
use App\Services\NearestDriverService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CustomerShipmentCreatedNotification;
/**
 * @OA\Tag(name="Other", description="Customer Shipment Management")
 */
class CustomerShipmentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/customer-shipments",
     *     summary="Create a new customer shipment",
     *     description="Creates a new customer shipment and submits it for admin review.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", example="Consignee Name", description="Consignee name (required, max 255 characters)"),
     *                 @OA\Property(property="email", type="string", example="consignee@email.com", description="Consignee email (nullable, max 255 characters)"),
     *                 @OA\Property(property="tracking_no", type="string", example="Tracking Number", description="Tracking number (nullable, must be unique)"),
     *                 @OA\Property(property="cellphone", type="string", example="+96899123456", description="Consignee cellphone (nullable, max 20 characters)"),
     *                 @OA\Property(property="alternatePhone", type="string", example="+96899123457", description="Consignee alternate phone (nullable, max 20 characters)"),
     *                 @OA\Property(property="district", type="string", example="District Name", description="Consignee district (nullable, max 255 characters)"),
     *                 @OA\Property(property="country_id", type="integer", example="1", description="Consignee country ID (required, must exist in countries table)"),
     *                 @OA\Property(property="governorate_id", type="integer", example="1", description="Consignee governorate ID (required if country_id is Oman)"),
     *                 @OA\Property(property="state_id", type="integer", example="1", description="Consignee state ID (required, must exist in states table)"),
     *                 @OA\Property(property="zipcode", type="string", example="12345", description="Consignee zipcode (nullable, max 20 characters)"),
     *                 @OA\Property(property="streetAddress", type="string", example="Street Address", description="Consignee street address (required, max 255 characters)"),
     *                 @OA\Property(property="identify", type="string", example="Identification details", description="Consignee identification (nullable, max 255 characters)"),
     *                 @OA\Property(property="taxNumber", type="string", example="Tax Number", description="Consignee tax number (nullable, max 255 characters)"),
     *                 @OA\Property(property="longitude", type="number", format="float", example="72.56789", description="Consignee longitude (nullable)"),
     *                 @OA\Property(property="latitude", type="number", format="float", example="23.45678", description="Consignee latitude (nullable)"),
     *                 @OA\Property(property="location_url", type="string", example="https://www.example.com/location", description="Consignee location URL (nullable)"),
     *                 @OA\Property(property="notes", type="string", example="Shipment notes", description="Shipment notes (nullable)"),
     *                 @OA\Property(property="payment_type", type="string", example="cash", description="Payment type (required)"),
     *                 @OA\Property(property="value", type="number", format="float", example="100.00", description="Shipment value (required)"),
     *                 @OA\Property(property="fee_payer", type="string", example="sender", description="Fee payer (required)"),
     *                 @OA\Property(property="customer_name", type="string", example="Customer Name", description="Customer name (required)"),
     *                 @OA\Property(property="customer_phone", type="string", example="+96899123456", description="Customer phone (required)"),
     *                 @OA\Property(property="merchant_id", type="integer", example="1", description="Merchant ID (required)"),
     *                 @OA\Property(property="unit_id", type="integer", example="1", description="Unit ID (required)"),
     *                 @OA\Property(property="zone_id", type="integer", example="1", description="Zone ID (required)"),
     *                 @OA\Property(property="package_id", type="integer", example="1", description="Package ID (required)"),
     *                 @OA\Property(property="weight", type="number", format="float", example="1.0", description="Weight (required)"),
     *                 @OA\Property(property="height", type="number", format="float", example="10.0", description="Height (required)"),
     *                 @OA\Property(property="width", type="number", format="float", example="10.0", description="Width (required)"),
     *                 @OA\Property(property="length", type="number", format="float", example="10.0", description="Length (required)"),
     *                 @OA\Property(property="status", type="integer", example="0", description="Status (nullable)"),
     *                 @OA\Property(property="item_name", type="array", @OA\Items(type="string"), description="Item names (nullable)"),
     *                 @OA\Property(property="quantity", type="array", @OA\Items(type="integer"), description="Quantities (nullable)"),
     *                 @OA\Property(property="category", type="array", @OA\Items(type="string"), description="Categories (nullable)"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment created successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function store(StoreShipmentRequest $request)
    {

        $request->validated();
        DB::beginTransaction();
        try {
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

            $addressService = new AddressService();

            if (empty($consigneeData['latitude']) || empty($consigneeData['longitude'])) {
                if (!empty($consigneeData['location_url'])) {
                    $parsed = $addressService->parseInputAddress($consigneeData['location_url']);
                    // Override streetAddress and set coordinates
                    $consigneeData['streetAddress'] = $parsed['streetAddress'];
                    $consigneeData['latitude'] = $parsed['latitude'];
                    $consigneeData['longitude'] = $parsed['longitude'];
                }
            }

            // Find or create consignee
            $existingConsignee = Consignee::withoutGlobalScope(ConsigneeScope::class)
                ->where('cellphone', $consigneeData['cellphone'])
                ->where('name', $consigneeData['name'])
                ->where('country_id', $consigneeData['country_id'])
                ->first();

            if ($existingConsignee) {
                $consignee = $existingConsignee;
            } else {
                $consignee = Consignee::create($consigneeData);
            }

            // Get default PE shipper
            $peShipper = Shipper::where('email', 'pe@gmail.com')->first();
            if (!$peShipper) {
                return sendResponse("Error occurred.", [], false, ["PE shipper not found"], 500);
            }

            // Get merchant commission for delivery fee calculation
            $shipper_commission = ShipperCommission::where('shipper_id', $peShipper->id)
                ->where('state_id', $consigneeData['state_id'])
                ->first();

            if (!$shipper_commission) {
                return sendResponse("Error occurred.", [], false, ["Merchant commission is not available for the state"], 500);
            }

            // Prepare shipment data for customer-created shipment
            $shipmentData = [
                "shipper_id" => $peShipper->id, // Default PE shipper
                "consignee_id" => $consignee->id,
                "merchant_id" => $request->merchant_id,
                "tracking_no" => generate_tracking_no(),
                "created_by" => Auth::id(),
                "notes" => $request->notes,
                "payment_type" => $request->payment_type,
                "value" => $request->value,
                "delivery_fee" => $shipper_commission->delivery_fee,
                "total_cod" => $shipper_commission->delivery_fee + $request->value,
                "fee_payer" => $request->fee_payer,
                "customer_name" => $request->customer_name,
                "customer_phone" => $request->customer_phone,
                // No owner set initially - this makes it "pending" for admin
            ];

            $shipmentData['is_walkin'] = 1;

            $shipment = Shipment::create($shipmentData);

            ShipmentInformation::create([
                'shipment_id' => $shipment->id,
                'merchant_id' => $request->merchant_id,
                'unit_id' => $request->unit_id,
                'zone_id' => $request->zone_id,
                'package_id' => $request->package_id,
                'tracking_no' => $shipment->tracking_no,
                'weight' => $request->weight,
                'height' => $request->height,
                'width' => $request->width,
                'length' => $request->length,
                'status' => $request->status ?? 0,
            ]);

            ShipmentDelivery::create([
                'shipment_id' => $shipment->id
            ]);

            ShipmentFinance::create([
                'shipment_tracking_no' => $shipment->tracking_no
            ]);

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

            $status = "CUSTOMER_ORDER_CREATED";
            $historyDataCreated = [
                "description" => "Shipment created by customer " . $consignee->name . " phone " . $consignee->cellphone . " and pending admin review",
                "shipment_id" => $shipment->id,
                "status" => "CUSTOMER_ORDER_CREATED"
            ];
            shipmentHistory($historyDataCreated);

            DB::commit();

            activityLog("customer_shipment_created", "Shipment #{$shipment->tracking_no} created by customer and waiting for admin review");
            $superAdmins = User::role('Super Admin')->get(); 

if ($superAdmins->count() > 0) {
    Notification::send($superAdmins, new CustomerShipmentCreatedNotification($shipment));
}

            return sendResponse("Shipment created successfully and submitted for review.", new ShipmentResource($shipment->load("consignee", "shipment_items", "shipper")));
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Unexpected Error Occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Get all customer-created shipments that are pending admin review
     * This shows shipments created by customers to admin for next action
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 8);
            $query = Shipment::with([
                'shipper:id,name,country_id,state_id,contact,zip_code,address',
                'shipper.country:id,name',
                'shipper.state:id,en_name,ar_name',
                'merchant:id,name',
                'merchant.merchant.country:id,name',
                'merchant.merchant.governorate:id,en_name,ar_name',
                'merchant.merchant.state:id,en_name,ar_name',
                'merchant.merchant.place:id,en_name,ar_name',
                'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone',
                'consignee.country:id,name',
                'consignee.governorate:id,en_name,ar_name',
                'consignee.state:id,en_name,ar_name',
                'consignee.place:id,en_name,ar_name',
                'shipment_information:id,shipment_id,zone_id',
                'shipment_information.zone:id,name',
                'core_status',
                'shipmentHistories',
                'shipment_items',
                'shipment_delivery',
                'transactions',
                'instant_delivery_assignment',
            ])->whereHas('core_status', function ($q) {
                $q->where('name', 'CUSTOMER_ORDER_CREATED');
            });

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_no', 'like', "%{$search}%")
                        ->orWhereHas('consignee', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('cellphone', 'like', "%{$search}%");
                        });
                });
            }
            if ($request->has('from') && $request->has('to')) {
                try {
                    $fromDate = Carbon::createFromFormat('Y-m-d H:i', $request->input('from'));
                    $toDate = Carbon::createFromFormat('Y-m-d H:i', $request->input('to'));
                    if ($fromDate && $toDate) {
                        $query->whereBetween('created_at', [$fromDate, $toDate]);
                    }
                } catch (\Exception $e) {
                    Log::warning("Invalid date format for from/to: {$request->input('from')} / {$request->input('to')}", ['exception' => $e->getMessage()]);
                }
            }

            $shipments = $query->orderByDesc('created_at')->paginate($perPage);

            if ($shipments->isEmpty()) {
                return sendResponse("No pending shipments found.", ['shipments' => ['data' => [], 'links' => []], 'total_pending' => 0], true, [], 200);
            }

            return sendResponse("Pending customer shipments retrieved successfully.", [
                'shipments' => ShipmentResource::collection($shipments)->response()->getData(true),
                'total_pending' => $shipments->total(),
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching pending shipments.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    public function getPendingCustomerShipments(Request $request)
    {
        try {
            $query = Shipment::with([
                'shipper:id,name,country_id,state_id,contact,zip_code,address',
                'shipper.country:id,name',
                'shipper.state:id,en_name,ar_name',
                'merchant:id,name',
                'merchant.merchant.country:id,name',
                'merchant.merchant.governorate:id,en_name,ar_name',
                'merchant.merchant.state:id,en_name,ar_name',
                'merchant.merchant.place:id,en_name,ar_name',
                'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone',
                'consignee.country:id,name',
                'consignee.governorate:id,en_name,ar_name',
                'consignee.state:id,en_name,ar_name',
                'consignee.place:id,en_name,ar_name',
                'shipment_information:id,shipment_id,zone_id',
                'shipment_information.zone:id,name',
                'core_status',
                'shipmentHistories',
                'shipment_items',
                'shipment_delivery',
                'transactions',
                'instant_delivery_assignment',
            ])
                ->whereNull('facility_id') // Shipments without facility are pending admin review
                ->whereNull('facility_type')
                ->whereHas('core_status', function ($q) {
                    $q->where('name', 'CUSTOMER_ORDER_CREATED');
                });

            // Add search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_no', 'like', "%{$search}%")
                        ->orWhereHas('consignee', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('cellphone', 'like', "%{$search}%");
                        });
                });
            }

            // Add date filtering
            if ($request->has('date') && $request->date) {
                try {
                    $selectedDate = Carbon::createFromFormat('Y-m-d', $request->date)->startOfDay();
                    $query->whereDate('created_at', $selectedDate);
                } catch (\Exception $e) {
                    // Invalid date format, ignore the filter
                }
            }

            $shipments = $query->orderByDesc('created_at')->paginate(15);

            if ($shipments->isEmpty()) {
                return sendResponse("No pending shipments found.", [], false, ['no record'], 404);
            }

            return sendResponse("Pending customer shipments retrieved successfully.", [
                'shipments' => new ShipmentResource($shipments),
                'total_pending' => $shipments->total()
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching pending shipments.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/customer-shipments/accept",
     *     summary="Accept or reject a customer shipment",
     *     description="Accepts or rejects a pending customer shipment and updates its status.",
     *     tags={"Other"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="shipment_id", type="integer", example="1", description="Shipment ID (required, must exist in shipments table)"),
     *                 @OA\Property(property="action", type="string", example="accept", description="Action: accept or reject (required)"),
     *                 @OA\Property(property="notes", type="string", example="Admin notes", description="Admin notes (nullable)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment processed successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors or shipment not pending",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function acceptCustomerShipment(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'action' => 'required|in:accept,reject',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $shipment = Shipment::findOrFail($request->shipment_id);

            if ($shipment->facility_id !== null || $shipment->facility_type !== null) {
                return sendResponse("This shipment is not pending admin review.", [], false, [], 422);
            }

            if ($request->action === 'accept') {
                $user = Auth::user();
                $shipment->facility_id = facility("id");
                $shipment->facility_type = facility("type");
                $shipment->save();

                $historyData = [
                    "description" => "Shipment accepted by admin and moved to active workflow. Admin: " . Auth::user()->name,
                    "shipment_id" => $shipment->id,
                    "status" => "ORDER_ACCEPTED"
                ];
                if ($request->notes) {
                    $historyData["description"] .= " - Notes: " . $request->notes;
                }
                shipmentHistory($historyData);

                activityLog("customer_shipment_accepted", "Shipment #{$shipment->tracking_no} accepted by admin and moved to active workflow");

                $message = "Shipment accepted and moved to active workflow.";
            } else {
                // Reject the shipment
                $historyData = [
                    "description" => "Shipment rejected by admin. Admin: " . Auth::user()->name,
                    "shipment_id" => $shipment->id,
                    "status" => "ORDER_REJECTED"
                ];
                if ($request->notes) {
                    $historyData["description"] .= " - Reason: " . $request->notes;
                }
                shipmentHistory($historyData);

                activityLog("customer_shipment_rejected", "Shipment #{$shipment->tracking_no} rejected by admin");

                $message = "Shipment rejected.";
            }

            // Assign nearest drivers to the shipment
            $nearestDriverService = new NearestDriverService();
            $nearestDriverService->assignNearestDrivers($shipment);

            DB::commit();

            return sendResponse($message, new ShipmentResource($shipment->load("consignee", "shipment_items", "shipper", "shipmentHistories")));
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while processing shipment.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Admin assigns a customer-created shipment to a driver
     */
    public function assignCustomerShipment(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
            'driver_id' => 'required'
        ]);

        DB::beginTransaction();
        try {
            $shipment = Shipment::where('tracking_no', $request->tracking_no)->first();
            $assignment = DriverShipmentAssignment::where('status', 'ASSIGNED')->where('shipment_id', $shipment->id)->first();

            if (!$shipment) {
                return sendResponse("Shipment not found.", [], false, [], 422);
            }

            if (!$assignment) {
                return sendResponse("This shipment is not accepted by a driver.", [], false, [], 422);
            }

            $assignment->shipment_tracking_no = $shipment->tracking_no;
            $assignment->driver_id = $request->driver_id;
            $assignment->assigned_by = Auth::id();
            $assignment->assigned_at = operation_now();
            $assignment->status = "DISPATCH";
            $assignment->save();

            $historyData = [
                "description" => "Shipment assigned to driver. Admin: " . Auth::user()->name . " to: " . $assignment->driver->name,
                "shipment_id" => $shipment->id,
                "status" => "DISPATCH"
            ];

            shipmentHistory($historyData);

            activityLog("customer_shipment_assigned", "Shipment #{$shipment->tracking_no} assigned to driver");

            $message = "Shipment assigned to driver.";

            DB::commit();

            return sendResponse($message, new ShipmentResource($shipment->load("consignee", "shipment_items", "shipper", "shipmentHistories")));
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while processing shipment.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }
}
