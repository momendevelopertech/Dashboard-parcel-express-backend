<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\DeliveryExceptionEnum;
use App\Enums\ShipmentStatusEnum;
use Exception;


use Carbon\Carbon;
use App\Models\Hub;
use App\Models\User;
use App\Models\State;
use App\Models\Wallet;
use App\Models\Account;
use App\Models\Address;
use App\Models\Setting;
use App\Models\Shipper;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Models\Consignee;
use App\Models\DriverBonus;
use App\Models\Transaction;
use App\Models\ShipmentItem;
use App\Services\FcmService;
use App\Traits\CustomeTrait;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use App\Models\DriverRunsheet;
use App\Services\FeeAllocator;
use App\Exports\ShipmentExport;
use App\Imports\ShipmentImport;
use App\Models\MerchantWaybill;
use App\Models\ShipmentFinance;
use App\Models\ShipmentHistory;
use App\Models\ShipmentDelivery;
use App\Services\AddressService;
use App\Models\ShipperCommission;
use App\Models\CommissionTemplate;
use App\Models\MerchantCommission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\ShipmentInformation;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Scopes\ShipmentScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Scopes\ConsigneeScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use App\Exports\ShipmentHistoryExport;
use App\Imports\ShipmentImportPreview;
use App\Models\DriverRunsheetShipment;
use App\Models\MerchantPickupShipment;
use Illuminate\Support\Facades\Schema;
use App\Models\ShipmentAddressRevision;
use App\Services\ShipmentPickupService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\ShipmentResource;
use App\Models\DriverShipmentAssignment;
use Illuminate\Database\Eloquent\Builder;
use App\Services\AddressUpdateLinkService;
use App\Http\Requests\StoreShipmentRequest;
use App\Services\ShipmentValidationService;
use App\Http\Requests\UpdateShipmentRequest;
use App\Exports\ShipmentImportTemplateExport;
use App\Http\Resources\ShipmentTrackResource;
use Illuminate\Validation\ValidationException;
use App\Notifications\ConsigneeOFDNotification;
use App\Http\Requests\RescheduleShipmentRequest;
use App\Notifications\ConsigneeOFDQRNotification;
use App\Notifications\ShipmentCreatedNotification;
use App\Notifications\OutsourcedShipmentCreatedNotification;
use App\Http\Resources\UncreatedShipmentResource;
use App\Models\Scopes\ExcludeReturnShipmentsScope;
use App\Services\AdminCounterService;

class ShipmentController extends Controller
{
    use CustomeTrait;
    public $adminCounterService;
    public function __construct(AdminCounterService $adminCounterService)
    {
        $this->adminCounterService = $adminCounterService;
    }
    private function calcDriverReceivableForShipment(Shipment $shipment): float
    {
        // Use centralized calculation service for consistency
        return app(\App\Services\CalculationLogicService::class)
            ->getDriverCollectibleAmount($shipment);
    }

    private function makeAddressSignature(array $data): ?string
    {
        $street = trim(mb_strtolower($data['streetAddress'] ?? ''));
        $zip = trim(mb_strtolower($data['zipcode'] ?? ''));
        $cityId = $data['city_id'] ?? null;

        $lat = isset($data['latitude']) && is_numeric($data['latitude']) ? round((float) $data['latitude'], 5) : null;
        $lng = isset($data['longitude']) && is_numeric($data['longitude']) ? round((float) $data['longitude'], 5) : null;

        if (!$street && !$zip && !$cityId && !$lat && !$lng) {
            return null;
        }

        return hash('sha256', json_encode([
            'street' => $street,
            'zip' => $zip,
            'city' => (string) $cityId,
            'lat' => (string) $lat,
            'lng' => (string) $lng,
        ]));
    }

    private function createConsigneeAddress(int $consigneeId, array $addr): Address
    {
        return Address::create([
            'consignee_id' => $consigneeId,
            'country_id' => $addr['country_id'] ?? null,
            'governorate_id' => $addr['governorate_id'] ?? null,
            'state_id' => $addr['state_id'] ?? null,
            'place_id' => $addr['place_id'] ?? null,
            'city_id' => $addr['city_id'] ?? null,
            'zipcode' => $addr['zipcode'] ?? null,
            'streetAddress' => $addr['streetAddress'] ?? null,
            'longitude' => $addr['longitude'] ?? null,
            'latitude' => $addr['latitude'] ?? null,
            'location_url' => $addr['location_url'] ?? null,
            'label' => $addr['label'] ?? null,
            'approved' => false,
            'is_active' => true,
        ]);
    }
    /**
     * @OA\Tag(
     *     name="OMS",
     *     description="Shipment Management System API Endpoints"
     * )
     */

    /**
     * List all shipments with filtering
     *
     * @OA\Get(
     *   path="/shipments",
     *   tags={"Shipments"},
     *   summary="List all shipments with filtering",
     *   description="Get paginated list of shipments with optional filters and relationships",
     *   operationId="shipmentIndexV1",
     *   security={{"sanctum": {}}},
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Tracking number filter",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="status",
     *     in="query",
     *     description="Shipment status filter",
     *     required=false,
     *     @OA\Schema(type="string", enum={"in_exception", "OFD", "DELIVERED"})
     *   ),
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number",
     *     required=false,
     *     @OA\Schema(type="integer", default=1)
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipments retrieved successfully."),
     *       @OA\Property(property="data", type="object",
     *           @OA\Property(property="shipments", type="array", @OA\Items(ref="#/components/schemas/Shipment"))
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $hasOutsourcedParam = request()->has('is_outsourced');
        $isOutsourced = request()->boolean('is_outsourced');

        $shipmentsQuery = has_role("Merchant")
            ? Shipment::query()->where('merchant_id', Auth::id())
            : Shipment::query()->excludePickupUnassigned();

        // /shipments should only list outbound shipments that already have a waybill.
        $shipmentsQuery
            ->where(function ($q) {
                $q->where('is_return', false)->orWhereNull('is_return');
            })
            ->whereNotNull('tracking_no')
            ->whereRaw("TRIM(tracking_no) <> ''");

        if ($hasOutsourcedParam) {
            $shipmentsQuery->where('is_outsourced', $isOutsourced);
        }

        $shipmentsQuery->applyFilters(request());

        $shipmentsQuery->filterByFromCurrentFinal(request())->with([
            'shipper:id,name,country_id,state_id,contact,zip_code,address',
            'shipper.country:id,name',
            'shipper.state:id,en_name,ar_name',
            'merchant:id,name',
            'merchant.merchant.country:id,name',
            'merchant.merchant.governorate:id,en_name,ar_name',
            'merchant.merchant.state:id,en_name,ar_name',
            'merchant.merchant.place:id,en_name,ar_name',

            'consignee:id,name,cellphone,alternatePhone,country_key_cellphone,country_key_alternatePhone,address_confirmed,current_address_id',

            'deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,zipcode,streetAddress,latitude,longitude,location_url,approved,approved_at',
            'deliveryAddress.country:id,name',
            'deliveryAddress.governorate:id,en_name,ar_name',
            'deliveryAddress.state:id,en_name,ar_name',
            'deliveryAddress.place:id,en_name,ar_name',
            'deliveryAddress.city:id,name',

            'shipment_information:id,shipment_id,zone_id',
            'shipment_information.zone:id,name',
            'core_status',
            'shipmentHistories',
            'shipment_items',
            'shipment_delivery',
            'transactions',
            'destinationOwner',
            'finalOwner',
            'currentOwner',
            'fromOwner',
        ]);

        $shipments = $shipmentsQuery
            ->orderByDesc('id')
            ->paginate($perPage);
        $shipments->getCollection()->loadMissing('deliveryAddress');

        $shipments->getCollection()->each(function ($shipment) {
            $shipment->append('base_delivery_fee');
        });
        if ($shipments->isEmpty()) {
            return sendResponse("No Record.", [], false, ['no record']);
        }

        return sendResponse("Shipments retrieved successfully.", [
            'shipments' => new ShipmentResource($shipments),
        ]);
    }

    /**
     * List all shipment statuses
     *
     * @OA\Get(
     *   path="/shipments/statuses",
     *   tags={"Shipments"},
     *   summary="List all shipment statuses",
     *   description="Get list of all available shipment statuses",
     *   operationId="shipmentStatusesV1",
     *   security={{"sanctum": {}}},
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Statuses retrieved successfully."),
     *       @OA\Property(property="data", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function statuses()
    {
        return sendResponse("Statuses retrieved successfully.", ShipmentStatusEnum::getAll());
    }
    /**
     * List future shipments
     *
     * @OA\Get(
     *   path="/shipments/future-shipments",
     *   tags={"Shipments"},
     *   summary="List future shipments",
     *   description="Get list of shipments scheduled for future delivery",
     *   operationId="shipmentFutureV1",
     *   security={{"sanctum": {}}},
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipments retrieved successfully."),
     *       @OA\Property(property="data", type="object",
     *           @OA\Property(property="shipments", type="array", @OA\Items(ref="#/components/schemas/Shipment"))
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function futureShipments()
    {
        $shipments = $this->getFutureShipments(DeliveryExceptionEnum::FUTURE_DELIVERY);

        if ($shipments->isEmpty()) {
            return sendResponse("No Record.", [], false, ['no record']);
        }

        return sendResponse("Shipments retrieved successfully.", [
            'shipments' => new ShipmentResource($shipments),
        ]);
    }

    // public function index()
    // {
    //     $trackingNo = request()->query('query');
    //     $status = request()->query('status');
    //     $today = request()->query('today');
    //     $date = request()->query('date');
    //     $facilityId = request()->query('facility_id');
    //     $facilityType = request()->query('facility_type');
    //     $perPage = request()->query('per_page', 8);
    //     $shipmentsQuery = Shipment::query()->excludePickupUnassigned();
    //     $isOutParamExists = request()->has('is_outsourced');
    //     if ($isOutParamExists) {
    //         $shipmentsQuery->where('is_outsourced', (int) request('is_outsourced'));
    //     } else {

    //         $shipmentsQuery->where('is_outsourced', 0);
    //     }

    //     if ($trackingNo) {
    //         $shipmentsQuery->where('tracking_no', "like", "%" . $trackingNo . "%");
    //     }
    //     if ($status) {
    //         switch ($status) {
    //             case 'in_exception':
    //                 $shipmentsQuery->where('in_exception', true);
    //                 break;
    //             case 'OFD':
    //                 $shipmentsQuery->where('status', 'OFD');
    //                 break;
    //             case 'DELIVERED':
    //                 $shipmentsQuery->where('status', 'DELIVERED');
    //                 break;
    //             default:
    //                 $shipmentsQuery->where('status', $status);
    //                 break;
    //         }
    //     }

    //     if ($today === "true") {
    //         $shipmentsQuery->whereDate('created_at', Carbon::today());
    //     } elseif ($date) {
    //         try {
    //             $selectedDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
    //             if ($status === 'DELIVERED') {
    //                 // For delivered shipments, filter by delivery date using shipment history
    //                 $shipmentsQuery->whereHas('shipmentHistories', function ($q) use ($selectedDate) {
    //                     $q->where('name', 'DELIVERED')
    //                         ->whereDate('time', $selectedDate);
    //                 });
    //             } else {
    //                 // For other statuses, filter by creation date
    //                 $shipmentsQuery->whereDate('created_at', $selectedDate);
    //             }
    //         } catch (\Exception $e) {
    //             // Invalid date format, ignore the filter
    //         }
    //     }

    //     if (has_role("Merchant")) {

    //         $shipmentsQuery->where('merchant_id', Auth::id());
    //     }

    //     // Filter by facility if provided
    //     if ($facilityId && $facilityType) {
    //         $shipmentsQuery->where('owner_id', $facilityId)
    //             ->where('owner_type', $facilityType);
    //     }

    //     // Exclude pending customer shipments (those without    owner assigned)
    //     // These should only be visible in CustomerCreatedShipments component until admin accepts them

    //     // $shipmentsQuery->where(function ($query) {
    //     //     $query->whereNotNull('facility_id')
    //     //         ->orWhereNotNull('facility_type');
    //     // });

    //     $shipmentsQuery->with([
    //         'shipper:id,name,country_id,state_id,contact,zip_code,address',
    //         'shipper.country:id,name',
    //         'shipper.state:id,en_name,ar_name',

    //         'merchant:id,name',
    //         'merchant.merchant.country:id,name',
    //         'merchant.merchant.governorate:id,en_name,ar_name',
    //         'merchant.merchant.state:id,en_name,ar_name',
    //         'merchant.merchant.place:id,en_name,ar_name',

    //         'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone,country_key_cellphone,country_key_alternatePhone,address_confirmed',
    //         'consignee.country:id,name',
    //         'consignee.governorate:id,en_name,ar_name',
    //         'consignee.state:id,en_name,ar_name',
    //         'consignee.place:id,en_name,ar_name',
    //         'consignee.old_address',

    //         'shipment_information:id,shipment_id,zone_id',
    //         'shipment_information.zone:id,name',

    //         'core_status',
    //         'shipmentHistories',
    //         'shipment_items',
    //         'shipment_delivery',
    //         'transactions',
    //         'governorate',
    //         'state',
    //         'place',
    //         'city',

    //     ]);

    //     $shipments = $shipmentsQuery->orderByDesc('id')->paginate($perPage);
    //     if ($shipments->isEmpty()) {
    //         return sendResponse("No Record.", [], false, ['no record']);
    //     }
    //     return sendResponse("Shipments retrieved successfully.", [
    //         'shipments' => new ShipmentResource($shipments),
    //     ]);
    // }

    /**
     * List pickup unassigned shipments
     *
     * @OA\Get(
     *   path="/shipments/pickup-unassigned",
     *   tags={"Shipments"},
     *   summary="List pickup unassigned shipments",
     *   description="Get list of shipments that are not assigned for pickup",
     *   operationId="shipmentPickupUnassignedV1",
     *   security={{"sanctum": {}}},
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipments retrieved successfully."),
     *       @OA\Property(property="data", type="object",
     *           @OA\Property(property="shipments", type="array", @OA\Items(ref="#/components/schemas/Shipment"))
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function pickupUnassignedIndex()
    {
        $perPage = request()->query('per_page', 8);

        $shipmentsQuery = Shipment::query()
            ->pickupUnassigned()
            ->applyFilters(request());

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
            'governorate',
            'state',
            'place',
            'city',
            'proofs',
        ]);

        $shipments = $shipmentsQuery->orderByDesc('id')->paginate($perPage);
        if ($shipments->isEmpty()) {
            return sendResponse("No Record.", [], false, ['no record']);
        }

        return sendResponse("Shipments retrieved successfully.", [
            'shipments' => new ShipmentResource($shipments),
        ]);
    }

    /**
     * Display unregistered shipments with registration status check
     *
     * Returns unregistered shipments (tracking_no is NULL, pre_id is NOT NULL)
     * and checks if a registered shipment exists with tracking_no matching the pre_id
     */
    public function unregisteredShipments()
    {
        $perPage = request()->query('per_page', 15);
        $registeredFilter = request()->query('registered'); // Can be 'true', 'false', or null

        // Base query: shipments with pre_id and dashboard source
        $shipmentsQuery = Shipment::query()->whereNotNull('pre_id')->where('created_source', 'dashboard');

        // If filtering by registration status
        if ($registeredFilter !== null) {
            $isRegisteredFilter = filter_var($registeredFilter, FILTER_VALIDATE_BOOLEAN);

            if ($isRegisteredFilter) {
                // Show shipments that have BOTH pre_id AND tracking_no (registered)
                $shipmentsQuery->whereNotNull('tracking_no');
            } else {
                // Show shipments that have pre_id but NO tracking_no (unregistered)
                $shipmentsQuery->whereNull('tracking_no');
            }
        } else {
            // No filter: show only unregistered (pre_id exists, tracking_no is null)
            $shipmentsQuery->whereNull('tracking_no');
        }

        // Apply any additional filters
        $shipmentsQuery->applyFilters(request());

        // Load relationships
        $shipmentsQuery->with([
            'shipper:id,name,country_id',
            'merchant:id,name',
        ]);

        $shipments = $shipmentsQuery
            ->orderByDesc('id')
            ->paginate($perPage);

        if ($shipments->isEmpty()) {
            return sendResponse("No shipments found.", [], false, ['no record']);
        }

        // Transform the collection to add registration status
        $transformedShipments = $shipments->getCollection()->map(function ($shipment) {
            // Build the response structure - get only the model attributes without relationships
            $shipmentData = $shipment->toArray();

            // Remove geojson fields from shipment data
            unset($shipmentData['coordinates_geojson'], $shipmentData['polygon_geojson']);

            // Determine if this shipment is registered (has tracking_no)
            if ($shipment->tracking_no) {
                $shipmentData['is_registered'] = true;
                // The shipment itself is the registered version
                $registeredShipmentData = $shipment->getAttributes();
                unset($registeredShipmentData['coordinates_geojson'], $registeredShipmentData['polygon_geojson']);
                $shipmentData['shipment'] = $registeredShipmentData;
            } else {
                $shipmentData['is_registered'] = false;
            }

            return $shipmentData;
        });

        // Replace the collection with transformed data
        $shipments->setCollection(collect($transformedShipments));

        return sendResponse("Shipments retrieved successfully.", [
            'shipments' => $shipments,
        ]);
    }

    /**
     * Get all shipments with cursor pagination
     *
     * @OA\Get(
     *   path="/shipments/all",
     *   tags={"Shipments"},
     *   summary="Get all shipments with cursor pagination",
     *   description="Retrieve all shipments with cursor pagination (500 items per page)",
     *   operationId="shipmentAllV1",
     *   security={
     *     {"sanctum": {}}
     *   },
     *   @OA\Parameter(
     *     name="cursor",
     *     in="query",
     *     description="Cursor for pagination",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/Shipment")
     *       ),
     *       @OA\Property(property="links", type="object", description="Pagination links"),
     *       @OA\Property(property="meta", type="object", description="Pagination metadata")
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function all()
    {
        $shipments = Shipment::byOwner()
            ->select(['id', 'tracking_no', 'consignee_id', 'shipper_id', 'merchant_id', 'total_cod']) // Select necessary columns
            ->cursorPaginate(500);

        return response()->json($shipments);
    }

    /**
     * List shipments with unresolved exceptions
     *
     * @OA\Get(
     *   path="/shipments/not_deliver",
     *   tags={"Shipments"},
     *   summary="List shipments with unresolved exceptions",
     *   description="Retrieve shipments that have unresolved exceptions",
     *   operationId="shipmentNotDeliverV1",
     *   security={
     *     {"sanctum": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipments not delivered"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/Shipment")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="No shipments found",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="No record found."),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function not_deliver()
    {
        $perPage = (int) request()->query('per_page', 10);
        $search = trim((string) request()->query('query', ''));
        $statusFilter = request()->query('status');
        $driverId = request()->query('driver_id');
        $companyId = request()->query('company_id');
        $warehouseId = request()->query('warehouse_id');
        $exception = request()->query('exception_type');
        $workspaceKey = request()->query('workspace_key');
        $workspaceType = request()->query('workspace_type');


        $from = request()->query('from');
        $to = request()->query('to');
        $start = $from ? Carbon::parse($from) : null;
        $end = $to ? Carbon::parse($to) : null;
        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end, $start];
        }
        $workspaceId = null;

        if ($workspaceKey) {
            try {
                $workspaceId = Crypt::decryptString($workspaceKey);
            } catch (\Throwable $e) {
                $workspaceId = $workspaceKey;
            }
        }

        $rescheduleTypes = array_map('strtoupper', ['RESCHEDULE', 'RESCHEDULED', 'RE_SCHEDULE', 'RE-SCHEDULE']);
        $closeTypes = array_map('strtoupper', ['CLOSED', 'NDR_CLOSED', DeliveryExceptionEnum::CANCELLED, 'CANCELED', 'RTO', 'RETURNED_TO_ORIGIN', 'RETURN_TO_ORIGIN']);

        $shipments = Shipment::query()
            ->select('shipments.*')
            // ->byOwner()
            ->where('in_exception', true)
            ->whereHas('core_exception')
            ->with([
                'merchant:id,name',
                'consignee' => function ($q) {
                    $q->select([
                        'id',
                        'name',
                        DB::raw('cellphone as phone'),
                        DB::raw('streetAddress as address'),
                    ]);
                },
                'driver:id,name',

                'current_driver:id,user_id,company_id,company_name',
                'current_driver.company:id,name',

                'core_exception' => function ($q) {
                    $q->select('id', 'shipment_id', 'name', 'type', 'description', 'proof', 'time', 'updated_at');
                },

                'shipmentHistories:id,shipment_id,name,type,time,updated_at',
            ])->withCount([
                    'shipmentHistories as no_answer_count' => function ($h) use ($start, $end) {
                        $h->where('status', 'DELIVERY_EXCEPTION')
                            ->whereRaw('UPPER(type) = ?', [DeliveryExceptionEnum::NO_ANSWER]);
                        if ($start && $end) {
                            $h->whereBetween(DB::raw('COALESCE(time, updated_at)'), [$start, $end]);
                        } elseif ($start) {
                            $h->where(DB::raw('COALESCE(time, updated_at)'), '>=', $start);
                        } elseif ($end) {
                            $h->where(DB::raw('COALESCE(time, updated_at)'), '<=', $end);
                        }
                    }
                ])

            ->when($search !== '', function ($qb) use ($search) {
                $qb->where(function ($inner) use ($search) {
                    $inner->where('tracking_no', 'like', "%{$search}%")
                        ->orWhere('streetAddress', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhereHas('merchant', fn($c) => $c->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('consignee', function ($c) use ($search) {
                            $c->where('name', 'like', "%{$search}%")
                                ->orWhere('cellphone', 'like', "%{$search}%")
                                ->orWhere('streetAddress', 'like', "%{$search}%");
                        });
                });
            })
            ->when($workspaceId, function ($qb) use ($workspaceId, $workspaceType) {
                $qb->where(function ($wq) use ($workspaceId, $workspaceType) {
                    if (!empty($workspaceType)) {
                        // Filter with both ID and type
                        $wq->where(function ($x) use ($workspaceId, $workspaceType) {
                            $x->where('owner_id', $workspaceId)
                                ->where('owner_type', $workspaceType);
                        })->orWhere(function ($x) use ($workspaceId, $workspaceType) {
                            $x->where('destination_owner_id', $workspaceId)
                                ->where('destination_owner_type', $workspaceType);
                        });
                    } else {
                        // Filter by ID only - check both owner and destination
                        $wq->where(function ($x) use ($workspaceId) {
                            $x->where('owner_id', $workspaceId)
                                ->whereNotNull('owner_type');
                        })->orWhere(function ($x) use ($workspaceId) {
                            $x->where('destination_owner_id', $workspaceId)
                                ->whereNotNull('destination_owner_type');
                        });
                    }
                });
            })

            ->when($driverId, function ($qb) use ($driverId) {
                $qb->where(function ($q) use ($driverId) {
                    $q->where('driver_id', $driverId)
                        ->orWhere('current_driver_id', $driverId) // لو العمود موجود
                        ->orWhereHas('current_driver', function ($d) use ($driverId) {
                            $d->where('id', $driverId)
                                ->orWhere('user_id', $driverId); // لو الـ select بيختار user_id
                        });
                });
            })

            ->when($warehouseId, function ($qb) use ($warehouseId) {
                $qb->where(function ($wq) use ($warehouseId) {
                    $wq->where(function ($x) use ($warehouseId) {
                        $x->where('destination_owner_id', $warehouseId)->whereNotNull('destination_owner_type');
                    })->orWhere(function ($x) use ($warehouseId) {
                        $x->where('owner_id', $warehouseId)->whereNotNull('owner_type');
                    });
                });
            })
            ->when($exception, fn($qb) => $qb->whereHas('core_exception', fn($e) => $e->where('type', $exception)))
            ->when($statusFilter === 'Rescheduled', function ($qb) use ($rescheduleTypes) {
                $qb->whereHas('shipmentHistories', function ($h) use ($rescheduleTypes) {
                    $h->whereIn(DB::raw('UPPER(type)'), $rescheduleTypes)
                        ->orWhereIn(DB::raw('UPPER(name)'), $rescheduleTypes);
                });
            })
            ->when($statusFilter === 'Closed', function ($qb) use ($closeTypes) {
                $qb->where(function ($qq) use ($closeTypes) {
                    $qq->where('in_exception', 0)
                        ->orWhereHas('shipmentHistories', function ($h) use ($closeTypes) {
                            $h->whereIn(DB::raw('UPPER(type)'), $closeTypes)
                                ->orWhereIn(DB::raw('UPPER(name)'), $closeTypes);
                        });
                });
            })
            ->when($statusFilter === 'Pending', function ($qb) use ($rescheduleTypes, $closeTypes) {
                $qb->where('in_exception', 1)
                    ->whereDoesntHave('shipmentHistories', function ($h) use ($closeTypes) {
                        $h->whereIn(DB::raw('UPPER(type)'), $closeTypes)
                            ->orWhereIn(DB::raw('UPPER(name)'), $closeTypes);
                    })
                    ->whereDoesntHave('shipmentHistories', function ($h) use ($rescheduleTypes) {
                        $h->whereIn(DB::raw('UPPER(type)'), $rescheduleTypes)
                            ->orWhereIn(DB::raw('UPPER(name)'), $rescheduleTypes);
                    });
            })
            ->when($statusFilter === 'RTO', function ($qb) {
                $qb->whereHas('shipmentHistories', function ($h) {
                    $h->whereIn(DB::raw('UPPER(type)'), ['RTO', 'RETURN_TO_ORIGIN', 'RETURNED_TO_ORIGIN'])
                        ->orWhereIn(DB::raw('UPPER(name)'), ['RTO', 'RETURN_TO_ORIGIN', 'RETURNED_TO_ORIGIN']);
                });
            })
            ->when($statusFilter === 'Delivered', function ($qb) {
                $qb->whereHas('shipmentHistories', function ($h) {
                    $h->whereIn(DB::raw('UPPER(type)'), ['DELIVERED', 'DELIVER'])
                        ->orWhereIn(DB::raw('UPPER(name)'), ['DELIVERED', 'DELIVER']);
                });
            })
            ->when($start && $end, fn($qb) => $qb->whereHas('core_exception', fn($e) => $e->whereBetween(DB::raw('COALESCE(time, updated_at)'), [$start, $end])))
            ->when($start && !$end, fn($qb) => $qb->whereHas('core_exception', fn($e) => $e->where(DB::raw('COALESCE(time, updated_at)'), '>=', $start)))
            ->when(!$start && $end, fn($qb) => $qb->whereHas('core_exception', fn($e) => $e->where(DB::raw('COALESCE(time, updated_at)'), '<=', $end)))
            ->orderByDesc('id')

            ->paginate($perPage)
            ->appends(request()->query());

        return sendResponse('Shipments not delivered', ShipmentResource::collection($shipments));
    }





    /**
     * Resolve shipment exception status
     *
     * @OA\Post(
     *   path="/shipments/mark_resolved",
     *   tags={"Shipments"},
     *   summary="Resolve shipment exception",
     *   description="Mark an shipment's exception status as resolved",
     *   operationId="shipmentMarkResolvedV1",
     *   security={
     *     {"sanctum": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Shipment ID to resolve exception",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", example=1, description="Shipment ID")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Exception status updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Status updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         ref="#/components/schemas/Shipment"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Database error",
     *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *   )
     * )
     */
    public function mark_resolved(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:shipments,id',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $shipment = Shipment::lockForUpdate()->findOrFail($request->id);

                if ($shipment->in_exception === false) {
                    shipmentHistory([
                        'shipment_id' => $shipment->id,
                        'status' => 'EXCEPTION_RESOLVED',
                        'description' => 'Exception already resolved.',
                    ]);

                    return sendResponse("Shipment already resolved.", new ShipmentResource($shipment));
                }

                $shipment->in_exception = false;
                $shipment->save();

                // History بسيط بدون أي Arrays/JSON
                shipmentHistory([
                    'shipment_id' => $shipment->id,
                    'status' => 'EXCEPTION_RESOLVED',
                    'description' => 'Exception resolved.',
                ]);

                return sendResponse("Status updated successfully.", new ShipmentResource($shipment));
            });
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating shipment.", [], false, [$e->getMessage()], 422);
        } catch (\Throwable $e) {
            return sendResponse("Unexpected error.", [], false, [$e->getMessage()], 500);
        }
    }


    public function reschedule(RescheduleShipmentRequest $request)
    {
        $user = auth()->user();
        $shipment = Shipment::findOrFail($request->id);

        if (in_array(strtoupper($shipment->status), ['CLOSED', 'NDR_CLOSED'])) {
            return sendError('Cannot reschedule a closed shipment.', [], 422);
        }

        DB::beginTransaction();
        try {
            $rescheduleDateTime = now();
            if ($request->filled('reschedule_date')) {
                $rescheduleDateTime = \Carbon\Carbon::parse(
                    $request->reschedule_date . ' ' . ($request->reschedule_time ?? '00:00')
                );
            }

            $shipment->update([
                'status' => 'RESCHEDULED',
                'in_exception' => false,
                'rescheduled_date' => $rescheduleDateTime,
                'rescheduled_at' => now(),
                'rescheduled_by' => $user->id,
                'reschedule_notes' => $request->notes,
            ]);

            // Add history
            ShipmentHistory::create([
                'shipment_id' => $shipment->id,
                'name' => 'RESCHEDULED',
                'type' => 'RESCHEDULE',
                'description' => 'Shipment rescheduled by ' . $user->name,
                'time' => now(),
                'operatorId' => $user->id,
                'operatorInfo' => $user->name . ' - ' . $user->getRoleNames()->first(),
                'originActionName' => 'Reschedule',
            ]);

            DB::commit();
            return sendResponse('Shipment rescheduled successfully.', $shipment);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendError('Failed to reschedule shipment.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get real-time shipment data for non-delivered shipments
     *
     * @OA\Get(
     *   path="/shipments/realtime-query",
     *   tags={"OMS"},
     *   summary="Get real-time shipment data for non-delivered shipments",
     *   description="Retrieve all non-delivered shipments with full relationships for real-time tracking",
     *   operationId="getRealtimeShipmentData",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipments retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="tracking_no", type="string", example="TRK123"),
     *           @OA\Property(property="status", type="string", example="IN_TRANSIT"),
     *           @OA\Property(
     *             property="shipper",
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="country", type="object"),
     *             @OA\Property(property="state", type="object")
     *           ),
     *           @OA\Property(
     *             property="consignee",
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="country", type="object"),
     *             @OA\Property(property="state", type="object")
     *           ),
     *           @OA\Property(
     *             property="shipment_information",
     *             type="object",
     *             @OA\Property(property="zone", type="object")
     *           ),
     *           @OA\Property(
     *             property="shipmentHistories",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *               @OA\Property(property="id", type="integer"),
     *               @OA\Property(property="name", type="string"),
     *               @OA\Property(property="time", type="string", format="date-time")
     *             )
     *           ),
     *           @OA\Property(
     *             property="shipment_amounts",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *               @OA\Property(property="amount", type="number")
     *             )
     *           ),
     *           @OA\Property(
     *             property="shipment_items",
     *             type="array",
     *             @OA\Items(
     *               type="object",
     *               @OA\Property(property="id", type="integer"),
     *               @OA\Property(property="name", type="string"),
     *               @OA\Property(property="quantity", type="integer")
     *             )
     *           ),
     *           @OA\Property(
     *             property="merchant",
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string")
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unauthorized"),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function indexAllForRealtimeQuery()
    {

        $shipments = Shipment::with([
            'shipper.country',
            'shipper.state',
            'consignee.country',
            'consignee.state',
            'shipment_information.zone',
            'shipmentHistories',
            'shipment_amounts',
            'shipment_items',
            'merchant'
        ])
            ->where('status', '!=', 'DELIVERED')
            // Exclude pending customer shipments from realtime tracking
            ->where(function ($query) {
                $query->whereNotNull('owner_id')
                    ->orWhereNotNull('owner_type');
            })
            ->orderBy('id', 'desc')
            ->get();

        return sendResponse("Shipments retrieved successfully.", new ShipmentResource($shipments), true, []);
    }

    /**
     * Create a new shipment
     *
     * @OA\Post(
     *   path="/shipments/store",
     *   tags={"OMS"},
     *   summary="Create a new shipment with full transaction handling",
     *   description="Create a new shipment with consignee information, financial details, and items",
     *   operationId="createShipment",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Shipment creation data",
     *     @OA\JsonContent(
     *       required={
     *         "shipper_id", "merchant_id", "value", "delivery_fee", "unit_id", "zone_id", "package_id",
     *         "weight", "height", "width", "length", "name", "cellphone", "country_id", "state_id"
     *       },
     *       @OA\Property(
     *         property="shipper_id",
     *         example=1,
     *         type="integer",
     *         description="ID of the shipper"
     *       ),
     *       @OA\Property(
     *         property="merchant_id",
     *         example=1,
     *         type="integer",
     *         description="ID of the merchant"
     *       ),
     *       @OA\Property(
     *         property="value",
     *         example=1,
     *         type="number",
     *         description="Shipment value"
     *       ),
     *       @OA\Property(
     *         property="delivery_fee",
     *         example=1,
     *         type="number",
     *         description="Delivery fee amount"
     *       ),
     *       @OA\Property(
     *         property="unit_id",
     *         example=1,
     *         type="integer",
     *         description="ID of the unit"
     *       ),
     *       @OA\Property(
     *         property="zone_id",
     *         example=1,
     *         type="integer",
     *         description="ID of the zone"
     *       ),
     *       @OA\Property(
     *         property="package_id",
     *         example=1,
     *         type="integer",
     *         description="ID of the package"
     *       ),
     *       @OA\Property(
     *         property="weight",
     *         example=1,
     *         type="number",
     *         description="Weight of the shipment"
     *       ),
     *       @OA\Property(
     *         property="height",
     *         example=1,
     *         type="number",
     *         description="Height of the shipment"
     *       ),
     *       @OA\Property(
     *         property="width",
     *         example=1,
     *         type="number",
     *         description="Width of the shipment"
     *       ),
     *       @OA\Property(
     *         property="length",
     *         example=1,
     *         type="number",
     *         description="Length of the shipment"
     *       ),
     *       @OA\Property(
     *         property="name",
     *         type="string",
     *         description="Consignee name"
     *       ),
     *       @OA\Property(
     *         property="cellphone",
     *         type="string",
     *         description="Consignee cellphone number (without country code)"
     *       ),
     *       @OA\Property(
     *         property="country_key_cellphone",
     *         type="string",
     *         description="Country code for cellphone (e.g. +968)"
     *       ),
     *       @OA\Property(
     *         property="alternatePhone",
     *         type="string",
     *         description="Consignee alternate phone number (without country code)"
     *       ),
     *       @OA\Property(
     *         property="country_key_alternatePhone",
     *         type="string",
     *         description="Country code for alternate phone (e.g. +968)"
     *       ),
     *       @OA\Property(
     *         property="country_id",
     *         example=1,
     *         type="integer",
     *         description="Consignee country ID"
     *       ),
     *       @OA\Property(
     *         property="streetAddress",
     *         example="Muscat - Street Addrees 24",
     *         type="string",
     *         description="Consignee street address"
     *       ),
     *       @OA\Property(
     *         property="payment_type",
     *         example="COD",
     *         type="string",
     *         description="Consignee country ID"
     *       ),
     *       @OA\Property(
     *         property="state_id",
     *         example=1,
     *         type="integer",
     *         description="Consignee state ID"
     *       ),
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Shipment created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipment created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="tracking_no", type="string", example="TRK123"),
     *         @OA\Property(property="value", type="number", example=100.50),
     *         @OA\Property(property="delivery_fee", type="number", example=50.00),
     *         @OA\Property(property="amount", type="number", example=150.50),
     *         @OA\Property(
     *           property="consignee",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="name", type="string")
     *         ),
     *         @OA\Property(
     *           property="shipment_items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="quantity", type="integer"),
     *             @OA\Property(property="category", type="string")
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unauthorized"),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation Error",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Error Occurred."),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Unexpected Error",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Unexpected Error Occurred."),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store(StoreShipmentRequest $request)
    {
        $request->validated();
        DB::beginTransaction();

        $role = Auth::user()->roles[0]->name ?? null;
        $originalUser = Auth::user();

        $sendWhatsappSetting = Setting::where('key', 'send_whatsapp_after_create_shipment')->value('value');
        $shouldSendWhatsapp = $sendWhatsappSetting === 'yes';

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
                "location_url"
            ]);

            $alternatePhoneSplit = $consigneeData['alternatePhone'] ? splitPhoneNumber($consigneeData['alternatePhone']) : ['country_code' => null, 'national_number' => null];
            $cellphoneSplit = $consigneeData['cellphone'] ? splitPhoneNumber($consigneeData['cellphone']) : ['country_code' => null, 'national_number' => null];

            $consigneeData['country_key_cellphone'] = $cellphoneSplit['country_code'];
            $consigneeData['country_key_alternatePhone'] = $alternatePhoneSplit['country_code'];
            $consigneeData['cellphone'] = $cellphoneSplit['national_number'];
            $consigneeData['alternatePhone'] = $alternatePhoneSplit['national_number'];

            $cc = $cellphoneSplit['country_code'] ?? null;
            $num = $cellphoneSplit['national_number'] ?? null;
            $acc = $alternatePhoneSplit['country_code'] ?? null;
            $anum = $alternatePhoneSplit['national_number'] ?? null;

            $existingConsignee = Consignee::withoutGlobalScope(ConsigneeScope::class)
                ->where(function ($q) use ($cc, $num, $acc, $anum) {
                    if ($cc && $num) {
                        $q->where(function ($qq) use ($cc, $num) {
                            $qq->where('country_key_cellphone', $cc)
                                ->where('cellphone', $num);
                        });
                    }
                    if ($acc && $anum) {
                        $q->orWhere(function ($qq) use ($acc, $anum) {
                            $qq->where('country_key_alternatePhone', $acc)
                                ->where('alternatePhone', $anum);
                        });
                    }
                })
                ->first();

            if ($existingConsignee) {
                $identityFields = [
                    'name',
                    'email',
                    'country_key_cellphone',
                    'cellphone',
                    'country_key_alternatePhone',
                    'alternatePhone',
                    'district',
                    'identify',
                    'taxNumber',
                ];

                $diff = collect($consigneeData)
                    ->only($identityFields)
                    ->filter(fn($v) => !is_null($v) && $v !== '')
                    ->diffAssoc($existingConsignee->only($identityFields));

                if ($diff->isNotEmpty()) {
                    $existingConsignee->update($diff->toArray());
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
                // "delivery_fee",
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
                "need_invoice",
                "sender_country_id",
                "sender_governorate_id",
                "sender_state_id",
                "sender_place_id",
                "sender_latitude",
                "sender_longitude"
            ]);

            $shipmentData['tracking_no'] = generate_tracking_no();
            $shipmentData['created_by'] = Auth::id();
            $shipmentData['consignee_id'] = $consignee->id;
            $shipmentData['created_source'] = 'dashboard';

            // Adding lat,lng and location_url
            if (isset($request->latitude) && isset($request->longitude) && isset($request->location_url)) {

                $shipmentData['latitude'] = $request->latitude;
                $shipmentData['longitude'] = $request->longitude;
                $shipmentData['location_url'] = $request->location_url;
            } else {

                $coordinates = getShipmentLatLng($request->streetAddress, $request->state_id) ?? [];
                $shipmentData['latitude'] = $coordinates['lat'] ?? null;
                $shipmentData['longitude'] = $coordinates['lng'] ?? null;
                $shipmentData['location_url'] = $coordinates['compoundCode'] ?? null;
            }

            $guestMerchantId = null;
            if ($request->boolean('is_walkin')) {
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
                    'country_code' => $phoneSplit['country_code'] ?? null,
                    'contact_no' => $phoneSplit['national_number'] ?? null,
                    'lat' => $request->sender_latitude ?? null,
                    'lng' => $request->sender_longitude ?? null,
                    'is_guest' => true,
                    'owner_id' => facility("id"),
                    'owner_type' => facility("type"),
                ];

                $existingMerchant = Merchant::withoutGlobalScope('scopeByOwner')
                    ->when($merchantData['contact_no'], fn($q) => $q->where('contact_no', $merchantData['contact_no']))
                    ->when($merchantData['country_code'], fn($q) => $q->where('country_code', $merchantData['country_code']))
                    ->when($merchantData['country_id'], fn($q) => $q->where('country_id', $merchantData['country_id']))
                    ->first();

                if ($existingMerchant) {
                    $diff = collect($merchantData)->diffAssoc($existingMerchant->only(array_keys($merchantData)));
                    if ($diff->isNotEmpty()) {
                        $existingMerchant->update($diff->toArray());
                    }
                    $guestMerchantId = $existingMerchant->user_id;
                } else {
                    $userData = [
                        'name' => $request->merchant_name,
                        'email' => $request->sender_email ?? 'guest_' . time() . '@example.com',
                        'phone' => $phoneSplit['national_number'] ?? null,
                        'country_code' => $phoneSplit['country_code'] ?? null,
                        'password' => bcrypt('guest_password'),
                        'owner_id' => facility("id"),
                        'owner_type' => facility("type"),
                    ];
                    $user = User::create($userData);
                    $user->assignRole("Merchant");
                    $merchantData['user_id'] = $user->id;

                    Merchant::create($merchantData);
                    $guestMerchantId = $user->id;

                    $states = State::select('id')->get();
                    $defaultMerchantCommission = Setting::where('key', 'default_merchant_commission')->value('value') ?? 1;
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

                $shipmentData['merchant_id'] = $guestMerchantId;

                $waybill = MerchantWaybill::create([
                    "merchant_id" => $shipmentData['merchant_id'],
                    "tracking_no" => generate_merchant_tracking_no()
                ]);
                $shipmentData['tracking_no'] = $waybill->tracking_no;
            }
            $resolvedShipperId = $shipmentData['shipper_id']
                ?? $request->input('shipper_id')
                ?? optional(Shipper::pe())->id;

            $shipmentData['shipper_id'] = $resolvedShipperId;
            $deliveryAddressInput = [
                'country_id' => $consigneeData['country_id'] ?? null,
                'governorate_id' => $consigneeData['governorate_id'] ?? null,
                'state_id' => $consigneeData['state_id'] ?? null,
                'place_id' => $consigneeData['place_id'] ?? null,
                'city_id' => $consigneeData['city_id'] ?? null,
                'zipcode' => $consigneeData['zipcode'] ?? null,
                'streetAddress' => $consigneeData['streetAddress'] ?? null,
                'longitude' => $consigneeData['longitude'] ?? null,
                'latitude' => $consigneeData['latitude'] ?? null,
                'location_url' => $consigneeData['location_url'] ?? null,
                'label' => null,
            ];
            $deliveryAddress = $this->createConsigneeAddress($consignee->id, $deliveryAddressInput);
            $shipmentData['delivery_address_id'] = $deliveryAddress->id;
            // === Pricing (merchant / shipper) ===

            $stateId = $deliveryAddress->state_id ?? null;
            $countryId = $deliveryAddress->country_id ?? null;

            if (!$stateId) {
                throw new \Exception("state_id is missing for delivery address");
            }

            $resolvedShipperId = $shipmentData['shipper_id']
                ?? $request->input('shipper_id')
                ?? optional(Shipper::pe())->id;

            if (!$resolvedShipperId) {
                throw new \Exception("shipper_id not resolved");
            }
            $shipmentData['shipper_id'] = $resolvedShipperId;

            $shipperDeliveryFromCommission = optional(
                ShipperCommission::where('shipper_id', $resolvedShipperId)
                    ->where('state_id', $stateId)
                    ->first()
            )->delivery_fee;
            $shipperDeliveryFromCommission = is_null($shipperDeliveryFromCommission) ? null : (float) $shipperDeliveryFromCommission;

            $merchantBaseFromCommission = null;
            $merchantLegacyDelivery = null;
            $merchantDiscountFromCommission = null;

            if (!empty($shipmentData['merchant_id'])) {
                // PRIORITY 1: Check state-specific merchant commission
                $cc = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
                    ->where('state_id', $stateId)
                    ->first();

                // PRIORITY 2: If no state-specific, check global merchant commission
                if (!$cc) {
                    $cc = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
                        ->whereNull('state_id')
                        ->first();
                }

                if ($cc) {
                    if (array_key_exists('base_delivery_fee', $cc->getAttributes())) {
                        $merchantBaseFromCommission = is_null($cc->base_delivery_fee) ? null : (float) $cc->base_delivery_fee;
                    }
                    if (array_key_exists('delivery_fee', $cc->getAttributes())) {
                        $merchantLegacyDelivery = is_null($cc->delivery_fee) ? null : (float) $cc->delivery_fee;
                    }
                    if (array_key_exists('delivery_discount_amount', $cc->getAttributes())) {
                        $merchantDiscountFromCommission = is_null($cc->delivery_discount_amount) ? 0.0 : (float) $cc->delivery_discount_amount;
                    }
                }
            }

            $templateQuery = CommissionTemplate::query();

            if (!is_null($countryId)) {
                $templateRow = (clone $templateQuery)
                    ->where('country_id', $countryId)
                    ->where('state_id', $stateId)
                    ->first();

                if (!$templateRow) {
                    $templateRow = (clone $templateQuery)
                        ->where('country_id', $countryId)
                        ->whereNull('state_id')
                        ->first();
                }

                if (!$templateRow) {
                    $templateRow = CommissionTemplate::whereNull('country_id')
                        ->where('state_id', $stateId)
                        ->first()
                        ?: CommissionTemplate::whereNull('country_id')->whereNull('state_id')->first();
                }
            } else {
                $templateRow = CommissionTemplate::where('state_id', $stateId)->first()
                    ?: CommissionTemplate::whereNull('state_id')->first();
            }

            $defaultFromTemplate = $templateRow ? (float) $templateRow->base_delivery_fee : null;

            if (empty($shipmentData['merchant_id'])) {
                if (!is_null($shipperDeliveryFromCommission) && $shipperDeliveryFromCommission > 0) {
                    $shipmentData['delivery_fee'] = (float) $shipperDeliveryFromCommission;
                } elseif (!is_null($defaultFromTemplate) && $defaultFromTemplate > 0) {

                    $shipmentData['delivery_fee'] = (float) $defaultFromTemplate;
                } else {

                    $shipmentData['delivery_fee'] = 0.0;
                }
            } else {
                if (!is_null($merchantLegacyDelivery) && $merchantLegacyDelivery > 0) {

                    $shipmentData['delivery_fee'] = (float) $merchantLegacyDelivery;
                } else if (!is_null($merchantBaseFromCommission) && $merchantBaseFromCommission > 0) {

                    $shipmentData['delivery_fee'] = (float) $merchantBaseFromCommission;
                } elseif (!is_null($defaultFromTemplate) && $defaultFromTemplate > 0) {

                    $shipmentData['delivery_fee'] = (float) $defaultFromTemplate;
                } elseif (!is_null($merchantLegacyDelivery) && $merchantLegacyDelivery > 0) {

                    $shipmentData['delivery_fee'] = (float) $merchantLegacyDelivery;
                } elseif (!is_null($shipperDeliveryFromCommission) && $shipperDeliveryFromCommission > 0) {

                    $shipmentData['delivery_fee'] = (float) $shipperDeliveryFromCommission;
                } else {

                    $shipmentData['delivery_fee'] = 0.0;
                }
            }


            // Always set delivery_fee_before_discount in shipmentData for getTotalCOD() calculation
            // Base fee before applying any discount: prefer merchant base_delivery_fee when available
            $shipmentData['delivery_fee_before_discount'] = !is_null($merchantBaseFromCommission)
                ? (float) $merchantBaseFromCommission
                : (float) ($shipmentData['delivery_fee'] ?? 0.0);

            if (Schema::hasColumn('shipments', 'delivery_fee_before_discount')) {
                // Already set above for database storage
            }
            if (Schema::hasColumn('shipments', 'delivery_fee_discount')) {
                // Discount comes from MerchantCommission.delivery_discount_amount for the same merchant_id/state_id
                $shipmentData['delivery_fee_discount'] = (float) ($merchantDiscountFromCommission ?? 0.0);
            }

            // Set value to shipment value only
            $value = (float) ($request->value ?? 0);
            $shipmentData['value'] = $value;

            // Set fee_payer from request
            $shipmentData['fee_payer'] = $request->fee_payer;

            // Use centralized calculation service for total_cod
            $calculationService = app(\App\Services\CalculationLogicService::class);
            $shipmentObj = (object) $shipmentData; // Convert to object for calculation
            $shipmentData['total_cod'] = $calculationService->getTotalCOD($shipmentObj);

            \Log::info('Pricing Decision', [
                'merchant_id' => $shipmentData['merchant_id'] ?? null,
                'state_id' => $stateId,
                'country_id' => $countryId,
                'template_base_delivery' => $defaultFromTemplate,
                'merchant_base_delivery' => $merchantBaseFromCommission,
                'merchant_legacy_delivery' => $merchantLegacyDelivery,
                'shipper_delivery' => $shipperDeliveryFromCommission,
                'final_delivery_fee' => $shipmentData['delivery_fee'],
                'fee_payer' => $shipmentData['fee_payer'] ?? 'customer',
                'payment_type' => $shipmentData['payment_type'] ?? 'COD',
                'value' => $value,
                'total_cod' => $shipmentData['total_cod'],
            ]);

            if (!$request->shipper_id) {
                $shipmentData['shipper_id'] = Shipper::pe()->id;
            }

            if ($role === "Merchant") {
                $shipmentData['tracking_no'] = $request->tracking_no;
                $shipmentData['owner_id'] = Auth::user()->owner_id;
                $shipmentData['owner_type'] = Auth::user()->owner_type;
                $shipmentData['facility_id'] = Auth::user()->facility_id;
                $shipmentData['facility_type'] = Auth::user()->facility_type;

                $shipment = Shipment::create($shipmentData)->load('consignee');

                // Set hub information using the new service
                $hubService = app(\App\Services\HubInformationService::class);
                $hubService->setFinalHub($shipment);
                $hubService->setInitialCurrentHub($shipment);
                $shipment->save();

                $link = app(AddressUpdateLinkService::class)->generate($shipment);
                if ((int) ($shipment->is_outsourced ?? 0) !== 1 && $shouldSendWhatsapp) {
                    $shipment->consignee->notify(new ShipmentCreatedNotification($shipment, $link['url'], $link['otp']));
                }

                $merchantAccount = Account::firstOrCreate(
                    ['accountable_type' => User::class, 'accountable_id' => $shipment->merchant_id],
                    ['parcel_value' => 0, 'balance' => 0]
                );
                // Use CalculationLogicService to get merchant COD (goods value only for COD shipments)
                $merchantCOD = $calculationService->getMerchantCOD($shipment);
                $merchantAccount->parcel_value += $merchantCOD;
                $merchantAccount->save();

                $merchantWaybill = MerchantWaybill::where("tracking_no", $shipmentData['tracking_no'])->first();
                if ($merchantWaybill) {
                    $merchantWaybill->used = 1;
                    $merchantWaybill->save();
                }

                Transaction::create([
                    "to_id" => Auth::user()->id,
                    "to_type" => User::class,
                    "shipment_id" => $shipment->id,
                    "amount" => $shipment->total_cod,
                    "type" => "merchant_created",
                ]);
            } else {
                $shipment = Shipment::create($shipmentData)->load('consignee');
                // Set hub information using the new service
                $hubService = app(\App\Services\HubInformationService::class);
                $hubService->setFinalHub($shipment);
                $hubService->setInitialCurrentHub($shipment);

                $zone = app(SorterController::class)->resolveZoneForShipment($shipment);
                if ($zone) {
                    $shipment->destination_owner_id = $zone->owner_id;
                    $shipment->destination_owner_type = $zone->owner_type;
                    $shipment->save();
                }

                if (!empty($shipment) && (int) ($shipment->is_outsourced ?? 0) === 1) {
                    $link = app(AddressUpdateLinkService::class)->generate($shipment);
                    if ($shouldSendWhatsapp) {
                        $shipment->consignee->notify(new OutsourcedShipmentCreatedNotification($shipment, $link['url'], $link['otp']));
                    }
                }

                $ownerAccount = Account::firstOrCreate(
                    ['accountable_type' => Auth::user()->owner_type, 'accountable_id' => Auth::user()->owner_id],
                    ['parcel_value' => 0, 'balance' => 0]
                );
                $ownerAccount->parcel_value += $shipmentData['total_cod'] ?? 0;
                $ownerAccount->save();

                Transaction::create([
                    "from_id" => $shipment->merchant ? $shipment->merchant->id : null,
                    "from_type" => User::class,
                    "to_id" => Auth::user()->owner_id,
                    "to_type" => Auth::user()->owner_type,
                    "shipment_id" => $shipment->id,
                    "amount" => $shipment->total_cod ?? 0,
                    "type" => "merchant_created",
                ]);
            }

            ShipmentInformation::create([
                'shipment_id' => $shipment->id,
                'merchant_id' => $shipmentData['merchant_id'] ?? null,
                'unit_id' => $request->unit_id,
                'zone_id' => $request->zone_id,
                'package_id' => $request->package_id,
                'tracking_no' => $shipment->tracking_no,
                'in_warehouse' => true,
                'weight' => $request->weight,
                'height' => $request->height,
                'width' => $request->width,
                'length' => $request->length,
                'status' => $request->status ?? 0,
            ]);

            ShipmentDelivery::create(['shipment_id' => $shipment->id]);
            ShipmentFinance::create(['shipment_tracking_no' => $shipment->tracking_no]);

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

            if ((int) ($shipment->is_outsourced ?? 0) !== 1 && $shouldSendWhatsapp) {
                $link = app(AddressUpdateLinkService::class)->generate($shipment);
                $shipment->consignee->notify(new ShipmentCreatedNotification($shipment, $link['url'], $link['otp']));
            }

            // Auto-pickup for walkin with driver (كما هو عندك)
            $proofsResponse = [];
            if ($request->boolean('is_walkin') && $request->filled('driver_id') && (int) $request->driver_id > 0) {
                $driver = User::with('roles', 'driver')->find($request->driver_id);
                if (!$driver) {
                    throw new Exception("Driver not found for driver_id: {$request->driver_id}");
                }

                Sanctum::actingAs($driver, ['*']);
                try {
                    $pickupService = app(\App\Services\ShipmentPickupService::class);
                    $result = $pickupService->handleShipmentPickup($request, $shipment->tracking_no, $driver->id, $shipment);
                    if (!$result['success']) {
                        throw new Exception($result['message']);
                    }
                    $proofsResponse = $result['data']['proofs'];
                } finally {
                    Sanctum::actingAs($originalUser, ['*']);
                }
            }

            DB::commit();
            $responseArray = (new ShipmentResource($shipment->load("consignee", "shipment_items", "deliveryAddress")))->toArray($request);

            // ضيف تجميعة الرسوم في الـ response (من غير ما تغيّر في الجدول)
            $responseArray['fees'] = [
                'fee_payer' => $shipmentData['fee_payer'] ?? 'shipper',
                'base_delivery_fee' => isset($baseDeliveryForResponse) ? (float) $baseDeliveryForResponse : null,
                'merchant' => [
                    'base' => isset($merchantBaseFee) ? (float) $merchantBaseFee : null,
                    'discount' => isset($merchantDiscount) ? (float) $merchantDiscount : null,
                    'effective' => isset($merchantEffectiveFee) ? (float) $merchantEffectiveFee : null,
                ],
                'shipper' => [
                    'base' => isset($shipperBaseFee) ? (float) $shipperBaseFee : null,      // هتكون null لو معندكش عمود base للـ shipper
                    'effective' => isset($shipperEffectiveFee) ? (float) $shipperEffectiveFee : null,
                ],
                'charged_delivery_fee' => (float) ($shipmentData['delivery_fee'] ?? 0),
            ];

            $responseData = $responseArray;


            $responseData = new ShipmentResource($shipment->load("consignee", "shipment_items", "deliveryAddress"));
            if ($request->boolean('is_walkin') && $request->filled('driver_id') && (int) $request->driver_id > 0) {
                $responseData = array_merge($responseData->toArray($request), ['proofs' => $proofsResponse]);
            }
            $identifier = $shipment->tracking_no ?? $shipment->pre_id ?? $shipment->id;
            activityLog('shipment creation', "shipment with Identifier : {$identifier}");

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
    /**
     * Get shipment details for editing
     *
     * @OA\Post(
     *   path="/shipments/edit/{id}",
     *   tags={"OMS"},
     *   summary="Get shipment details for editing",
     *   description="Retrieve shipment details with all relationships for editing",
     *   operationId="getShipmentForEdit",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Shipment ID to edit",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipment retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipment"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="tracking_no", type="string", example="TRK123"),
     *         @OA\Property(
     *           property="shipper",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="country", type="object"),
     *           @OA\Property(property="state", type="object")
     *         ),
     *         @OA\Property(
     *           property="consignee",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="country", type="object"),
     *           @OA\Property(property="state", type="object"),
     *           @OA\Property(property="city", type="object"),
     *           @OA\Property(property="old_address", type="object")
     *         ),
     *         @OA\Property(
     *           property="shipment_information",
     *           type="object",
     *           @OA\Property(property="zone", type="object")
     *         ),
     *         @OA\Property(
     *           property="shipmentHistories",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="time", type="string", format="date-time")
     *           )
     *         ),
     *         @OA\Property(
     *           property="shipment_amounts",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             @OA\Property(property="amount", type="number")
     *           )
     *         ),
     *         @OA\Property(
     *           property="shipment_items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="quantity", type="integer"),
     *             @OA\Property(property="category", type="string")
     *           )
     *         ),
     *         @OA\Property(
     *           property="merchant",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="name", type="string")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unauthorized"),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Shipment not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Shipment not found"),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function edit($id)
    {
        $shipment = Shipment::with([
            // Basic relationships
            'shipper.country',
            'shipper.state',
            'consignee.country',
            'consignee.state',
            'consignee.city',
            'consignee.old_address',
            'shipment_information.zone',
            'shipmentHistories',
            'shipment_amounts',
            'shipment_items',
            'merchant',
            'consignee',
            'shipment_information.package',
            'shipment_delivery',
            'shipment_finance',
            'transactions' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);
        $shipment->loadMissing([
            'shipper.commissions' => function ($query) use ($shipment) {
                $query->where('state_id', $shipment->state_id);
            },
            'merchant.commissions' => function ($query) use ($shipment) {
                $query->where('state_id', $shipment->state_id);
            }
        ]);
        return sendResponse("Shipment details retrieved successfully", new ShipmentResource($shipment));
    }

    /**
     * Get shipment details by tracking number
     *
     * @OA\Post(
     *   path="/shipments/show/{tracking_no}",
     *   tags={"OMS"},
     *   summary="Get shipment details by tracking number",
     *   description="Retrieve shipment details with consignee location information",
     *   operationId="getShipmentByTracking",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="tracking_no",
     *     in="path",
     *     description="Tracking number of the shipment",
     *     required=true,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipment retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipment"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="tracking_no", type="string", example="TRK123"),
     *         @OA\Property(
     *           property="consignee",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="country", type="object"),
     *           @OA\Property(property="state", type="object"),
     *           @OA\Property(property="governorate", type="object"),
     *           @OA\Property(property="place", type="object")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unauthorized"),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Shipment not found",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Shipment not found"),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */

    public function show($tracking_no)
    {
        $shipment = Shipment::with([
            'consignee.country',
            'consignee.state',
            'consignee.governorate',
            'consignee.place',
        ])->where('tracking_no', $tracking_no)->first();
        return sendResponse("Shipment", new ShipmentResource($shipment));
    }


    public function showById(Request $request, int $id)
    {
        try {
            $shipment = Shipment::query()
                // ->byOwner()
                ->with([
                    'consignee',
                    'consignee.country',
                    'consignee.state',
                    'consignee.governorate',
                    'consignee.place',
                    'shipment_items',
                    'shipper',
                    'shipment_information',
                    'instant_delivery_assignment',
                    'instant_delivery_assignment.driver',
                    'senderCountry',
                    'senderGovernorate',
                    'senderState',
                    'senderPlace',
                ])
                ->find($id);

            if (!$shipment) {
                return sendResponse('Shipment not found.', [], false, [], 404);
            }
            return sendResponse('Shipment retrieved successfully.', [
                'shipment' => $shipment
            ]);
        } catch (\Throwable $e) {
            Log::error('shipments.showById failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return sendResponse('Error occurred.', [], false, [$e->getMessage()], 500);
        }
    }


    /**
     * Update existing shipment details
     *
     * @OA\Post(
     *   path="/shipments/update",
     *   tags={"OMS"},
     *   summary="Update shipment details",
     *   description="Update shipment information including shipper, consignee, and payment details",
     *   operationId="updateShipment",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     required=true,
     *     description="Shipment update data",
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(
     *         property="id",
     *         type="integer",
     *         description="Shipment ID to update"
     *       ),
     *       @OA\Property(
     *         property="shipper_id",
     *         type="integer",
     *         description="ID of the shipper"
     *       ),
     *       @OA\Property(
     *         property="notes",
     *         type="string",
     *         description="Shipment notes"
     *       ),
     *       @OA\Property(
     *         property="payment_type",
     *         type="string",
     *         description="Payment type"
     *       ),
     *       @OA\Property(
     *         property="total_cod",
     *         type="number",
     *         description="Shipment total COD amount"
     *       ),
     *       @OA\Property(
     *         property="delivery_fee",
     *         type="number",
     *         description="Delivery fee amount"
     *       ),
     *       @OA\Property(
     *         property="merchant_id",
     *         type="integer",
     *         description="ID of the merchant"
     *       ),
     *       @OA\Property(
     *         property="name",
     *         type="string",
     *         description="Consignee name"
     *       ),
     *       @OA\Property(
     *         property="cellphone",
     *         type="string",
     *         description="Consignee cellphone number"
     *       ),
     *       @OA\Property(
     *         property="country_id",
     *         type="integer",
     *         description="Consignee country ID"
     *       ),
     *       @OA\Property(
     *         property="state_id",
     *         type="integer",
     *         description="Consignee state ID"
     *       ),
     *       @OA\Property(
     *         property="place_id",
     *         type="integer",
     *         description="Consignee place ID"
     *       ),
     *       @OA\Property(
     *         property="streetAddress",
     *         type="string",
     *         description="Consignee street address"
     *       ),
     *       @OA\Property(
     *         property="longitude",
     *         type="number",
     *         description="Consignee longitude"
     *       ),
     *       @OA\Property(
     *         property="latitude",
     *         type="number",
     *         description="Consignee latitude"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipment updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipment updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="tracking_no", type="string", example="TRK123"),
     *         @OA\Property(
     *           property="shipper",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="country", type="object"),
     *           @OA\Property(property="state", type="object")
     *         ),
     *         @OA\Property(
     *           property="merchant",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="country", type="object"),
     *           @OA\Property(property="governorate", type="object"),
     *           @OA\Property(property="state", type="object"),
     *           @OA\Property(property="place", type="object")
     *         ),
     *         @OA\Property(
     *           property="consignee",
     *           type="object",
     *           @OA\Property(property="id", type="integer"),
     *           @OA\Property(property="name", type="string"),
     *           @OA\Property(property="country", type="object"),
     *           @OA\Property(property="governorate", type="object"),
     *           @OA\Property(property="state", type="object"),
     *           @OA\Property(property="place", type="object")
     *         ),
     *         @OA\Property(
     *           property="shipment_information",
     *           type="object",
     *           @OA\Property(property="zone", type="object")
     *         ),
     *         @OA\Property(
     *           property="shipmentHistories",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="time", type="string", format="date-time")
     *           )
     *         ),
     *         @OA\Property(
     *           property="shipment_items",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="quantity", type="integer")
     *           )
     *         ),
     *         @OA\Property(
     *           property="shipment_delivery",
     *           type="object"
     *         ),
     *         @OA\Property(
     *           property="transactions",
     *           type="array",
     *           @OA\Items(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="amount", type="number")
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Unauthorized"),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Database Error",
     *     @OA\JsonContent(
     *         @OA\Property(property="success", type="boolean", example=false),
     *         @OA\Property(property="message", type="string", example="Error Occured."),
     *         @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */

    public function update(UpdateShipmentRequest $request)
    {
        $request->validated();

        $shipment = Shipment::with(['consignee'])->find($request->id);
        if (!$shipment) {
            return sendResponse("Shipment not found.", false, [], [], 404);
        }

        DB::beginTransaction();
        try {
            // 1) تحديث بيانات الأوردر الأساسية (بدون عنوان)
            $shipmentData = $request->only([
                "shipper_id",
                "notes",
                "payment_type",
                "total_cod",
                "delivery_fee",
                "merchant_id",
                "is_outsourced",
            ]);
            if (empty($shipmentData['shipper_id'])) {
                $shipmentData['shipper_id'] = 1; // fallback مناسب
            }
            $shipment->fill($shipmentData)->save();

            // 2) تحديث هوية المستلم فقط (اسم/تليفونات) — بدون أي حقول عنوان
            if ($shipment->consignee) {
                $identity = $request->only(["name", "cellphone", "alternatePhone"]);

                if ($request->filled('cellphone') && function_exists('splitPhoneNumber')) {
                    $cell = splitPhoneNumber($request->cellphone);
                    $identity['cellphone'] = $cell['national_number'] ?? null;
                    $identity['country_key_cellphone'] = $cell['country_code'] ?? null;
                }
                if ($request->filled('alternatePhone') && function_exists('splitPhoneNumber')) {
                    $alt = splitPhoneNumber($request->alternatePhone);
                    $identity['alternatePhone'] = $alt['national_number'] ?? null;
                    $identity['country_key_alternatePhone'] = $alt['country_code'] ?? null;
                }

                if (collect($identity)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty()) {
                    $shipment->consignee->update($identity);
                }
            }

            // 3) معالجة العنوان المُقترح — ينشئ Address جديد + ShipmentAddressRevision pending
            $proposed = $request->only([
                'country_id',
                'governorate_id',
                'state_id',
                'place_id',
                'city_id',
                'zipcode',
                'streetAddress',
                'longitude',
                'latitude',
                'location_url',
            ]);

            // العنوان النشط الحالي للـ consignee (لو موجود)
            $active = Address::where('consignee_id', $shipment->consignee_id)
                ->where('is_active', true)
                ->latest('id')
                ->first();

            // هل مختلف؟
            $isDifferent = $active
                ? collect([
                    'country_id',
                    'governorate_id',
                    'state_id',
                    'place_id',
                    'city_id',
                    'zipcode',
                    'streetAddress',
                    'longitude',
                    'latitude',
                    'location_url',
                ])->some(function ($k) use ($active, $proposed) {
                    return ($proposed[$k] ?? null) != ($active->$k ?? null);
                })
                : collect($proposed)->filter()->isNotEmpty();

            if ($isDifferent) {
                // أنشئ عنوان جديد (غير معتمد وغير نشط) مرتبط بالـ consignee
                $newAddress = Address::create(array_merge($proposed, [
                    'consignee_id' => $shipment->consignee_id,
                    'approved' => false,
                    'is_active' => false,
                ]));

                // اربط الأوردر بالعنوان المقترح للتنفيذ
                $shipment->delivery_address_id = $newAddress->id;
                $shipment->save();

                // أنشئ سطر مراجعة بانتظار الموافقة
                ShipmentAddressRevision::create([
                    'shipment_id' => $shipment->id,
                    'old_address_id' => $active?->id,
                    'new_address_id' => $newAddress->id,
                    'changed_by' => Auth::id(),
                    'reason' => $request->input('reason', 'Proposed via shipment update'),
                    'approved' => false,
                    'rejected' => false,
                ]);

                shipmentHistory([
                    "shipment_id" => $shipment->id,
                    "status" => "ADDRESS_UPDATE_REQUESTED",
                    "description" => "Consignee address update requested via shipment edit.",
                ]);
            } else {
                // نفس العنوان: لو عندك delivery_address_id مختلف، سوّه مع النشط
                if ($active && $shipment->delivery_address_id !== $active->id) {
                    $shipment->delivery_address_id = $active->id;
                    $shipment->save();
                }
            }

            // ألغِ استثناء لو مرفوع
            if ($shipment->in_exception) {
                $shipment->update(['in_exception' => false]);
            }

            DB::commit();

            // رجّع الأوردر بعلاقاته (وبالذات العنوان المختار للتوصيل)
            $shipment->refresh()->load([
                'consignee:id,name',
                'deliveryAddress.country:id,name',
                'deliveryAddress.governorate:id,en_name,ar_name',
                'deliveryAddress.state:id,en_name,ar_name',
                'deliveryAddress.place:id,en_name,ar_name',
                'deliveryAddress.city:id,name',
            ]);
            $identifier = $shipment->tracking_no ?? $shipment->pre_id ?? $shipment->id;
            activityLog('shipment updated', "shipment with Identifier : {$identifier} updated ");
            return sendResponse("Shipment updated successfully.", new ShipmentResource($shipment));
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Updating shipment failed.", [], false, [$e->getMessage()], 500);
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/shipments/delete",
     *     tags={"OMS"},
     *     summary="Delete an shipment and its related data",
     *     description="Deletes an shipment along with its related items and histories",
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="ID of the shipment to delete"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function delete(Request $request)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            return sendResponse("Only admins can delete shipments.", [], [], 403);
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:shipments,id',
        ]);

        try {
            $shipments = Shipment::withoutGlobalScope(ShipmentScope::class)
                ->with(['shipment_items', 'shipmentHistories', 'consignee'])
                ->whereIn('id', $request->ids)
                ->get();
            if ($shipments->isEmpty()) {
                return sendResponse("No shipments found.", [], [], 404);
            }
            $deletedCount = 0;
            foreach ($shipments as $shipment) {
                $shipment->delete();
                $identifier = $shipment->tracking_no ?? $shipment->pre_id ?? $shipment->id;

                activityLog('shipment deleted', "shipment with Identifier : {$identifier} deleted");
                $deletedCount++;
            }
            $message = $deletedCount === 1
                ? "Shipment and related data deleted successfully."
                : "$deletedCount shipments and their related data have been deleted successfully.";
        } catch (QueryException $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected Error Occurred.", [], [$e->getMessage()], 500);
        }
        return sendResponse($message, ['deleted_count' => $deletedCount]);
    }


    /**
     * @OA\Post(
     *     path="/api/shipments/update-status",
     *     tags={"OMS"},
     *     summary="Update single shipment status",
     *     description="Updates an shipment's status and handles related driver assignment timestamps",
     *     @OA\Parameter(
     *         name="role",
     *         in="header",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"Super Admin"}
     *         ),
     *         description="User role required for this endpoint"
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="ID of the shipment to update"
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"RETURNED", "FUTURE_DELIVERY", "NO_ANSWER", "DELIVERED"}
     *         ),
     *         description="New status value"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment status updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error"
     *     )
     * )
     */
    public function updateStatus(Request $request)
    {
        try {
            $status = $request->status;
            $shipment = Shipment::findOrFail($request->id);
            $shipment->status = $status;
            $shipment->save();
            if ($status == "RETURNED" || $status == DeliveryExceptionEnum::FUTURE_DELIVERY || $status == DeliveryExceptionEnum::NO_ANSWER) {
                DriverShipmentAssignment::where('shipment_id', $shipment->id)->update(['returned_at' => now()]);
            } elseif ($status == "DELIVERED") {
                DriverShipmentAssignment::where('shipment_id', $shipment->id)->update(['delivered_at' => now()]);
            }
            return sendResponse("Status updated successfully.", new ShipmentResource($shipment));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating status.", [], [$e->getMessage()], 422);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/shipments/update_bulk_status",
     *     tags={"OMS"},
     *     summary="Update multiple shipments' status in bulk",
     *     description="Updates the status of multiple shipments at once and handles related operations",
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"RETURNED", "FUTURE_DELIVERY", "NO_ANSWER", "DELIVERED", "LOST"}
     *         ),
     *         description="New status value"
     *     ),
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="string"
     *             ),
     *             minItems=1
     *         ),
     *         description="Array of valid tracking numbers"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments status updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No matching shipments found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation or database error"
     *     )
     * )
     */
    public function update_bulk_status(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'required',

                'future_delivery_date' => [
                    'exclude_unless:status,FUTURE_DELIVERY', // 🔥 key rule
                    'required',
                    'date',
                    'after:today',
                ],

                'tracking_no' => 'required|array|min:1|exists:shipments,tracking_no',
            ]);

            DB::beginTransaction();

            $status = $request->status;
            $trackingNumbers = $validated['tracking_no'];



            $shipments = Shipment::whereIn('tracking_no', $trackingNumbers)->get();

            if ($shipments->isEmpty()) {
                return sendResponse("No matching shipments found.", [], [], 404);
            }

            if (strtolower($status) == strtolower("LOST")) {
                foreach ($shipments as $shipment) {
                    $shipment->update([
                        'status' => $status,
                        'in_exception' => false
                    ]);
                }

                DriverRunsheetShipment::whereIn('shipment_tracking_no', $trackingNumbers)->update([
                    'status' => strtolower($status),
                ]);
            } else {
                foreach ($shipments as $shipment) {
                    $shipment->update([
                        'status' => $status,
                        'in_exception' => false
                    ]);
                }
            }

            if ($status == "FUTURE_DELIVERY") {
                foreach ($shipments as $shipment) {
                    $shipment->shipment_delivery()->updateOrCreate([
                        'future_delivery_date' => $request->future_delivery_date,
                    ]);
                }
            }
            if ($status == "RETURNED" || $status == DeliveryExceptionEnum::FUTURE_DELIVERY || $status == DeliveryExceptionEnum::NO_ANSWER) {
                DriverShipmentAssignment::whereIn('shipment_id', $shipments->pluck('id'))->update(['returned_at' => now()]);
            } elseif ($status == "DELIVERED") {
                DriverShipmentAssignment::whereIn('shipment_id', $shipments->pluck('id'))->update(['delivered_at' => now()]);
            }

            foreach ($shipments as $shipment) {
                $description = "[" . status($status)['label'] . "] Shipment tracking no: " . $shipment->tracking_no;

                $historyData = [
                    "status" => status($status)['label'],
                    "description" => $description,
                    "shipment_id" => $shipment->id,
                ];

                shipmentHistory($historyData);
                activityLog("shipment_status_changed", "Shipment {$shipment->tracking_no} status has been updated to {$status}.");
            }

            DB::commit();
            return sendResponse("Status updated successfully.", ShipmentResource::collection($shipments));
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error updating shipments.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/shipments/getSingle",
     *     tags={"OMS"},
     *     summary="Get single shipment details by tracking number",
     *     description="Retrieves a single shipment with all related data including locations, history, and financial information",
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Shipment tracking number"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment fetched successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Database error"
     *     )
     * )
     */
    // public function getSingle()
    // {
    //     try {
    //         $shipment = Shipment::where('tracking_no', request()->tracking_no)->with([
    //             'shipper:id,name,country_id,state_id,contact,zip_code,address',
    //             'shipper.country:id,name',
    //             'shipper.state:id,en_name,ar_name',

    //             'merchant:id,name',
    //             'merchant.merchant.country:id,name',
    //             'merchant.merchant.governorate:id,en_name,ar_name',
    //             'merchant.merchant.state:id,en_name,ar_name',
    //             'merchant.merchant.place:id,en_name,ar_name',
    //             'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone,location_url,latitude,longitude',
    //             'consignee.country:id,name',
    //             'consignee.governorate:id,en_name,ar_name',
    //             'consignee.state:id,en_name,ar_name',
    //             'consignee.place:id,en_name,ar_name',
    //             'consignee.old_address.governorate:id,en_name,ar_name',
    //             'consignee.old_address.state:id,en_name,ar_name',
    //             'consignee.old_address.country:id,name',
    //             'consignee.old_address.place:id,en_name,ar_name',

    //             'shipment_information:id,shipment_id,zone_id,in_warehouse',
    //             'shipment_information.zone:id,name',

    //             'shipmentHistories',
    //             'shipment_items',
    //             'shipment_delivery',
    //             "quick_notes",
    //             'transactions.from',
    //             'transactions.to',
    //             'shipment_finance',
    //             'current_assignment.driver',
    //             'assigned_to_shelf',
    //             'merchant_pickup_shipment.driver:id,name',
    //             'merchant_pickup_shipment.pickup_task',
    //             'driver:id,name',
    //             'driver_status:driver_id,latitude,longitude,location,last_updated'
    //         ])->firstOrFail();

    //         return sendResponse("Shipment fetched successfully.", new ShipmentResource($shipment));
    //     } catch (QueryException $e) {
    //         return sendResponse("Error occurred while fetching the shipment.", [], false, [$e->getMessage()], 422);
    //     }
    // }

    public function getSingle()
    {
        try {
            $shipment = Shipment::where('tracking_no', request()->tracking_no)->with([
                'shipper:id,name,country_id,state_id,contact,zip_code,address',
                'shipper.country:id,name',
                'shipper.state:id,en_name,ar_name',

                'merchant:id,name',
                'merchant.merchant.country:id,name',
                'merchant.merchant.governorate:id,en_name,ar_name',
                'merchant.merchant.state:id,en_name,ar_name',
                'merchant.merchant.place:id,en_name,ar_name',

                // لا نستخدم old_address
                'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone,location_url,latitude,longitude',

                // العنوان الخاص بالأوردر فقط
                'deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,zipcode,streetAddress,latitude,longitude,location_url,label,approved,is_active,last_used_at',
                'deliveryAddress.country:id,name',
                'deliveryAddress.governorate:id,en_name,ar_name',
                'deliveryAddress.state:id,en_name,ar_name',
                'deliveryAddress.place:id,en_name,ar_name',
                'deliveryAddress.city:id,name',

                'shipment_information:id,shipment_id,zone_id,in_warehouse',
                'shipment_information.zone:id,name',

                'shipmentHistories',
                'shipment_items',
                'shipment_delivery',
                'quick_notes',
                'transactions.from',
                'transactions.to',
                'shipment_finance',
                'current_assignment.driver',
                'assigned_to_shelf',
                'merchant_pickup_shipment.driver:id,name',
                'merchant_pickup_shipment.pickup_task',
                'driver:id,name',
                'driver_status:driver_id,latitude,longitude,location,last_updated'
            ])->firstOrFail();

            // ✅ سلامة الربط: لازم عنوان الأوردر يعود لنفس الـ consignee
            if ($shipment->deliveryAddress && $shipment->deliveryAddress->consignee_id !== $shipment->consignee_id) {
                return sendResponse(
                    "Shipment address doesn't belong to this consignee.",
                    [],
                    false,
                    ["delivery_address_id mismatch with consignee_id"],
                    422
                );
            }

            // نحضّر مصفوفة العنوان من علاقة الأوردر فقط
            $addr = $shipment->deliveryAddress;
            $addressArray = $addr ? [
                'id' => $addr->id,
                'label' => $addr->label,
                'streetAddress' => $addr->streetAddress,
                'zipcode' => $addr->zipcode,
                'latitude' => $addr->latitude,
                'longitude' => $addr->longitude,
                'location_url' => $addr->location_url,
                'approved' => $addr->approved,
                'is_active' => $addr->is_active,
                'last_used_at' => optional($addr->last_used_at)?->toDateTimeString(),
                'country' => $addr->country?->name,
                'governorate' => $addr->governorate?->en_name ?? $addr->governorate?->ar_name,
                'state' => $addr->state?->en_name ?? $addr->state?->ar_name,
                'place' => $addr->place?->en_name ?? $addr->place?->ar_name,
                'city' => $addr->city?->name,
            ] : null;

            // نبدأ من الـ toArray علشان يحتوي باقي العلاقات اللي اتحمّلت
            $data = $shipment->toArray();

            // تأكد إن فيه كائن consignee (لو مش موجود نبنيه بسرعة)
            if (!isset($data['consignee'])) {
                $data['consignee'] = [
                    'id' => $shipment->consignee?->id,
                    'name' => $shipment->consignee?->name,
                    'phones' => [
                        'cellphone' => $shipment->consignee?->cellphone,
                        'alternate' => $shipment->consignee?->alternatePhone,
                    ],
                    'location_url' => $shipment->consignee?->location_url,
                ];
            }

            // 👇 نضيف العنوان داخل consignee.address + نطلّعه Top-level كمان
            $data['consignee']['address'] = $addressArray;
            $data['delivery_address'] = $addressArray;

            return sendResponse("Shipment fetched successfully.", $data);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while fetching the shipment.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/shipments/printShipment",
     *     tags={"OMS"},
     *     summary="Generate print-ready HTML for shipment",
     *     description="Generates a print-ready HTML view of an shipment with all its details",
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Shipment tracking number"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Print-ready HTML generated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function printShipment(Request $request)
    {
        $request->validate([
            "tracking_no" => "required|exists:shipments,tracking_no"
        ]);

        $tracking_no = request('tracking_no');
        $shipment = Shipment::with(
            "consignee",
            "consignee.city",
            "consignee.governorate",
            "consignee.state",
            "consignee.place",
            "deliveryAddress",
            "deliveryAddress.governorate",
            "deliveryAddress.state",
            "deliveryAddress.place",
            "shipper",
            "shipper.country",
            'shipper.state',
            "shipment_information",
            "shipment_information.zone",
            "shipment_items",
            "shipment_amounts",
            "merchant"
        )->where('tracking_no', $tracking_no)->first();
        // sleep(1);
        $html = view('printWaybills', ['shipment' => $shipment])->render();

        // shipmentHistory([
        //     "shipment_id" => $shipment->id,
        //     "status" => "PRINT",
        //     "description" => "Airway bill has been printed",
        //     "type" => "PRINT"
        // ]);

        return response()->json(['html' => $html]);
    }
    /**
     * @OA\Post(
     *     path="/api/shipments/printMultipleShipments",
     *     tags={"OMS"},
     *     summary="Generate print-ready HTML for multiple shipments",
     *     description="Generates a print-ready HTML view for multiple shipments in a single A4 document",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tracking_numbers"},
     *             @OA\Property(
     *                 property="tracking_numbers",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 description="Array of tracking numbers"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Print-ready HTML generated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function printMultipleShipments(Request $request)
    {
        $data = $request->validate([
            'tracking_numbers' => ['required', 'array', 'min:1'],
            'tracking_numbers.*' => ['string', 'exists:shipments,tracking_no'],
        ]);

        $trackingNumbers = $data['tracking_numbers'];

        $shipments = Shipment::with(
            'consignee',
            'consignee.city',
            'consignee.governorate',
            'consignee.state',
            'consignee.place',
            'deliveryAddress',
            'deliveryAddress.governorate',
            'deliveryAddress.state',
            'deliveryAddress.place',
            'merchant',
            'shipper',
            'shipper.country',
            'shipper.state',
            'shipment_information',
            'shipment_information.zone',
            'shipment_items',
            'shipment_amounts'
        )
            ->whereIn('tracking_no', $trackingNumbers)
            ->get()
            ->sortBy(fn($o) => array_search($o->tracking_no, $trackingNumbers))
            ->values();

        $html = view('printWaybills', ['shipments' => $shipments])->render();

        return response()->json(['html' => $html]);
    }
    // public function printMultipleShipments(Request $request)
    // {
    //     $request->validate([
    //         "tracking_numbers" => "required|array|min:1",
    //         "tracking_numbers.*" => "required|exists:shipments,tracking_no"
    //     ]);

    //     $trackingNumbers = $request->tracking_numbers;
    //     $shipments = Shipment::with([
    //         "consignee.city",
    //         "consignee.governorate",
    //         "consignee.state",
    //         "consignee.place",
    //         "shipper.country",
    //         "shipper.state",
    //         "shipment_information.zone",
    //         "shipment_amounts",
    //         "shipment_items",
    //         "merchant"
    //     ])
    //     ->whereIn('tracking_no', $trackingNumbers)
    //     ->get();

    //     if ($shipments->isEmpty()) {
    //         return response()->json(['html' => 'No shipments found'], 404);
    //     }

    //     $html = '';

    //     // Process each shipment
    //     foreach ($shipments as $index => $shipment) {
    //         // Get the full HTML for each shipment
    //         $shipmentFullHtml = view('printShipment', ['shipment' => $shipment])->render();

    //         // If this is not the first shipment, add a page break
    //         if ($index > 0) {
    //             $html .= '<div style="page-break-before: always;"></div>';
    //         }

    //         // Add the shipment HTML
    //         $html .= $shipmentFullHtml;
    //     }

    //     // Wrap everything in HTML structure
    //     $fullHtml = '<!DOCTYPE html><html><head>';

    //     // Get the head content from the first shipment
    //     $firstShipment = $shipments->first();
    //     $headView = view('printShipmentHead', ['shipment' => $firstShipment])->render();

    //     // Extract the head content (everything between <head> and </head>)
    //     preg_match('/<head[^>]*>([\s\S]*?)<\/head>/i', $headView, $headMatches);
    //     if (isset($headMatches[1])) {
    //         $fullHtml .= $headMatches[1];
    //     }

    //     // Add print-specific styles
    //     $fullHtml .= '
    //     <style>
    //         @media print {
    //             @page {
    //                 size: 100mm 150mm;
    //                 margin: 0;
    //             }
    //             body {
    //                 margin: 0;
    //                 padding: 0;
    //                 width: 100mm;
    //                 height: 148mm;
    //             }
    //             .container {
    //                 page-break-after: always;
    //                 page-break-inside: avoid;
    //                 break-after: page;
    //                 break-inside: avoid;
    //             }
    //             .container:last-child {
    //                 page-break-after: auto;
    //                 break-after: auto;
    //             }
    //         }
    //     </style>';

    //     $fullHtml .= '</head><body>';
    //     $fullHtml .= $html;
    //     $fullHtml .= '</body></html>';

    //     return response()->json(['html' => $fullHtml]);
    // }
    /**
     * @OA\Post(
     *     path="/api/shipments/assign-shipment",
     *     tags={"OMS"},
     *     summary="Assign shipment to driver with financial reconciliation",
     *     description="Assigns an shipment to a driver, updates driver runsheet, and handles financial transactions",
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Valid shipment tracking number"
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Existing driver ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment assigned successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="System error"
     *     )
     * )
     */
    public function assignShipment(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
            'driver_id' => 'required|exists:users,id',
        ]);

        $trackingNo = trim($request->tracking_no);

        DB::beginTransaction();
        try {
            $shipment = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                ->where('tracking_no', $trackingNo)
                ->firstOrFail();
            $validationService = new ShipmentValidationService();

            if ($validationService->isShipmentRTO($shipment)) {
                return sendResponse(
                    "RTO shipments cannot be assigned for delivery.",
                    [],
                    false,
                    ["Shipment is in RTO flow and must return to origin."],
                    422
                );
            }
            // Ensure assignee is a driver user
            $assignee = \App\Models\User::with('driver')->findOrFail($request->driver_id);
            if (!$assignee->driver) {
                return sendResponse(
                    "Selected user is not a driver.",
                    [],
                    false,
                    ["Only users with driver profile can be assigned."],
                    422
                );
            }
            if ($shipment->is_outsourced == 1) {
                return sendResponse(
                    "Shipment cannot be assigned because it is outsourced.",
                    [],
                    false,
                    ["Shipment is outsourced and cannot be assigned."],
                    422
                );
            }

            if ($validationService->isShipmentDelivered($shipment)) {
                return sendResponse("Shipment is already delivered.", [], false, ["Shipment is already delivered."], 422);
            }

            if ($validationService->isShipmentAssigned($shipment)) {
                return sendResponse("Shipment is already assigned.", [], false, ["Shipment is already assigned."], 422);
            }

            if ($validationService->isShipmentInRunsheet($shipment, $request->driver_id)) {
                return sendResponse("Shipment is already in runsheet.", [], false, ["Shipment is already in runsheet."], 422);
            }

            if ($validationService->isShipmentConfirmed($shipment, $request->driver_id)) {
                return sendResponse("Shipment is already confirmed.", [], false, ["Shipment is already confirmed."], 422);
            }

            if (!$shipment->shipment_information->in_warehouse) {
                return sendResponse("Shipment is not in warehouse.", [], false, ["Shipment is not in warehouse."], 422);
            }

            if ($validationService->isShipmentAssignedFromCurrentFacility($shipment)) {
                return sendResponse("Shipment is not assigned from the current facility.", [], false, ["Shipment is not assigned from the current facility."], 422);
            }

            if (!$shipment->is_sorted) {
                return sendResponse("Shipment is not sorted.", [], false, ["Shipment is not sorted."], 422);
            }

            if ($validationService->isAnShipmentToBeTransferred($shipment)) {
                return sendResponse("Shipment is a transfer shipment.", [], false, ["Shipment is a transfer shipment."], 422);
            }

            $existingAssignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)->first();

            if ($existingAssignment) {
                $existingAssignment->delete();
            }
            $driverShipmentAssignment = new DriverShipmentAssignment();
            $driverShipmentAssignment->shipment_id = $shipment->id;
            $driverShipmentAssignment->shipment_tracking_no = $shipment->tracking_no;
            $driverShipmentAssignment->driver_id = $request->driver_id;
            $driverShipmentAssignment->assigned_by = Auth::id();

            // Get timezone-aware timestamp
            $tzData = operation_now_with_tz();
            $driverShipmentAssignment->assigned_at = $tzData['timestamp'];
            $driverShipmentAssignment->timezone = $tzData['timezone'];

            $description = '';
            if (!setting('driver_confirmation_required')) {
                $driverShipmentAssignment->confirmed_at = $tzData['timestamp'];
                $status = ShipmentStatusEnum::OFD;
                $description = "Shipment is out for delivery. Driver: " . $existingAssignment->driver->name;
            } else {
                $status = ShipmentStatusEnum::DISPATCH;
                $description = "Shipment Dispatched by: " . Auth::user()->name . " to: " . $driverShipmentAssignment->driver->name;
            }

            $driverShipmentAssignment->save();

            $shipment->driver_id = $request->driver_id;
            $shipment->assignment_id = $driverShipmentAssignment->id;

            if (!setting('driver_confirmation_required')) {
                $status = ShipmentStatusEnum::OFD;
                $description = "Shipment is out for delivery. Driver: " . $assignee->name;
            } else {
                $status = ShipmentStatusEnum::DISPATCH;
                $description = "Shipment Dispatched by: " . Auth::user()->name . " to: " . $assignee->name;
            }
            $shipment->shipment_delivery->ofd_count = $shipment->shipment_delivery->ofd_count + 1;
            $shipment->shipment_delivery->save();
            $shipment->is_sorted = false;
            $shipment->save();

            $historyData = [
                "status" => status($status)['label'],
                "description" => $description,
                "shipment_id" => $shipment->id,

            ];


            // Get timezone-aware timestamp
            $timestampData = operation_now_with_tz();
            $timezone = $timestampData['timezone'];

            $driverRunsheet = DriverRunsheet::where('driver_id', $request->driver_id)
                ->where('created_at', '>=', $timestampData['timestamp']->subDay())
                ->where('status', '=', "pending")
                ->latest()
                ->first();

            if (!$driverRunsheet) {
                $driverRunsheet = DriverRunsheet::create([
                    "driver_id" => $request->driver_id,
                    "timezone" => $timezone,
                    "status" => 'pending'
                ]);
            }

            if ($validationService->isShipmentInRunsheet($shipment, $request->driver_id)) {
                return sendResponse("Shipment is already in runsheet.", [], false, ["Shipment is already in runsheet."], 422);
            } else {
                $runsheet_shipment = DriverRunsheetShipment::create([
                    "runsheet_id" => $driverRunsheet->id,
                    "driver_id" => $request->driver_id,
                    "shipment_tracking_no" => $shipment->tracking_no,
                    "timezone" => $timezone,
                    "status" => "assigned",
                ]);
            }


            // $transactionAmount = $shipment->amount ?? 0;
            $transactionAmount = $this->calcDriverReceivableForShipment($shipment);

            $user = Auth::user();

            $facilityAccount = Account::firstOrCreate(
                [
                    'accountable_id' => $user->owner_id,
                    'accountable_type' => $user->owner_type,
                ]
            );

            $driverAccount = Account::firstOrCreate(
                [
                    'accountable_id' => $request->driver_id,
                    'accountable_type' => User::class,
                ]
            );

            $facilityAccount->parcel_value -= $transactionAmount;
            $driverAccount->parcel_value += $transactionAmount;

            $facilityAccount->save();
            $driverAccount->save();

            Transaction::create([
                'from_id' => $user->owner_id,
                'from_type' => $user->owner_type,
                'to_id' => $driverAccount->accountable_id,
                'to_type' => User::class,
                'shipment_id' => $shipment->id,
                'amount' => $transactionAmount,
                'type' => 'assignment',
                'description' => 'Assignment transaction for shipment ' . $shipment->tracking_no,
            ]);



            shipmentHistory($historyData);

            updateShipmentStatus($shipment->id, status($status)['label']);

            $driver_shipments = DriverShipmentAssignment::where('driver_id', $request->driver_id)
                ->with('shipment', 'driver', 'assigned_by')
                ->whereNull('returned_at')
                ->whereNull('confirmed_at')
                ->whereNull('delivered_at')
                ->get();

            $todayAssignmentsCount = DriverShipmentAssignment::where('driver_id', $request->driver_id)
                ->whereDate('assigned_at', now()->toDateString())
                ->count();

            activityLog("shipment_assigned", "Shipment has been #{$shipment->tracking_no} assigned to {$driverShipmentAssignment->driver->name}");

            // ---------------------------------------------------------
            // NEW LOGIC: Link to Merchant Pickup Task (if applicable)
            // ---------------------------------------------------------
            $pickupTaskData = null; // To hold data for the response

            // 1. Find the most relevant open PickupRequest for this merchant
            $pickupRequest = \App\Models\PickupRequest::where('merchant_user_id', $shipment->merchant_id)
                ->whereIn('status', ['pending', 'assigned', 'in_progress'])
                ->latest()
                ->first();

            if ($pickupRequest) {
                // 2. Find or create a MerchantPickupTask
                // Match by: merchant_id, driver_id, pickup_request_id
                // We use 'to_pickup' as the status for active tasks
                $pickupTask = \App\Models\MerchantPickupTask::firstOrCreate(
                    [
                        'merchant_id' => $shipment->merchant_id,
                        'driver_id' => $request->driver_id,
                        'pickup_request_id' => $pickupRequest->id,
                    ],
                    [
                        'no_of_shipments' => $pickupRequest->shipments_count,
                        'status' => 'to_pickup',
                        'note' => null,
                    ]
                );

                // 3. Link the shipment to this task (avoid duplicates)
                \App\Models\MerchantPickupShipment::firstOrCreate(
                    [
                        'pickup_task_id' => $pickupTask->id,
                        'shipment_id' => $shipment->id,
                    ],
                    [
                        'merchant_id' => $shipment->merchant_id,
                        'driver_id' => $request->driver_id,
                        'pickup_request_id' => $pickupRequest->id,
                        'shipment_tracking_no' => $shipment->tracking_no,
                        'pre_id' => $shipment->pre_id,
                        'status' => 'to_pickup',
                    ]
                );

                // 4. Recalculate registered_shipments_no (if you want to store it on the task, or just for response)
                // If the model has a 'registered_shipments_no' column, update it.
                // Based on previous context, it might be an appended attribute, but if we need to persist it:
                // $pickupTask->registered_shipments_no = $pickupTask->shipments()->count();
                // $pickupTask->save();
                // (Assuming it's calculated on the fly or not a DB column based on the model definition seen earlier)

                // Prepare data for response
                $pickupTaskData = [
                    'pickup_task_id' => $pickupTask->id,
                    'pickup_request_id' => $pickupRequest->id,
                    'pickup_request_ref' => $pickupRequest->ref,
                    'registered_shipments_no' => $pickupTask->shipments()->count(),
                    'total_shipments_no' => (int) $pickupTask->no_of_shipments,
                    'picked_shipments_no' => $pickupTask->picked_shipments_no,
                ];
            }

            DB::commit();

            // Merge the new data into the response
            $responseData = new ShipmentResource(["driver_shipments" => $driver_shipments, "count" => $todayAssignmentsCount]);
            $responseArray = $responseData->response()->getData(true); // Get array representation

            if ($pickupTaskData) {
                $responseArray['data'] = array_merge($responseArray['data'] ?? [], $pickupTaskData);
                return response()->json($responseArray);
            }

            return sendResponse("Shipment assigned successfully.", $responseData);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while assigning the shipment.", [], false, [$e->getMessage()], 500);
        }
    }

    public function sendAssignNotification(Request $request)
    {
        // Validation: either driver_id (send to topic) or token (send to single device)
        $request->validate([
            'driver_id' => 'required_without:token|nullable|exists:users,id',
            'token' => 'required_without:driver_id|nullable|string',
            'body' => 'nullable|string|max:500',
        ]);

        try {
            // If driver_id is provided, use the DriverRunsheetNotificationService
            if ($request->filled('driver_id')) {
                $driver = User::with('driver')->findOrFail($request->input('driver_id'));
                if (!$driver->driver) {
                    return sendResponse('Selected user is not a driver.', [], false, ['User has no driver profile.'], 422);
                }

                // Use the service to send FCM with unconfirmed count
                $runsheetService = resolve(\App\Services\DriverRunsheetNotificationService::class);
                $runsheetService->notifyDriverUnconfirmedShipments($driver->id);

                return sendResponse('Notification sent successfully.', [
                    'sent_to' => 'driver_' . $driver->id,
                    'type' => 'runsheet_update',
                ]);
            }

            // If token is provided, send to specific device
            if ($request->filled('token')) {
                $baseTitle = 'تم إسناد شحنة جديدة إليك';
                $nowHuman = now()->format('Y-m-d H:i');
                $title = "{$baseTitle} | {$nowHuman}";
                $body = (string) $request->input('body', '');

                $data = [
                    'type' => 'shipment_assigned',
                    'sent_at' => now()->toIso8601String(),
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ];

                /** @var \App\Services\FcmService $fcm */
                $fcm = resolve(\App\Services\FcmService::class);
                $fcm->sendToToken($request->input('token'), $title, $body, $data);

                return sendResponse('Notification sent (token).', [
                    'sent_to' => 'token',
                    'payload' => compact('title', 'body', 'data'),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('FCM sendAssignNotification failed: ' . $e->getMessage());
            return sendResponse('Failed to send notification.', [], false, [$e->getMessage()], 500);
        }
    }
    public function unassignShipment(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
            'driver_id' => 'required|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $trackingNo = trim($request->tracking_no);

            $shipment = Shipment::with(['shipment_information', 'shipment_delivery'])
                ->where('tracking_no', $trackingNo)
                ->firstOrFail();

            if (in_array($shipment->status, ['DELIVERY_EXCEPTION', 'DELIVERED'], true)) {
                return sendResponse(
                    "Cannot unassign shipment in its current status.",
                    [],
                    false,
                    ["Shipment status '{$shipment->status}' does not allow unassignment."],
                    422
                );
            }

            $assignee = \App\Models\User::with('driver')->findOrFail($request->driver_id);
            if (!$assignee->driver) {
                return sendResponse(
                    "Selected user is not a driver.",
                    [],
                    false,
                    ["Only users with driver profile can be unassigned."],
                    422
                );
            }

            $assignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->where('driver_id', $request->driver_id)
                ->whereNull('returned_at')
                ->whereNull('delivered_at')
                ->latest()
                ->first();

            if (!$assignment) {
                return sendResponse(
                    "No active assignment found for this driver and shipment.",
                    [],
                    false,
                    ["Shipment is not assigned to the provided driver or already closed."],
                    422
                );
            }

            if (!setting('driver_confirmation_required')) {
                return sendResponse(
                    "Cannot unassign after OFD (auto-confirm mode).",
                    [],
                    false,
                    ["Use return/cancel workflow instead of unassign in auto-confirm mode."],
                    422
                );
            }

            if (!is_null($assignment->confirmed_at)) {
                $shipment->shipment_information->in_warehouse = true;
                $shipment->shipment_information->save();
            }
            $runsheets = DriverRunsheet::where('driver_id', $request->driver_id)
                ->where('status', 'pending')
                ->whereHas('shipments', function ($q) use ($shipment) {
                    $q->where('shipment_tracking_no', $shipment->tracking_no);
                })
                ->get();

            foreach ($runsheets as $sheet) {
                DriverRunsheetShipment::where('runsheet_id', $sheet->id)
                    ->where('shipment_tracking_no', $shipment->tracking_no)
                    ->delete();

                $remaining = DriverRunsheetShipment::where('runsheet_id', $sheet->id)->count();
                if ($remaining === 0) {
                    $sheet->delete();
                }
            }

            $driverRunsheet = DriverRunsheet::where('driver_id', $request->driver_id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($driverRunsheet) {
                DriverRunsheetShipment::where('runsheet_id', $driverRunsheet->id)
                    ->where('shipment_tracking_no', $shipment->tracking_no)
                    ->delete();
            }

            $transactionAmount = $this->calcDriverReceivableForShipment($shipment);

            $user = Auth::user();

            $facilityAccount = Account::firstOrCreate([
                'accountable_id' => $user->owner_id,
                'accountable_type' => $user->owner_type,
            ]);

            $driverAccount = Account::firstOrCreate([
                'accountable_id' => $request->driver_id,
                'accountable_type' => User::class,
            ]);

            $facilityAccount->parcel_value += $transactionAmount;
            $driverAccount->parcel_value -= $transactionAmount;
            $facilityAccount->save();
            $driverAccount->save();

            Transaction::create([
                'from_id' => $driverAccount->accountable_id,
                'from_type' => User::class,
                'to_id' => $user->owner_id,
                'to_type' => $user->owner_type,
                'shipment_id' => $shipment->id,
                'amount' => $transactionAmount,
                'type' => 'assignment_reversal',
                'description' => 'Unassignment reversal for shipment ' . $shipment->tracking_no,
            ]);

            if ($shipment->shipment_delivery && $shipment->shipment_delivery->ofd_count > 0) {
                $shipment->shipment_delivery->ofd_count -= 1;
                $shipment->shipment_delivery->save();
            }

            $assignment->delete();

            $shipment->driver_id = null;
            $shipment->assignment_id = null;
            $shipment->is_sorted = true;
            $shipment->save();

            $status = ShipmentStatusEnum::SORTED;
            $statusLabel = status($status)['label'] ?? $status;
            $historyData = [
                "status" => $statusLabel,
                "description" => "Assignment revoked by: " . Auth::user()->name . " from driver: " . $assignee->name,
                "shipment_id" => $shipment->id,
            ];

            shipmentHistory($historyData);
            updateShipmentStatus($shipment->id, $statusLabel);

            $driver_shipments = DriverShipmentAssignment::where('driver_id', $request->driver_id)
                ->with('shipment', 'driver', 'assigned_by')
                ->whereNull('returned_at')
                ->whereNull('confirmed_at')
                ->whereNull('delivered_at')
                ->get();

            $todayAssignmentsCount = DriverShipmentAssignment::where('driver_id', $request->driver_id)
                ->whereDate('assigned_at', now()->toDateString())
                ->count();

            activityLog("shipment_unassigned", "Shipment #{$shipment->tracking_no} unassigned from {$assignee->name}");

            DB::commit();
            return sendResponse(
                "Shipment unassigned successfully.",
                new ShipmentResource(["driver_shipments" => $driver_shipments, "count" => $todayAssignmentsCount])
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse(
                "An error occurred while unassigning the shipment.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }


    /**
     * @OA\Post(
     *     path="/api/shipments/confirm-assign-shipment",
     *     tags={"OMS"},
     *     summary="Confirm driver assignment and update shipment status to OFD",
     *     description="Confirms a driver's assignment to an shipment, updates status to OFD, and generates delivery OTP",
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Valid shipment tracking number"
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Existing driver ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment confirmed successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="System error"
     *     )
     * )
     */
    public function confirmAssignShipment(Request $request)
    {
        $request->validate([
            'tracking_no' => 'required|exists:shipments,tracking_no',
        ]);

        $trackingNo = trim($request->tracking_no);

        try {
            DB::beginTransaction();

            $user = auth()->user();
            $driverId = $user->id;

            $shipment = Shipment::withoutGlobalScope(ExcludeReturnShipmentsScope::class)->where('tracking_no', $trackingNo)
                ->firstOrFail();



            $validationService = new ShipmentValidationService();

            $existingAssignment = DriverShipmentAssignment::where('shipment_id', $shipment->id)
                ->where('driver_id', $driverId)
                ->first();

            if (!$validationService->isShipmentAssigned($shipment, $driverId) || !$existingAssignment) {
                return sendResponse("Shipment is not assigned to this driver.", [], false, ["Shipment is not assigned."], 422);
            }

            if ($validationService->isShipmentConfirmed($shipment, $driverId)) {
                return sendResponse("Shipment is already confirmed.", [], false, ["Shipment is already confirmed."], 422);
            }

            if ($validationService->isShipmentDelivered($shipment)) {
                return sendResponse("Shipment is already delivered.", [], false, ["Shipment is already delivered."], 422);
            }

            if (!$validationService->isShipmentInRunsheet($shipment, $driverId)) {
                return sendResponse("Shipment is not in runsheet.", [], false, ["Shipment is not in runsheet."], 422);
            }

            if ($validationService->isShipmentInException($shipment)) {
                return sendResponse("Shipment is in exception.", [], false, ["Shipment is in exception."], 422);
            }

            if ($existingAssignment->confirmed_at) {
                return sendResponse("This shipment is already confirmed.", [], false, [], 422);
            }

            $runsheet_shipment = DriverRunsheetShipment::where('shipment_tracking_no', $shipment->tracking_no)
                ->where('status', 'assigned');


            if (!$runsheet_shipment) {
                return sendResponse("This shipment is not assigned to a driver.", [], false, [], 422);
            }



            $existingAssignment->confirmed_at = now();
            $existingAssignment->save();

            $status = ShipmentStatusEnum::OFD;
            $shipment->shipment_delivery->ofd_count = $shipment->shipment_delivery->ofd_count + 1;

            $shipment->shipment_information->in_warehouse = false;
            $shipment->shipment_information->save();

            $shipment->driver_id = $driverId;
            $shipment->in_exception = false;

            $shipment->owner_type = null;
            $shipment->owner_id = null;

            $shipment->save();

            $historyData = [
                "status" => status($status)['label'],
                "description" => status($status)['description'] . " Driver: " . $existingAssignment->driver->name,
                "shipment_id" => $shipment->id,
            ];

            shipmentHistory($historyData);

            updateShipmentStatus($shipment->id, status($status)['label']);

            $shipment->runsheet_shipment->update([
                "status" => "confirmed",
            ]);

            $shipment->shipment_delivery->update([
                "delivery_otp" => generate_otp(),
                "otp_generated_at" => now()
            ]);

            $shipment->shipment_delivery->save();

            // Generate address update token for consignee
            try {
                $addressService = new AddressService();
                $tokenData = $addressService->generateAddressUpdateToken($shipment);

                // Store the update URL in the shipment or consignee for later use in notifications
                $shipment->consignee->address_update_url = $tokenData['url'];
                $shipment->consignee->save();

                activityLog("address_token_generated", "Address update token generated for shipment #{$shipment->tracking_no}");
            } catch (Exception $e) {
                // Log the error but don't fail the entire confirmation process
                Log::error("Failed to generate address update token for shipment {$shipment->tracking_no}: " . $e->getMessage());
            }
            // $shipment->consignee->notify(new ConsigneeOFDQRNotification($shipment));
            $shipment->consignee->notify(new ConsigneeOFDNotification($shipment));
            activityLog("shipment_confirmed", "Shipment #{$shipment->tracking_no} has been  confirmed by {$existingAssignment->driver->name}");
            try {
                // لازم يكون السواق اللي أكَّد هو المربوط على الأوردر
                if ((int) $shipment->driver_id !== (int) $driverId) {
                    $shipment->driver_id = $driverId;
                    $shipment->save();
                }

                // حدّد الولاية
                $stateId = $shipment->state_id
                    ?? optional($shipment->consignee)->state_id
                    ?? null;

                if ($stateId) {
                    // منشأة المستخدم الحالي (Station/Hub/Branch)
                    $facilityType = auth()->user()->owner_type;
                    $facilityId = auth()->user()->owner_id;

                    // هات سطر البونص (مخصص للمنشأة أولاً ثم fallback عام)
                    // $bonusRow = DriverBonus::query()
                    //     ->where('driver_id', $driverId)
                    //     ->where('state_id', $stateId)
                    //     ->where(function ($q) use ($facilityType, $facilityId) {
                    //         $q->where(function ($q1) use ($facilityType, $facilityId) {
                    //             $q1->where('owner_type', $facilityType)
                    //                 ->where('owner_id', $facilityId);
                    //         })
                    //             ->orWhere(function ($q2) {
                    //                 $q2->whereNull('owner_type')->whereNull('owner_id');
                    //             });
                    //     })
                    //     ->first();

                    // $pickupBonus = (float) ($bonusRow->pickup_bonus ?? 0);

                    // if ($pickupBonus > 0) {
                    //     // امنع التكرار (نفس المفتاح المستخدم في shipment_pickup)
                    //     $ref = 'BON-PU-' . $shipment->tracking_no;
                    //     $exists = Transaction::where('reference', $ref)
                    //         ->where('type', 'bonus_credit')
                    //         ->exists();

                    //     if (!$exists) {
                    //         Transaction::create([
                    //             'from_id' => $facilityId,
                    //             'from_type' => $facilityType,
                    //             'to_id' => $driverId,
                    //             'to_type' => User::class,

                    //             'shipment_id' => $shipment->id,
                    //             'amount' => $pickupBonus,
                    //             'type' => 'bonus_credit',
                    //             'reference' => $ref,
                    //             'description' => "Pickup bonus for {$shipment->tracking_no}",

                    //             'created_by' => auth()->id(),
                    //             'warehouse_id' => $facilityId, // لو العمود موجود
                    //         ]);
                    //     }
                    // }
                }
            } catch (\Throwable $e) {
                Log::warning('Pickup bonus credit on confirm failed for ' . $shipment->tracking_no . ' : ' . $e->getMessage());
            }
            DB::commit();
            return sendResponse("Shipment confirmed successfully.", new ShipmentResource($shipment));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return sendResponse("Shipment not found.", [], [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while confirming the shipment.", [], false, [$e->getMessage()], 500);
        }
    }
    public function confirmAllAssigned(Request $request)
    {

        try {
            DB::beginTransaction();

            $user = auth()->user();

            if (!$user || !$user->roles->contains('name', 'Driver')) {
                return sendResponse(
                    "Unauthorized.",
                    [],
                    false,
                    ["Only drivers can confirm shipments."],
                    403
                );
            }

            $driverId = $user->id;
            // $validationService = new ShipmentValidationService(); // Optimized: manual checks
            $confirmedShipments = [];

            // 1. Fetch eligible assignments with eager loading
            $assignments = DriverShipmentAssignment::where('driver_id', $driverId)
                ->whereNull('confirmed_at')
                ->whereNull('returned_at')
                ->whereNull('delivered_at')
                ->with([
                    'shipment' => function ($q) {
                        $q->with(['shipment_delivery', 'shipment_information', 'consignee']);
                    },
                    'driver'
                ])
                ->get();

            if ($assignments->isEmpty()) {
                // Return success if nothing to confirm to avoid error UX, or keep 422?
                // Original logic returned 422 if empty. Sticking to original behavior but checking logic.
                // If the app expects success when "Confirm All" is clicked even if list is empty (UI sync issue), 200 is better.
                // But following established pattern:
                return sendResponse("No eligible shipments found for confirmation.", [], false, ["No shipments assigned to this driver."], 422);
            }

            // 2. Bulk fetch Runsheet Data for validation
            // We need to know if shipment is in runsheet (assigned/confirmed/delivered) to validate connection
            $trackingNumbers = $assignments->pluck('shipment.tracking_no')->filter()->toArray();

            $runsheetItems = DriverRunsheetShipment::where('driver_id', $driverId)
                ->whereIn('shipment_tracking_no', $trackingNumbers)
                ->get()
                ->keyBy('shipment_tracking_no');

            foreach ($assignments as $assignment) {
                $shipment = $assignment->shipment;

                // --- VALIDATION CHECKS (Memory-based) ---

                // Skip if no shipment loaded
                if (!$shipment) {
                    continue;
                }

                // Check status: Must be DISPATCH
                if ($shipment->status !== 'DISPATCH') {
                    continue;
                }

                // Check if already delivered
                if (strtolower($shipment->status) === 'delivered') {
                    continue;
                }

                // Check Runsheet Status
                $runsheetItem = $runsheetItems->get($shipment->tracking_no);

                // Must be in runsheet
                if (!$runsheetItem) {
                    continue;
                }

                // Must not be 'returned' (should be assigned, confirmed, or delivered - logically 'assigned' for confirmation)
                if ($runsheetItem->status === 'returned') {
                    continue;
                }

                // Check if already confirmed (Runsheet level)
                if ($runsheetItem->status === 'confirmed') {
                    continue; // Already confirmed
                }

                // Original Validation: isShipmentInRunsheet checks for ['assigned', 'confirmed', 'delivered']
                // We want to confirm 'assigned' ones.

                // Check Exception
                if ($shipment->in_exception) {
                    continue;
                }

                // --- UPDATE LOGIC ---

                // 1. Mark assignment as confirmed
                $assignment->confirmed_at = now();
                $assignment->save();

                // 2. Update Shipment
                $status = ShipmentStatusEnum::OFD;
                if ($shipment->shipment_delivery) {
                    $shipment->shipment_delivery->ofd_count = ($shipment->shipment_delivery->ofd_count ?? 0) + 1;
                    $shipment->shipment_delivery->save(); // Save relation
                }

                if ($shipment->shipment_information) {
                    $shipment->shipment_information->in_warehouse = false;
                    $shipment->shipment_information->save();
                }

                $shipment->driver_id = $driverId;
                $shipment->in_exception = false;
                $shipment->owner_type = null;
                $shipment->owner_id = null;
                $shipment->save();

                // 3. History
                $historyData = [
                    "status" => status($status)['label'],
                    "description" => status($status)['description'] . " Driver: " . $assignment->driver->name,
                    "shipment_id" => $shipment->id,
                ];
                shipmentHistory($historyData);
                updateShipmentStatus($shipment->id, status($status)['label']);

                // 4. Update Runsheet Item
                // We already fetched $runsheetItem
                if ($runsheetItem->status !== 'confirmed') {
                    $runsheetItem->update([
                        "status" => "confirmed",
                    ]);
                }

                // 5. OTP & Tokens
                if ($shipment->shipment_delivery) {
                    $shipment->shipment_delivery->update([
                        "delivery_otp" => generate_otp(),
                        "otp_generated_at" => now()
                    ]);
                }

                try {
                    $addressService = new AddressService();
                    $tokenData = $addressService->generateAddressUpdateToken($shipment);
                    if ($shipment->consignee) {
                        $shipment->consignee->address_update_url = $tokenData['url'];
                        $shipment->consignee->save();
                    }
                    activityLog("address_token_generated", "Address update token generated for shipment #{$shipment->tracking_no}");
                } catch (Exception $e) {
                    Log::error("Failed to generate address update token for shipment {$shipment->tracking_no}: " . $e->getMessage());
                }

                // 6. Notify Consignee
                // Dispatch notification (Ensure it's queued if possible, or accept overhead for now but DB is faster)
                if ($shipment->consignee) {
                    try {
                        $shipment->consignee->notify(new ConsigneeOFDNotification($shipment));
                    } catch (\Throwable $ex) {
                        Log::error('Confirmation Notification failed: ' . $ex->getMessage());
                    }
                }

                activityLog("shipment_confirmed", "Shipment #{$shipment->tracking_no} has been confirmed by {$assignment->driver->name}");

                // Driver ID Consistency Check
                if ((int) $shipment->driver_id !== (int) $driverId) {
                    $shipment->driver_id = $driverId;
                    $shipment->save();
                }

                $confirmedShipments[] = $shipment;
            }

            if (empty($confirmedShipments)) {
                DB::rollBack();
                return sendResponse("No eligible shipments found for confirmation.", [], false, ["No shipments were eligible for confirmation."], 422);
            }

            DB::commit();
            return sendResponse("All eligible shipments confirmed successfully.", ShipmentResource::collection($confirmedShipments));
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An error occurred while confirming the shipments.", [], false, [$e->getMessage()], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/shipments/history",
     *     tags={"OMS"},
     *     summary="Get merchant's shipment history with filtering",
     *     description="Retrieves paginated list of merchant's shipments with optional filtering by search term, status, and date range",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Search term for tracking number"
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Filter by shipment status"
     *     ),
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Start date filter"
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="End date filter"
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         ),
     *         description="Page number"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No shipments found"
     *     )
     * )
     */
    public function history(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $date_start = $request->query('date_start');
        $date_end = $request->query('date_end');
        $page = $request->query('page', 1);
        $user = Auth::user();
        $shipmentsQuery = Shipment::byOwner();
        if ($user->merchant) {
            $merchantId = $user->merchant->id;
            $shipmentsQuery->where('merchantId', $merchantId);
        }

        if ($search) {
            $shipmentsQuery->where('tracking_no', 'like', "%{$search}%");
        }

        if ($status) {
            $shipmentsQuery->where('status', $status);
        }

        if ($date_start) {
            $shipmentsQuery->whereDate('created_at', '>=', $date_start);
        }

        if ($date_end) {
            $shipmentsQuery->whereDate('created_at', '<=', $date_end);
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

            'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone',
            'consignee.country:id,name',
            'consignee.governorate:id,en_name,ar_name',
            'consignee.state:id,en_name,ar_name',
            'consignee.place:id,en_name,ar_name',

            'shipment_information:id,shipment_id,zone_id',
            'shipment_information.zone:id,name',

            'shipmentHistories',
            'shipment_items',
            'shipment_delivery',
            'transactions',
            'assigned_to_shelf.shelf:id,barcode,shelf_barcode'
        ]);

        $shipments = $shipmentsQuery->orderByDesc('id')->paginate(10);

        if ($shipments->isEmpty()) {
            return sendResponse("No Record.", [], false, ['no record']);
        }

        return sendResponse("Shipments retrieved successfully.", [
            'data' => new ShipmentResource($shipments),
            'links' => $shipments->links()
        ]);
    }

    public function get_merchant_created_shipments()
    {
        $shipments = Shipment::whereHas("merchant")->where('status', 'CREATED')
            // Exclude pending customer shipments
            ->where(function ($query) {
                $query->whereNotNull('owner_id')
                    ->orWhereNotNull('owner_type');
            });

        $shipments = $shipments->with([
            'shipper.country',
            'shipper.state',
            'merchant.merchant.country',
            'merchant.merchant.governorate',
            'merchant.merchant.state',
            'merchant.merchant.place',
            'consignee.country',
            'consignee.governorate',
            'consignee.state',
            'consignee.place',
            'shipment_information.zone',
            'shipmentHistories',
            'shipment_amounts',
            'shipment_items',
            'shipment_delivery',
            'shipment.assigned_to_shelf.shelf',
        ]);

        if (request()->has('status') && request()->input('status') !== 'All') {
            $status = request()->input('status');
            $shipments = $shipments->where('status', $status);
        }

        $shipmentsCount = Shipment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        if (request()->input('status') === 'All') {
            $shipmentsCount['All'] = Shipment::count();
        }

        $shipments = $shipments->orderBy('id', 'desc')->paginate(500);

        if ($shipments->isEmpty()) {
            return sendResponse("No Record.", [], false, ['no record']);
        }

        return sendResponse("Shipments retrieved successfully.", [
            'shipments' => new ShipmentResource($shipments),
            'status_counts' => $shipmentsCount
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/shipments/history/export",
     *     tags={"OMS"},
     *     summary="Export shipment history to CSV or PDF",
     *     description="Exports filtered shipment history with selected columns to CSV or PDF format",
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"csv", "pdf"},
     *             default="csv"
     *         ),
     *         description="Export format (csv or pdf)"
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="string",
     *                 enum={"tracking_no", "created_at", "total_cod", "status", "updated_at"}
     *             )
     *         ),
     *         description="Columns to include in export",
     *         style="form",
     *         explode=true
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Search term for tracking number"
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Filter by shipment status"
     *     ),
     *     @OA\Parameter(
     *         name="date_start",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         ),
     *         description="Start date filter"
     *     ),
     *     @OA\Parameter(
     *         name="date_end",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         ),
     *         description="End date filter"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File downloaded successfully",
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="File name",
     *             @OA\Schema(
     *                 type="string"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="error"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Invalid format"
     *             )
     *         )
     *     )
     * )
     */
    public function exportHistoryShipment(Request $request)
    {
        $format = $request->input('format', 'csv');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format', [], false, null, 422);
        }
        $availableColumns = [
            'tracking_no' => 'Tracking Number',
            'created_at' => 'Shipment Date',
            'total_cod' => 'Total Amount',
            'status' => 'Status',
            'updated_at' => 'Delivery Date'
        ];
        $selectedColumns = $request->input('columns', array_keys($availableColumns));
        $columns = array_intersect_key($availableColumns, array_flip($selectedColumns));
        $query = Shipment::with([
            'shipmentDelivery',
            'shipmentStatus'
        ])
            // Exclude pending customer shipments from export
            ->where(function ($q) {
                $q->whereNotNull('owner_id')
                    ->orWhereNotNull('owner_type');
            });

        if ($request->has('search')) {
            $query->where('tracking_no', 'like', '%' . $request->search . '%');
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has(['date_start', 'date_end'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->date_start)->startOfDay(),
                Carbon::parse($request->date_end)->endOfDay()
            ]);
        }
        $shipments = $query->get();
        if ($format === 'pdf') {
            $html = view('exports.shipmentHistory', compact('columns', 'shipments'))->render();
            return response()->json(['html' => $html]);
        }
        return Excel::download(
            new ShipmentHistoryExport($shipments, array_keys($columns)),
            'shipment_history.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }
    /**
     * @OA\Post(
     *     path="/api/shipments/import",
     *     tags={"OMS"},
     *     summary="Import shipments from CSV/XLSX file",
     *     description="Imports shipments from a CSV or XLSX file with optional preview and address validation",
     *     @OA\Parameter(
     *         name="confirm_import",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean",
     *             default=false
     *         ),
     *         description="Confirm import after preview (true to proceed with import)"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="CSV or XLSX file containing shipments data"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import successful"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or missing data"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="System error"
     *     )
     * )
     */

    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,csv',
                'confirm_import' => 'nullable|boolean',
                'is_outsourced' => 'nullable|boolean',
                'batch_size' => 'nullable|integer|min:5|max:200',
            ]);

            $batchSize = $request->input('batch_size', 50);

            if (!$request->confirm_import) {
                $importer = new ShipmentImportPreview($request->boolean('is_outsourced', false));
                try {
                    Excel::import($importer, $request->file('file'));
                    $import_data = $importer->getPreviewData();

                    $totalRecords = count($import_data['importableShipments']) + count($import_data['unimportableShipments']);
                    $import_data['total_records'] = $totalRecords;
                    $import_data['batch_size'] = $batchSize;
                    $import_data['total_batches'] = ceil($totalRecords / $batchSize);
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'Missing columns')) {
                        $missing = str_replace('Missing columns: ', '', $e->getMessage());
                        return sendResponse(
                            "Validation failed",
                            ['missing_columns' => explode(', ', $missing)],
                            false,
                            ['Excel file is missing required columns'],
                            422
                        );
                    }
                    Log::error('Shipment Import Preview Error: ' . $e->getMessage());
                    return sendResponse("Error processing file", [], false, [$e->getMessage()], 422);
                }

                $addresses = [
                    'countries' => $import_data['missingCountries'] ?? [],
                    'governorates' => $import_data['missingGovernorates'] ?? [],
                    'states' => $import_data['missingStates'] ?? [],
                    'commissions' => $import_data['commissions'] ?? [],
                ];

                $hasMissingData = count($addresses['countries']) > 0
                    || count($addresses['governorates']) > 0
                    || count($addresses['states']) > 0
                    || count($addresses['commissions']) > 0;

                if ($hasMissingData) {
                    return sendResponse(
                        "Confirmation required for import",
                        [
                            'addresses' => $addresses,
                            'data' => $import_data
                        ],
                        false,
                        ["Some channels or commissions are missing. Please create them or confirm to proceed."],
                        422
                    );
                }

                return sendResponse(
                    "Import preview generated",
                    ['data' => $import_data],
                    true
                );
            }

            return $this->processBatchImport($request, $batchSize);
        } catch (ValidationException $e) {
            return sendResponse("Validation failed", [], false, $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Shipment Import System Error: ' . $e->getMessage());
            return sendResponse("System error occurred", [], false, [$e->getMessage()], 500);
        }
    }

    private function processBatchImport(Request $request, $batchSize = 50)
    {
        try {
            $file = $request->file('file');
            $isOutsourced = $request->boolean('is_outsourced', false);
            $data = Excel::toArray([], $file);
            $rows = $data[0];
            $header = array_shift($rows);
            $totalRows = count($rows);
            $totalBatches = ceil($totalRows / $batchSize);
            $fileUrl = uploadFile($file, 'public/temp_imports');

            if (!$fileUrl) {
                throw new Exception("Failed to upload file to S3");
            }
            $sessionId = 'import_' . uniqid() . '_' . time();
            $importSession = [
                'session_id' => $sessionId,
                'total_rows' => $totalRows,
                'total_batches' => $totalBatches,
                'processed_rows' => 0,
                'processed_batches' => 0,
                'batch_size' => $batchSize,
                'is_outsourced' => $isOutsourced,
                'file_url' => $fileUrl,
                'header' => $header,
                'results' => [
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => []
                ],
                'created_at' => now(),
                'updated_at' => now()
            ];

            Cache::put($sessionId, $importSession, 3600);

            Log::info("Batch import session created", [
                'session_id' => $sessionId,
                'total_batches' => $totalBatches,
                'total_rows' => $totalRows
            ]);

            return sendResponse(
                "Import started",
                [
                    'total_batches' => $totalBatches,
                    'total_rows' => $totalRows,
                    'batch_size' => $batchSize,
                    'session_id' => $sessionId
                ],
                true
            );
        } catch (Exception $e) {
            Log::error('Batch Import Init Error: ' . $e->getMessage());
            return sendResponse("Failed to start import", [], false, [$e->getMessage()], 500);
        }
    }

    public function processBatch(Request $request)
    {
        set_time_limit(300);
        try {
            $sessionId = $request->input('session_id');

            if (!$sessionId) {
                return sendResponse("Session ID required", [], false, ["Session ID is missing"], 400);
            }

            $session = Cache::get($sessionId);

            if (!$session) {
                return sendResponse("No active import session", [], false, ["Import session expired or not found"], 404);
            }

            $batchNumber = $request->input('batch', 1);
            $batchSize = $session['batch_size'];
            $start = ($batchNumber - 1) * $batchSize;

            Log::info("Processing batch", [
                'session_id' => $sessionId,
                'batch_number' => $batchNumber,
                'batch_size' => $batchSize
            ]);

            $tempPath = downloadFromS3($session['file_url']);

            try {
                $fileExtension = pathinfo($session['file_url'], PATHINFO_EXTENSION);
                $readerType = null;

                if ($fileExtension === 'xlsx') {
                    $readerType = \Maatwebsite\Excel\Excel::XLSX;
                } elseif ($fileExtension === 'csv') {
                    $readerType = \Maatwebsite\Excel\Excel::CSV;
                }

                $rows = Excel::toArray([], $tempPath, null, $readerType);
                $allRows = $rows[0];
                $header = $session['header'];
                array_shift($allRows); // Remove header row from data

                $batchRows = array_slice($allRows, $start, $batchSize);

                if (empty($batchRows)) {
                    Cache::forget($sessionId);
                    return sendResponse(
                        "Import completed",
                        [
                            'completed' => true,
                            'results' => $session['results']
                        ],
                        true
                    );
                }

                // ============================================
                // FIX: Convert indexed arrays to associative arrays with headers
                // ============================================
                $importer = new ShipmentImport($session['is_outsourced']);
                $headedCollection = collect($batchRows)->map(function ($row) use ($header) {
                    // Make sure the row has the same number of elements as header
                    $row = array_pad($row, count($header), '');
                    // Combine header keys with row values
                    $combined = array_combine($header, $row);
                    // Convert to object-like array (same as Excel import with WithHeadingRow)
                    return collect($combined)->mapWithKeys(function ($value, $key) {
                        // Convert to snake_case like Excel does
                        $normalizedKey = strtolower(str_replace([' ', '-'], '_', $key));
                        return [$normalizedKey => $value];
                    })->toArray();
                });
                // ============================================
                // END OF FIX
                // ============================================

                DB::transaction(function () use ($importer, $headedCollection) {
                    $importer->collection($headedCollection);
                });

                $batchResults = $importer->getImportResults();
                $session['results']['imported'] += $batchResults['imported'];
                $session['results']['skipped'] += $batchResults['skipped'];
                $session['results']['errors'] = array_merge(
                    $session['results']['errors'],
                    $batchResults['errors']
                );
                $session['processed_batches'] = $batchNumber;
                $session['processed_rows'] = min($session['total_rows'], $batchNumber * $batchSize);
                $session['updated_at'] = now();

                Cache::put($sessionId, $session, 3600);

                $progress = min(100, round(($session['processed_rows'] / $session['total_rows']) * 100));
                $isCompleted = $batchNumber >= $session['total_batches'];

                if ($isCompleted) {
                    Cache::forget($sessionId);
                    Log::info("Import completed", [
                        'session_id' => $sessionId,
                        'total_imported' => $session['results']['imported'],
                        'total_skipped' => $session['results']['skipped']
                    ]);
                }

                return sendResponse(
                    "Batch {$batchNumber} processed",
                    [
                        'batch_number' => $batchNumber,
                        'total_batches' => $session['total_batches'],
                        'progress' => $progress,
                        'batch_results' => $batchResults,
                        'cumulative_results' => $session['results'],
                        'completed' => $isCompleted,
                        'session_id' => $sessionId
                    ],
                    true
                );
            } finally {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        } catch (Exception $e) {
            Log::error('Batch Processing Error: ' . $e->getMessage(), [
                'session_id' => $sessionId ?? 'unknown',
                'batch_number' => $batchNumber ?? 'unknown'
            ]);
            return sendResponse("Batch processing failed", [], false, [$e->getMessage()], 500);
        }
    }

    public function getImportProgress(Request $request)
    {
        try {
            $sessionId = $request->input('session_id');

            if (!$sessionId) {
                return sendResponse("Session ID required", [], false, ["Session ID is missing"], 400);
            }

            $session = Cache::get($sessionId);

            if (!$session) {
                return sendResponse("No active import", [], false, ["No import in progress"], 404);
            }

            $progress = min(100, round(($session['processed_rows'] / $session['total_rows']) * 100));

            return sendResponse(
                "Import progress",
                [
                    'progress' => $progress,
                    'processed_batches' => $session['processed_batches'],
                    'total_batches' => $session['total_batches'],
                    'processed_rows' => $session['processed_rows'],
                    'total_rows' => $session['total_rows'],
                    'results' => $session['results'],
                    'session_id' => $sessionId
                ],
                true
            );
        } catch (Exception $e) {
            Log::error('Import Progress Error: ' . $e->getMessage());
            return sendResponse("Failed to get progress", [], false, [$e->getMessage()], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/shipments/getMultiple",
     *     tags={"OMS"},
     *     summary="Retrieve multiple shipments by tracking numbers",
     *     description="Fetches shipment details for multiple tracking numbers with full relationships",
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                 type="string"
     *             )
     *         ),
     *         description="Array of shipment tracking numbers"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No shipments found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Database error"
     *     )
     * )
     */
    public function getMultiple(Request $request)
    {
        try {
            // Validate JSON input with array of tracking numbers
            $validated = $request->validate([
                'tracking_no' => 'required|array|min:1',
                'tracking_no.*' => 'string|exists:shipments,tracking_no'
            ]);

            // Clean and unique tracking numbers
            $trackingNumbers = array_unique(
                array_filter(
                    array_map('trim', $validated['tracking_no'])
                )
            );

            // Get shipments with relationships
            $shipments = Shipment::whereIn('tracking_no', $trackingNumbers)
                ->with([
                    'shipper:id,name,country_id,state_id,contact,zip_code,address',
                    'shipper.country:id,name',
                    'shipper.state:id,en_name,ar_name',
                    'merchant:id,name',
                    'merchant.merchant.country:id,name',
                    'merchant.merchant.governorate:id,en_name,ar_name',
                    'merchant.merchant.state:id,en_name,ar_name',
                    'merchant.merchant.place:id,en_name,ar_name',
                    'consignee:id,name,country_id,governorate_id,state_id,place_id',
                    'consignee.country:id,name',
                    'consignee.governorate:id,en_name,ar_name',
                    'consignee.state:id,en_name,ar_name',
                    'consignee.place:id,en_name,ar_name',
                    'shipment_information:id,shipment_id,zone_id',
                    'shipment_information.zone:id,name',
                    'shipmentHistories',
                    'shipment_items',
                    'shipment_delivery',
                    "quick_notes",
                    'transactions.from',
                    'transactions.to',
                    'shipment_finance',
                    'core_status'
                ])
                ->orderByDesc('created_at')
                ->get();

            if ($shipments->isEmpty()) {
                return sendResponse("No shipments found", [], false, [], 404);
            }

            return sendResponse(
                "Shipments retrieved successfully",
                ShipmentResource::collection($shipments)
            );
        } catch (QueryException $e) {
            return sendResponse(
                "Database error",
                [],
                false,
                [$e->getMessage()],
                500
            );
        } catch (ValidationException $e) {
            return sendResponse(
                "Validation error",
                [],
                false,
                $e->errors(),
                422
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/import_template",
     *     summary="Download shipment import template",
     *     description="Download Excel or CSV template for bulk shipment import",
     *     tags={"OMS"},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully downloaded template",
     *         @OA\MediaType(
     *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error generating template",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error generating template"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function import_template()
    {
        try {
            // Check if static template exists in public/templates directory
            $templatePath = public_path('templates/shipment_import_template.xlsx');

            if (file_exists($templatePath) && is_readable($templatePath)) {
                return response()->download($templatePath, 'shipment_import_template.xlsx', [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="shipment_import_template.xlsx"'
                ]);
            }

            // Generate dynamic template using Excel export
            try {
                $filename = 'shipment_import_template_' . date('Y-m-d_H-i-s') . '.xlsx';

                return Excel::download(
                    new ShipmentImportTemplateExport(),
                    $filename,
                    \Maatwebsite\Excel\Excel::XLSX
                );
            } catch (Exception $e) {
                Log::error('Excel template generation failed: ' . $e->getMessage());

                // Fallback to CSV if Excel fails
                $csvData = $this->generateCsvTemplate();
                $filename = 'shipment_import_template_' . date('Y-m-d_H-i-s') . '.csv';

                return response($csvData, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0'
                ]);
            }
        } catch (Exception $e) {
            Log::error('Shipment Import Template Error: ' . $e->getMessage());
            return sendResponse(
                "Error generating template",
                [],
                false,
                ['Unable to generate import template: ' . $e->getMessage()],
                500
            );
        }
    }

    private function generateCsvTemplate(): string
    {
        $headers = [
            'tracking_no',
            'recipient_country',
            'recipient_state',
            'recipient_city',
            'recipient_name',
            'recipient_cellphone',
            'recipient_street_address',
            'cod',
            'payment_type',
            'recipient_alternate_phone',
            'recipient_zipcode',
            'declare',
            'weight_g',
            'ofd_times'
        ];

        $descriptions = [
            'Tracking Number (Required)',
            'Recipient Country (Required)',
            'Recipient State/Province (Required)',
            'Recipient City (Required)',
            'Recipient Name (Required)',
            'Recipient Phone (Required)',
            'Recipient Address (Required)',
            'COD Amount (Required)',
            'Payment Type (Required)',
            'Alternate Phone (Optional)',
            'Zip Code (Optional)',
            'Declared Value (Optional)',
            'Weight in Grams (Optional)',
            'OFD Times (Optional)'
        ];

        $sampleData = [
            [
                'PE' . date('Y') . '001',
                'Oman',
                'Muscat',
                'Ruwi',
                'Ahmed Al Rashid',
                '+968 9123 4567',
                'Building 123, Way 456, Al Khuwair',
                '25.500',
                'COD',
                '+968 2456 7890',
                '100',
                '25.0',
                '500',
                '1'
            ],
            [
                'PE' . date('Y') . '002',
                'UAE',
                'Dubai',
                'Deira',
                'Sara Mohammed',
                '+971 50 123 4567',
                'Al Rigga Street, Deira',
                '15.750',
                'COD',
                '',
                '',
                '15.0',
                '300',
                '1'
            ]
        ];

        $csvContent = '';

        // Add headers only
        $csvContent .= implode(',', $headers) . "\n";

        // Add sample data directly (no description row)
        foreach ($sampleData as $row) {
            $quotedRow = array_map(function ($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row);
            $csvContent .= implode(',', $quotedRow) . "\n";
        }

        return $csvContent;
    }

    /**
     * @OA\Post(
     *     path="/api/import_preview",
     *     summary="Preview shipment import file",
     *     description="Preview and validate shipment import file before actual import",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Excel or CSV file containing shipments data"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful preview",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Import preview generated successfully"),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(
     *                             property="addresses",
     *                             type="object",
     *                             @OA\Property(property="countries", type="array", @OA\Items(type="string")),
     *                             @OA\Property(property="governorates", type="array", @OA\Items(type="string")),
     *                             @OA\Property(property="states", type="array", @OA\Items(type="string")),
     *                             @OA\Property(property="commissions", type="array", @OA\Items(type="string"))
     *                         ),
     *                         @OA\Property(property="has_missing_data", type="boolean", example=false),
     *                         @OA\Property(property="data", type="object", description="Import preview data")
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Import preview generated with missing dependencies"),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(
     *                             property="addresses",
     *                             type="object",
     *                             @OA\Property(property="countries", type="array", @OA\Items(type="string")),
     *                             @OA\Property(property="governorates", type="array", @OA\Items(type="string")),
     *                             @OA\Property(property="states", type="array", @OA\Items(type="string")),
     *                             @OA\Property(property="commissions", type="array", @OA\Items(type="string"))
     *                         ),
     *                         @OA\Property(property="has_missing_data", type="boolean", example=true),
     *                         @OA\Property(property="data", type="object", description="Import preview data")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Validation failed"),
     *                     @OA\Property(property="data", type="object", @OA\Property(property="missing_columns", type="array", @OA\Items(type="string"))),
     *                     @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Error processing file"),
     *                     @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="System error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="System error occurred"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function import_preview(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:csv,txt,xlsx'
            ]);

            $importer = new ShipmentImportPreview;

            try {
                Excel::import($importer, $request->file('file'));
                $import_data = $importer->getPreviewData();
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'Missing columns')) {
                    $missing = str_replace('Missing columns: ', '', $e->getMessage());
                    return sendResponse(
                        "Validation failed",
                        ['missing_columns' => explode(', ', $missing)],
                        true,
                        ['Excel file is missing required columns'],
                        200
                    );
                }
                Log::error('Shipment Import Preview Error: ' . $e->getMessage());
                return sendResponse(
                    "Error processing file",
                    [],
                    false,
                    [$e->getMessage()],
                    422
                );
            }

            $addresses = [
                'countries' => $import_data['missingCountries'] ?? [],
                'governorates' => $import_data['missingGovernorates'] ?? [],
                'states' => $import_data['missingStates'] ?? [],
                'commissions' => $import_data['commissions'] ?? [],
            ];

            // Check if any missing data exists
            $hasMissingData = count($addresses['countries']) > 0
                || count($addresses['governorates']) > 0
                || count($addresses['states']) > 0
                || count($addresses['commissions']) > 0;

            $response_data = [
                'addresses' => $addresses,
                'data' => $import_data,
                'has_missing_data' => $hasMissingData
            ];

            if ($hasMissingData) {
                return sendResponse(
                    "Import preview generated with missing dependencies",
                    $response_data,
                    true,
                    ["Some channels or commissions are missing. Please create them before importing."],
                    200
                );
            }

            return sendResponse(
                "Import preview generated successfully",
                $response_data
            );
        } catch (ValidationException $e) {
            return sendResponse("Validation failed", [], false, $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Shipment Import Preview Error: ' . $e->getMessage());
            return sendResponse("System error occurred", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/shipments/export",
     *     summary="Export shipments data",
     *     description="Export shipments data in CSV or PDF format with selected columns",
     *     tags={"OMS"},
     *     security={
     *         "bearer_token"={}
     *     },
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"csv", "pdf"},
     *             default="csv"
     *         ),
     *         description="Export format (csv or pdf)"
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="id,tracking_no,consignee.name,consignee.cellphone,total_cod"
     *         ),
     *         description="Comma-separated list of columns to export. If not provided, all available columns will be exported."
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         ),
     *         description="Start date for filtering shipments"
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         ),
     *         description="End date for filtering shipments"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful export",
     *         @OA\MediaType(
     *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid format specified"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function export(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format specified', [], false, null, 422);
        }

        // Get columns to export
        $availableColumns = [
            'id',
            'tracking_no',
            'consignee.name',
            'consignee.country_key_cellphone',
            'consignee.cellphone',
            'consignee.alernatePhone',
            'consignee.country_key_alernatePhone',
            'consignee.country.name',
            'consignee.governorate.en_name',
            'consignee.state.en_name',
            'consignee.streetAddress',
            'consignee.zipcode',
            'total_cod',
            'payment_type',
            'shipment_information.weight',
            'created_at',
            'updated_at'
        ];
        $selectedColumns = $request->input('columns', $availableColumns);

        // Convert string input to array
        if (is_string($selectedColumns)) {
            $selectedColumns = explode(',', $selectedColumns);
        }

        // Ensure only valid columns are selected
        $columns = array_intersect($availableColumns, $selectedColumns);

        // Handle relationships
        $relationships = [];
        foreach ($availableColumns as $column) {
            if (strpos($column, '.') !== false) {
                $parts = explode('.', $column);
                array_pop($parts);
                if (!empty($parts)) {
                    $relationships[] = implode('.', $parts);
                }
            }
        }

        $query = Shipment::with(array_unique($relationships));
        // Query construction
        $query = Shipment::query();
        if (!empty($relationships)) {
            $query->with($relationships);
        }

        // Date filtering
        if ($request->has(['from_date', 'to_date'])) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay()
            ]);
        }

        $shipments = $query->get() ?? collect();

        if ($format === 'pdf') {

            $html = view('exports.general_export', [
                'name' => "Shipments",
                'rows' => $shipments,
                'columns' => $columns,
                'headers' => $this->mapColumnsToHeaders($columns),
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
        }

        $name = 'shipments.' . $format;
        return Excel::download(new ShipmentExport($shipments, $columns), $name);
    }
    private function mapColumnsToHeaders(array $columns): array
    {
        return collect($columns)->map(function ($column) {
            return match ($column) {
                'id' => 'ID',
                'tracking_no' => 'Tracking No',
                'consignee.name' => 'Consignee Name',
                'consignee.country_key_cellphone' => 'Consignee Code Phone',
                'consignee.cellphone' => 'Consignee Phone',
                'consignee.alternatePhone' => 'Consignee Alternate Phone',
                'consignee.country_key_alternatePhone' => 'Consignee Code Alternate Phone',
                'consignee.country.name' => 'Consignee Country',
                'consignee.governorate.en_name' => 'Consignee Governorate',
                'consignee.state.en_name' => 'Consignee State',
                'consignee.streetAddress' => 'Consignee Street Address',
                'consignee.zipcode' => 'Consignee Zipcode',
                'total_cod' => 'Total COD',
                'payment_type' => 'Payment Type',
                'shipment_information.weight' => 'Shipment Weight',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
                default => ucwords(str_replace(['.', '_'], ' ', $column)),
            };
        })->toArray();
    }

    /**
     * @OA\Get(
     *     path="/api/shipments/reports",
     *     summary="Generate shipment reports with filtering and statistics",
     *     description="Get detailed shipment reports with various filtering options and statistical data",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"CREATED", "DELIVERED", "OFD", "in_exception"}
     *         ),
     *         description="Filter by shipment status. Use 'in_exception' to filter shipments in exception."
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         ),
     *         description="Start date for filtering shipments"
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         ),
     *         description="End date for filtering shipments"
     *     ),
     *     @OA\Parameter(
     *         name="customer",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Search by customer name"
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             default=1
     *         ),
     *         description="Page number for pagination"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Report generated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="shipments",
     *                     type="object",
     *                     description="Paginated shipment data"
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="totalShipments", type="integer"),
     *                     @OA\Property(property="totalDelivered", type="integer"),
     *                     @OA\Property(property="totalPending", type="integer"),
     *                     @OA\Property(property="totalInException", type="integer"),
     *                     @OA\Property(property="totalAmount", type="number")
     *                 ),
     *                 @OA\Property(
     *                     property="chart_data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="title", type="string", description="Month and year"),
     *                         @OA\Property(property="value", type="integer", description="Number of shipments")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Query error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error generating report"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="System error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unexpected error occurred"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function reports(Request $request)
    {
        try {
            // Build query with relationships
            $query = Shipment::with([
                'shipper:id,name,country_id,state_id',
                'merchant:id,name',
                'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone,country_id,governorate_id,state_id,place_id',
                'consignee.country:id,name',
                'consignee.governorate:id,en_name,ar_name',
                'consignee.state:id,en_name,ar_name',
                'consignee.place:id,en_name,ar_name',
                'shipmentHistories' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(1);
                }
            ])
                // Exclude pending customer shipments from reports
                ->where(function ($q) {
                    $q->whereNotNull('owner_id')
                        ->orWhereNotNull('owner_type');
                });

            // Apply filters
            if ($request->has('status') && $request->status) {
                if ($request->status === 'in_exception') {
                    $query->where('in_exception', true);
                } else {
                    $query->where('status', $request->status);
                }
            }

            if ($request->has(['from_date', 'to_date']) && $request->from_date && $request->to_date) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->from_date)->startOfDay(),
                    Carbon::parse($request->to_date)->endOfDay()
                ]);
            }

            if ($request->has('customer') && $request->customer) {
                $customerSearch = $request->customer;
                $query->whereHas('consignee', function ($q) use ($customerSearch) {
                    $q->where('name', 'like', "%{$customerSearch}%");
                });
            }
            $shipments = $query->orderByDesc('created_at')->paginate(10);

            // Clone the query for different statistical calculations
            $baseQuery = clone $query;

            $totalShipments = $baseQuery->count();

            $deliveredQuery = clone $baseQuery;
            $totalDelivered = $deliveredQuery->where('status', 'DELIVERED')->count();

            $pendingQuery = clone $baseQuery;
            $totalPending = $pendingQuery->where('status', 'OFD')->count();

            $exceptionQuery = clone $baseQuery;
            $totalInException = $exceptionQuery->where('in_exception', true)->count();

            $amountQuery = clone $baseQuery;
            $totalAmount = $amountQuery->sum('total_cod');

            $summary = [
                'totalShipments' => $totalShipments,
                'totalDelivered' => $totalDelivered,
                'totalPending' => $totalPending,
                'totalInException' => $totalInException,
                'totalAmount' => $totalAmount
            ];
            $chartData = [];
            $startDate = Carbon::now()->subMonths(6)->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
            $statuses = ['CREATED', 'DELIVERED', 'OFD'];
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $monthYear = $currentDate->format('M Y');
                $monthStart = $currentDate->copy()->startOfMonth();
                $monthEnd = $currentDate->copy()->endOfMonth();
                $dataPoint = ['title' => $monthYear];
                $totalMonthShipments = Shipment::whereBetween('created_at', [$monthStart, $monthEnd])->count();
                $dataPoint['value'] = $totalMonthShipments;
                $chartData[] = $dataPoint;
                $currentDate->addMonth();
            }
            return sendResponse("Report generated successfully.", [
                'shipments' => new ShipmentResource($shipments),
                'summary' => $summary,
                'chart_data' => $chartData
            ]);
        } catch (QueryException $e) {
            return sendResponse("Error generating report.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            return sendResponse("Unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/shipments/reports/export",
     *     summary="Export shipment reports with filtering",
     *     description="Export shipment reports in CSV or PDF format with various filtering options",
     *     tags={"OMS"},
     *     security={
     *         "bearer_token"={}
     *     },
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             enum={"csv", "pdf"},
     *             default="csv"
     *         ),
     *         description="Export format (csv or pdf)"
     *     ),
     *     @OA\Parameter(
     *         name="columns",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="id,tracking_no,status,total_cod"
     *         ),
     *         description="Comma-separated list of columns to export. Available columns: id, tracking_no, status, total_cod, payment_type, created_at, updated_at, consignee.name, consignee.cellphone, consignee.alternatePhone, consignee.country.name, consignee.governorate.en_name, consignee.state.en_name, consignee.streetAddress, consignee.zipcode"
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"CREATED", "DELIVERED", "OFD", "in_exception", "all"}
     *         ),
     *         description="Filter by shipment status. Use 'in_exception' to filter shipments in exception. Use 'all' to include all statuses."
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         ),
     *         description="Start date for filtering shipments"
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         ),
     *         description="End date for filtering shipments"
     *     ),
     *     @OA\Parameter(
     *         name="customer",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Search by customer name"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful export",
     *         @OA\MediaType(
     *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="html", type="string", description="HTML content for PDF export")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid format specified",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid format"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function exportReports(Request $request)
    {
        $format = $request->input('format');
        if (!in_array($format, ['csv', 'pdf'])) {
            return sendResponse('Invalid format', [], false, null, 422);
        }

        $availableColumns = [
            'id',
            'tracking_no',
            'status',
            'total_cod',
            'payment_type',
            'created_at',
            'updated_at',
            'consignee.name',
            'consignee.country_key_cellphone',
            'consignee.cellphone',
            'consignee.country_key_alternatePhone',
            'consignee.alternatePhone',
            'consignee.country.name',
            'consignee.governorate.en_name',
            'consignee.state.en_name',
            'consignee.streetAddress',
            'consignee.zipcode'
        ];

        $selectedColumns = $request->input('columns', $availableColumns);

        // Validate and filter columns
        $columns = is_string($selectedColumns)
            ? array_intersect($availableColumns, explode(',', $selectedColumns))
            : array_intersect($availableColumns, $selectedColumns);

        // Query builder with filters
        $query = Shipment::with([
            'shipper:id,name,country_id,state_id',
            'merchant:id,name',
            'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone,country_id,governorate_id,state_id,place_id',
            'consignee.country:id,name',
            'consignee.state:id,en_name,ar_name',
            'consignee.place:id,en_name,ar_name'
        ])
            // Exclude pending customer shipments from export reports
            ->where(function ($q) {
                $q->whereNotNull('owner_id')
                    ->orWhereNotNull('owner_type');
            });

        // Apply filters
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            if ($request->status === 'in_exception') {
                $query->where('in_exception', true);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
        }

        if ($request->has('customer') && $request->customer) {
            $customerSearch = $request->customer;
            $query->whereHas('consignee', function ($q) use ($customerSearch) {
                $q->where('name', 'like', "%{$customerSearch}%");
            });
        }

        $shipments = $query->get();

        if ($format === 'pdf') {
            $html = view('exports.shipmentsReport', compact('columns', 'shipments'))->render();
            return response()->json(['html' => $html]);
        }

        return Excel::download(
            new ShipmentExport($shipments, $columns),
            'shipment_reports.csv',
            \Maatwebsite\Excel\Excel::CSV,
        );
    }

    public function generateTrackingNo(Shipment $shipment)
    {
        if ($shipment->tracking_no) {
            return sendResponse(
                'Shipment already has a tracking number.',
                ['tracking_no' => $shipment->tracking_no],
                false,
                [],
                409
            );
        }

        return DB::transaction(function () use ($shipment) {
            $tracking = generate_tracking_no();
            $preId = $shipment->pre_id;

            $shipment->tracking_no = $tracking;
            $shipment->created_source = 'dashboard';
            $shipment->save();

            $info = $shipment->shipment_information()->first();
            if ($info) {
                $info->update(['tracking_no' => $tracking]);
            } else {
                ShipmentInformation::create([
                    'shipment_id' => $shipment->id,
                    'merchant_id' => $shipment->merchant_id,
                    'tracking_no' => $tracking,
                    'in_warehouse' => true,
                    'status' => 0,
                ]);
            }

            if (!$shipment->shipment_finance) {
                ShipmentFinance::create(['shipment_tracking_no' => $tracking]);
            }

            // Log the Pre-ID to Waybill Number linking event
            try {
                shipmentHistory([
                    'shipment_id' => $shipment->id,
                    'status' => 'PRE_ID_LINKED_TO_WAYBILL',
                    'description' => "Pre-ID ({$preId}) linked to Waybill Number ({$tracking}). Tracking number generated from Unregistered Shipments list.",
                    'time' => now(),
                    'type' => 'PRE_ID_LINKING',
                ]);
            } catch (\Throwable $e) {
                \Log::error('Failed to log Pre-ID linking event', [
                    'shipment_id' => $shipment->id,
                    'pre_id' => $preId,
                    'tracking_no' => $tracking,
                    'error' => $e->getMessage()
                ]);
            }

            // Log the order creation event
            try {
                shipmentHistory([
                    'shipment_id' => $shipment->id,
                    'status' => 'ORDER_CREATED',
                    'description' => 'Tracking number generated from Unregistered list.',
                    'time' => now(),
                ]);
            } catch (\Throwable $e) {
                // swallow logging issues
            }
            $this->adminCounterService->broadcastToAllAdmins();
            activityLog('Generate_tracking_no', "Pre-ID ({$preId}) linked to Waybill Number ({$tracking}). Tracking number generated from Unregistered Shipments list.");
            return sendResponse('Tracking number generated successfully.', [
                'id' => $shipment->id,
                'pre_id' => $shipment->pre_id,
                'tracking_no' => $tracking,
            ]);
        });
    }

    public function bulkGenerateTrackingNo(Request $request)
    {
        $ids = (array) $request->input('ids', []);
        if (empty($ids)) {
            return sendResponse('No IDs provided.', [], false, [], 422);
        }

        $result = [
            'generated' => [],
            'skipped' => [],
        ];

        DB::transaction(function () use ($ids, &$result) {
            $shipments = Shipment::whereIn('id', $ids)->lockForUpdate()->get();

            foreach ($shipments as $shipment) {
                if ($shipment->tracking_no) {
                    $result['skipped'][] = [
                        'id' => $shipment->id,
                        'reason' => 'already_has_tracking',
                    ];
                    continue;
                }

                $tracking = generate_tracking_no();
                $preId = $shipment->pre_id;
                $shipment->update(['tracking_no' => $tracking, 'created_source' => 'dashboard']);

                $info = $shipment->shipment_information()->first();
                if ($info) {
                    $info->update(['tracking_no' => $tracking]);
                } else {
                    ShipmentInformation::create([
                        'shipment_id' => $shipment->id,
                        'merchant_id' => $shipment->merchant_id,
                        'tracking_no' => $tracking,
                        'in_warehouse' => true,
                        'status' => 0,
                    ]);
                }

                if (!$shipment->shipment_finance) {
                    ShipmentFinance::create(['shipment_tracking_no' => $tracking]);
                }

                // Log the Pre-ID to Waybill Number linking event
                try {
                    shipmentHistory([
                        'shipment_id' => $shipment->id,
                        'status' => 'PRE_ID_LINKED_TO_WAYBILL',
                        'description' => "Pre-ID ({$preId}) linked to Waybill Number ({$tracking}). Tracking number generated from Unregistered Shipments list (bulk operation).",
                        'time' => now(),
                        'type' => 'PRE_ID_LINKING',
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('Failed to log Pre-ID linking event in bulk', [
                        'shipment_id' => $shipment->id,
                        'pre_id' => $preId,
                        'tracking_no' => $tracking,
                        'error' => $e->getMessage()
                    ]);
                }

                // Log the order creation event
                try {
                    shipmentHistory([
                        'shipment_id' => $shipment->id,
                        'status' => 'ORDER_CREATED',
                        'description' => 'Tracking number generated from Unregistered list.',
                        'time' => now(),
                    ]);
                } catch (\Throwable $e) {
                    // ignore logging errors
                }

                $result['generated'][] = [
                    'id' => $shipment->id,
                    'tracking_no' => $tracking,
                ];
            }
        });

        return sendResponse('Bulk generation finished.', $result);
    }

    /**
     * @OA\Get(
     *     path="/api/shipments/track/{trackingNo}",
     *     summary="Track shipment by tracking number",
     *     description="Get detailed tracking information for a specific shipment",
     *     tags={"OMS"},
     *     security={
     *         "bearer_token"={}
     *     },
     *     @OA\Parameter(
     *         name="trackingNo",
     *         in="path",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Shipment tracking number"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment tracking details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment tracking details retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="tracking_no",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="consignee",
     *                     type="object",
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="cellphone", type="string"),
     *                     @OA\Property(property="alternatePhone", type="string"),
     *                     @OA\Property(property="country", type="object"),
     *                     @OA\Property(property="governorate", type="object"),
     *                     @OA\Property(property="state", type="object"),
     *                     @OA\Property(property="streetAddress", type="string"),
     *                     @OA\Property(property="zipcode", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="shipmentHistories",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="status", type="string"),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="notes", type="string")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="current_assignment",
     *                     type="object",
     *                     @OA\Property(
     *                         property="driver",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Shipment not found"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="System error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error occurred while fetching shipment tracking details"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function track($trackingNo)
    {
        try {
            $shipment = Shipment::with([
                'consignee',
                'shipmentHistories',
                'current_assignment.driver'
            ])->where('tracking_no', $trackingNo)->first();

            if (!$shipment) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }
            return sendResponse("Shipment tracking details retrieved successfully.", new ShipmentTrackResource($shipment));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return sendResponse("Shipment not found.", [], false, [], 404);
        } catch (\Exception $e) {
            return sendResponse("Error occurred while fetching shipment tracking details.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/shipments/printCompleted",
     *     tags={"OMS"},
     *     summary="Mark shipment as printed",
     *     description="Creates shipment history entry when printing is completed",
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         ),
     *         description="Shipment tracking number"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Print history created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function printCompleted(Request $request)
    {
        $request->validate([
            "tracking_no" => "required|exists:shipments,tracking_no"
        ]);

        $tracking_no = request('tracking_no');
        $shipment = Shipment::where('tracking_no', $tracking_no)->first();

        shipmentHistory([
            "shipment_id" => $shipment->id,
            "status" => "PRINT",
            "description" => "Airway bill has been printed",
            "type" => "PRINT"
        ]);

        return sendResponse("Print history created successfully.", []);
    }

    /**
     * Get unassigned shipments for pickup (no shipment id, only proof)
     */
    public function pickupUnassignedProofs(Request $request, $type = "unCreated")
    {
        $perPage = $request->query('per_page', 8);

        $from = $request->input('from');
        $to = $request->input('to');
        $pickup_ref = $request->input('pickup_ref');

        $query = \App\Models\MerchantPickupShipment::query()
            ->with(['merchant', 'driver', 'pickup_task'])
            ->orderByDesc('id')

            // TYPE FILTER
            ->when($type === 'unCreated', function ($q) {
                $q->whereNull('shipment_id');
            })
            ->when($type === 'created', function ($q) {
                $q->whereHas('shipment', function ($sq) {
                    $sq->where('created_source', 'unCreated');
                });
            })
            ->when($pickup_ref, function ($q) use ($pickup_ref) {
                $q->whereHas('pickup_task', function ($sq) use ($pickup_ref) {
                    $sq->where('ref', 'like', '%' . $pickup_ref . '%');
                });
            })

            // DATE FILTER
            ->when($from, function ($q) use ($from) {
                $q->whereDate('created_at', '>=', Carbon::parse($from)->startOfDay());
            })
            ->when($to, function ($q) use ($to) {
                $q->whereDate('created_at', '<=', Carbon::parse($to)->endOfDay());
            });


        $records = $query->paginate($perPage);

        // $data = $records->map(function ($item) {
        //     // Default to a placeholder image or null if no proof exists
        //     // Use the model accessor for proof_url logic to handle S3 paths correctly
        //     $proofUrl = $item->proof_url;

        //     return [
        //         'id' => $item->id,
        //         'driver_id' => $item->driver_id,
        //         'merchant' => $item->merchant ? [
        //             'id' => $item->merchant->id,
        //             'name' => $item->merchant->name,
        //         ] : null,
        //         'driver' => $item->driver ? [
        //             'id' => $item->driver->id,
        //             'name' => $item->driver->name,
        //         ] : null,
        //         'merchant_id' => $item->merchant_id,
        //         'tracking_no' => $item->shipment_tracking_no,
        //         'pickup_task_id' => $item->pickup_task_id,
        //         'shipment_id' => $item->shipment_id,
        //         'shipment' => $item->shipment ? [
        //             'tracking_no' => $item->shipment->tracking_no,
        //             'status' => $item->shipment->status,
        //         ] : null,
        //         'proof_url' => $proofUrl,
        //         'status' => $item->status,
        //         'created_at' => $item->created_at,
        //         'updated_at' => $item->updated_at,
        //     ];
        // });
        $data = UncreatedShipmentResource::collection($records)->response()->getData(true);

        // Convert links from object to array format
        if (isset($data['links'])) {
            $data['links'] = array_values((array) $data['links']);
        }

        return sendResponse('Unassigned proofs retrieved successfully.', $data);
    }
}
