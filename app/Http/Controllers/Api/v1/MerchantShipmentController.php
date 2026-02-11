<?php

namespace App\Http\Controllers\Api\v1;

use Exception;


use Carbon\Carbon;
use App\Models\User;
use App\Models\Account;
use App\Models\Address;
use App\Models\Shipper;
use App\Models\Merchant;
use App\Models\Shipment;
use App\Models\Consignee;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Models\ShipmentItem;
use Illuminate\Http\Request;
use App\Models\PickupRequest;
use App\Models\MerchantSetting;
use App\Models\MerchantWaybill;
use App\Models\ShipmentFinance;
use App\Models\ShipmentDelivery;
use App\Services\AddressService;
use App\Models\ShipperCommission;
use App\Models\MerchantCommission;
use App\Models\MerchantPickupTask;
use Illuminate\Support\Facades\DB;
use App\Models\ShipmentInformation;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Scopes\ConsigneeScope;
use App\Models\MerchantPickupShipment;
use Illuminate\Support\Facades\Schema;
use App\Imports\MerchantShipmentImport;
use Illuminate\Database\QueryException;
use App\Http\Resources\ShipmentResource;
use Illuminate\Support\Facades\Response;
use App\Services\AddressUpdateLinkService;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\RegisterShipmentRequest;
use App\Imports\MerchantShipmentImportPreview;
use App\Notifications\ShipmentCreatedNotification;
use App\Exports\MerchantShipmentImportTemplateExport;
use App\Http\Resources\MerchantPickupShipmentResource;
use App\Enums\ShipmentStatusEnum;
use App\Enums\MerchantPickupTaskStatusEnum;
use App\Models\CommissionTemplate;
use App\Domain\Pickup\ShipmentPickupFactory;
use App\Services\AdminCounterService;

class MerchantShipmentController extends Controller
{
         public $adminCounterService;
    public function __construct(AdminCounterService $adminCounterService)
    {
        $this->adminCounterService = $adminCounterService;
    }
    /**
     * Merchant Dashboard Overview
     *
     * @OA\Get(
     *     path="/merchant/dashboard",
     *     summary="Get merchant dashboard data",
     *     description="
     * Retrieve comprehensive dashboard overview for authenticated merchant.
     *
     * **Features:**
     * - Shipment statistics summary
     * - Recent activity overview
     * - Key performance indicators
     * - Quick action shortcuts
     *
     * **Security:**
     * - Requires merchant authentication
     * - Data filtered by merchant scope
     * ",
     *     operationId="getMerchantDashboard",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dashboard data retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function dashboard()
    {
        try {

            $user = auth()->user();

            if(!$user->merchant){
                return sendResponse('Unauthenticated.', [], false, ['User not authenticated.'], 401);
            }
            $merchantId = $user->id;

            // Basic shipment statistics
            $totalShipments = Shipment::where('merchant_id', $merchantId)->count();
            $todayShipments = Shipment::where('merchant_id', $merchantId)
                ->whereDate('created_at', today())
                ->count();

            // Pickup shipment statistics
            $pendingPickup = MerchantPickupShipment::where('merchant_id', $merchantId)
                ->where('status', 'created')
                ->count();

            // Try Shipment model first (base version approach), fallback to MerchantPickupShipment (v1 approach)
            $pickedShipmentsFromShipment = Shipment::where('merchant_id', $merchantId)
                ->whereIn('status', [ShipmentStatusEnum::PICKED, 'picked'])
                ->count();

            $pickedShipmentsFromPickup = MerchantPickupShipment::where('merchant_id', $merchantId)
                ->where('status', 'picked')
                ->count();

            // Use the higher count to ensure we don't miss any picked shipments
            $pickedShipments = max($pickedShipmentsFromShipment, $pickedShipmentsFromPickup);

            // Task statistics
            $activeTasks = MerchantPickupTask::where('merchant_id', $merchantId)
                ->whereIn('status', ['created', 'pending', 'to_pickup'])
                ->count();

            $completedTasks = MerchantPickupTask::where('merchant_id', $merchantId)
                ->where('status', 'pickup_completed')
                ->count();

            // Calculate total shipment value
            $totalShipmentValue = $this->calculateShipmentOperations($merchantId);

            // Calculate this month shipment value
            $thisMonthValue = $this->calculateThisMonthShipmentOperations($merchantId);

            // Get merchant account balance - try Account model first, fallback to Merchant model
            // $merchantAccount = Account::where('accountable_type', User::class)
            //     ->where('accountable_id', $merchantId)
            //     ->first();

            $merchant = Merchant::where('user_id', $merchantId)->first();

            $query = \App\Models\MerchantTransaction::forMerchant($merchant->id)
            ->completed();

            $accountBalance = $query->sum('amount');

            $parcelValue = $merchant->parcel_value;

            // Shipment status breakdown
            $shipmentStatuses = Shipment::where('merchant_id', $merchantId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Recent activity (last 7 days)
            $recentActivity = Shipment::where('merchant_id', $merchantId)
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            // Payment type breakdown
            $paymentTypes = Shipment::where('merchant_id', $merchantId)
                ->selectRaw('payment_type, COUNT(*) as count, SUM(total_cod) as total_amount')
                ->groupBy('payment_type')
                ->get()
                ->keyBy('payment_type');

            // Performance metrics
            $deliveredShipments = Shipment::where('merchant_id', $merchantId)
                ->where('status', 'DELIVERED')
                ->count();

            $deliveryRate = $totalShipments > 0 ? round(($deliveredShipments / $totalShipments) * 100, 2) : 0;

            // Average shipment value
            $avgShipmentValue = $totalShipments > 0 ? round($totalShipmentValue / $totalShipments, 2) : 0;
            // ==== Active Pickup signal (requests + tasks + shipments) ====
            $pendingPickupRequestsCount = PickupRequest::where('merchant_user_id', $merchantId)
                ->whereIn('status', ['pending', 'approved'])
                ->count();

            $nextPickupAt = PickupRequest::where('merchant_user_id', $merchantId)
                ->whereIn('status', ['pending', 'approved'])
                ->orderBy('scheduled_at', 'asc')
                ->value('scheduled_at');

            $activePickupTasksCount = MerchantPickupTask::where('merchant_id', $merchantId)
                ->whereIn('status', ['created', 'pending', 'to_pickup'])
                ->count();

            $activePickupShipmentsCount = MerchantPickupShipment::where('merchant_id', $merchantId)
                ->whereIn('status', ['created', 'to_pickup'])
                ->count();

            $hasActivePickup = ($pendingPickupRequestsCount > 0)
                || ($activePickupTasksCount > 0)
                || ($activePickupShipmentsCount > 0);

            $stats = [
                // Basic Statistics
                'total_shipments' => $totalShipments,
                'today_shipments' => $todayShipments,
                'pending_pickup' => $pendingPickup,
                'picked_shipments' => $pickedShipments,
                'active_tasks' => $activeTasks,
                'completed_tasks' => $completedTasks,

                // Financial Statistics
                'total_shipment_value' => number_format($totalShipmentValue, 2),
                'this_month_value' => number_format($thisMonthValue, 2),
                'account_balance' => number_format($accountBalance, 2),
                'parcel_value' => number_format($parcelValue, 2),
                'avg_shipment_value' => number_format($avgShipmentValue, 2),

                // Performance Metrics
                'delivery_rate' => $deliveryRate,
                'delivered_shipments' => $deliveredShipments,

                // Breakdowns
                'shipment_statuses' => $shipmentStatuses,
                'payment_types' => $paymentTypes,
                'recent_activity' => $recentActivity,
                'has_active_pickup' => $hasActivePickup,
                'active_pickup' => [
                    'pending_requests' => $pendingPickupRequestsCount,
                    'active_tasks' => $activePickupTasksCount,
                    'active_shipments' => $activePickupShipmentsCount,
                    'next_pickup_at' => $nextPickupAt,
                ],

                // Summary for quick overview
                'summary' => [
                    'shipments_this_week' => Shipment::where('merchant_id', $merchantId)
                        ->where('created_at', '>=', now()->startOfWeek())
                        ->count(),
                    'shipments_this_month' => Shipment::where('merchant_id', $merchantId)
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->count(),
                    'pending_tasks' => MerchantPickupTask::where('merchant_id', $merchantId)
                        ->whereIn('status', ['created', 'pending'])
                        ->count(),
                ]
            ];

            return sendResponse("Merchant dashboard statistics retrieved successfully.", $stats);
        } catch (Exception $e) {
            return sendResponse("An error occurred while fetching dashboard statistics.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Get Merchant Shipments List
     *
     * @OA\Get(
     *     path="/merchant/shipments",
     *     summary="Get paginated list of merchant shipments",
     *     description="
     * Retrieve paginated list of shipments for authenticated merchant with filtering and search capabilities.
     *
     * **Features:**
     * - Pagination support
     * - Advanced filtering options
     * - Search functionality
     * - Sorting capabilities
     *
     * **Security:**
     * - Merchant scope filtering
     * - Role-based access control
     * ",
     *     operationId="getMerchantShipments",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by shipment status",
     *         required=false,
     *         @OA\Schema(type="array", @OA\Items(type="string"))
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter shipments from date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter shipments to date",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="pre_id",
     *         in="query",
     *         description="Filter by shipment pre_id",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search shipments by tracking_no, pre_id, consignee name, phone, address, or zipcode",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipments retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */

    public function index(Request $request)
    {
        try {
            $merchantId = Auth::id();
            $perPage = (int) $request->input('per_page', 10);

            $query = Shipment::query()
                ->with([
                    'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone',
                    'deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,zipcode,streetAddress,latitude,longitude,location_url,approved,approved_at',
                    'deliveryAddress.country:id,name',
                    'deliveryAddress.governorate:id,en_name,ar_name',
                    'deliveryAddress.state:id,en_name,ar_name',
                    'deliveryAddress.place:id,en_name,ar_name',
                    'deliveryAddress.city:id,name',
                    'shipment_items:id,shipment_id,name,quantity,category',
                    'shipmentHistories' => function ($q) {
                        $q->select([
                            'id',
                            'shipment_id',
                            'name',
                            'description',
                            'time',
                            'type',
                            'operatorInfo',
                            'operationHub',
                            'operationHubType',
                            'proof',
                            'data',
                            'created_at',
                        ])->orderByDesc('id');
                    },
                    // 'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone',
                    // 'deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,zipcode,streetAddress,latitude,longitude,location_url,approved,approved_at',
                    // 'deliveryAddress.country:id,name',
                    // 'deliveryAddress.governorate:id,en_name,ar_name',
                    // 'deliveryAddress.state:id,en_name,ar_name',
                    // 'deliveryAddress.place:id,en_name,ar_name',
                    // 'deliveryAddress.city:id,name',
                    // 'shipment_items:id,shipment_id,name,quantity,category',

                    // 'merchant_pickup_shipment:id,shipment_tracking_no,status,driver_id,merchant_id,created_at',
                    // 'merchant_pickup_shipment.pickup_task:id,merchant_id,status,created_at',

                    // 'driver:id,name',
                ])
                ->where('merchant_id', $merchantId)
                ->orderBy('created_at', 'desc');

            if ($request->filled('status')) {
                $allowed = ['created', 'pending', 'pickup_completed', 'cancelled', 'picked', 'to_pickup', 'delivery_exception', 'delivered'];

                $statusInput = $request->input('status');

                $requested = is_array($statusInput)
                    ? $statusInput
                    : (is_string($statusInput)
                        ? array_filter(array_map('trim', explode(',', $statusInput)))
                        : []);

                $requested = array_map('strtolower', $requested);

                $valid = array_values(array_intersect($requested, $allowed));

                if (!empty($valid)) {
                    $query->whereIn('status', $valid);
                }
            }


            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
            }

            // Filter by pre_id
            if ($request->filled('pre_id')) {
                $query->where('pre_id', $request->input('pre_id'));
            }

            // البحث
            if ($request->filled('query')) {
                $term = '%' . $request->input('query') . '%';
                $query->where(function ($qq) use ($term) {
                    $qq->where('tracking_no', 'LIKE', $term)
                        ->orWhere('pre_id', 'LIKE', $term)
                        ->orWhereHas('consignee', function ($sub) use ($term) {
                            $sub->where('name', 'LIKE', $term)
                                ->orWhere('cellphone', 'LIKE', $term);
                        })
                        ->orWhereHas('deliveryAddress', function ($sub) use ($term) {
                            $sub->where('streetAddress', 'LIKE', $term)
                                ->orWhere('zipcode', 'LIKE', $term);
                        });
                });
            }

            // Search by pickup task ref
            if ($request->filled('pickup_ref')) {
                $refTerm = '%' . $request->input('pickup_ref') . '%';
                $query->whereHas('merchant_pickup_shipment.pickup_task', function ($sub) use ($refTerm) {
                    $sub->where('ref', 'LIKE', $refTerm);
                });
            }

            $shipments = $query->paginate($perPage);

            return sendResponse(
                "Merchant pickup shipments retrieved successfully.",
                new MerchantPickupShipmentResource($shipments)
            );
        } catch (\Exception $e) {
            return sendResponse(
                "An error occurred while fetching merchant pickup shipments.",
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    // public function index(Request $request)
    // {
    //     try {
    //         $merchantId = Auth::id();
    //         $perPage = $request->input('per_page', 10);

    //         $query = MerchantPickupShipment::with([
    //             'shipment' => function ($q) {
    //                 $q->with([
    //                     'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone',

    //                     'deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,zipcode,streetAddress,latitude,longitude,location_url,approved,approved_at',

    //                     'deliveryAddress.country:id,name',
    //                     'deliveryAddress.governorate:id,en_name,ar_name',
    //                     'deliveryAddress.state:id,en_name,ar_name',
    //                     'deliveryAddress.place:id,en_name,ar_name',
    //                     'deliveryAddress.city:id,name',


    //                     'shipment_items:id,shipment_id,name,quantity,category',
    //                 ]);
    //             },
    //             'pickup_task:id,merchant_id,status,created_at',
    //             'driver:id,name'
    //         ])
    //             ->where('merchant_id', $merchantId)
    //             ->orderBy('created_at', 'desc');

    //         if ($request->filled('status')) {
    //             $allowed = ['created', 'pending', 'pickup_completed', 'cancelled', 'picked', 'to_pickup'];
    //             $requested = is_array($request->status) ? $request->status : [$request->status];
    //             $valid = array_intersect($requested, $allowed);
    //             if (!empty($valid)) {
    //                 $query->whereIn('status', $valid);
    //             }
    //         }

    //         // فلاتر التاريخ
    //         if ($request->filled('from_date')) {
    //             $query->whereDate('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
    //         }
    //         if ($request->filled('to_date')) {
    //             $query->whereDate('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
    //         }

    //         // البحث
    //         if ($request->filled('query')) {
    //             $term = '%' . $request->input('query') . '%';
    //             $query->where(function ($qq) use ($term) {
    //                 $qq->where('shipment_tracking_no', 'LIKE', $term)
    //                     ->orWhereHas('shipment.consignee', function ($sub) use ($term) {
    //                         $sub->where('name', 'LIKE', $term)
    //                             ->orWhere('cellphone', 'LIKE', $term);
    //                     })
    //                     // كمان نسمح بالبحث في عنوان الأوردر الحالي
    //                     ->orWhereHas('shipment.deliveryAddress', function ($sub) use ($term) {
    //                         $sub->where('streetAddress', 'LIKE', $term)
    //                             ->orWhere('zipcode', 'LIKE', $term);
    //                     });
    //             });
    //         }

    //         $pickupShipments = $query->paginate($perPage);

    //         return sendResponse(
    //             "Merchant pickup shipments retrieved successfully.",
    //             new MerchantPickupShipmentResource($pickupShipments)
    //         );
    //     } catch (\Exception $e) {
    //         return sendResponse(
    //             "An error occurred while fetching merchant pickup shipments.",
    //             [],
    //             false,
    //             [$e->getMessage()],
    //             500
    //         );
    //     }
    // }
    /// ============================================================================================
    /// --------------------------------------------------------------------------------------------
    /// ============================================================================================
    // public function index(Request $request)
    // {
    //     try {
    //         $merchantId = Auth::id();
    //         $perPage = (int) $request->input('per_page', 10);

    //         $query = Shipment::query()
    //             ->with([
    //                 'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone',

    //                     'deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,zipcode,streetAddress,latitude,longitude,location_url,approved,approved_at',

    //                     'deliveryAddress.country:id,name',
    //                     'deliveryAddress.governorate:id,en_name,ar_name',
    //                     'deliveryAddress.state:id,en_name,ar_name',
    //                     'deliveryAddress.place:id,en_name,ar_name',
    //                     'deliveryAddress.city:id,name',


    //                     'shipment_items:id,shipment_id,name,quantity,category',
    //                 // 'consignee:id,name,country_key_cellphone,cellphone,country_key_alternatePhone,alternatePhone',
    //                 // 'deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,zipcode,streetAddress,latitude,longitude,location_url,approved,approved_at',
    //                 // 'deliveryAddress.country:id,name',
    //                 // 'deliveryAddress.governorate:id,en_name,ar_name',
    //                 // 'deliveryAddress.state:id,en_name,ar_name',
    //                 // 'deliveryAddress.place:id,en_name,ar_name',
    //                 // 'deliveryAddress.city:id,name',
    //                 // 'shipment_items:id,shipment_id,name,quantity,category',

    //                 // 'merchant_pickup_shipment:id,shipment_tracking_no,status,driver_id,merchant_id,created_at',
    //                 // 'merchant_pickup_shipment.pickup_task:id,merchant_id,status,created_at',

    //                 // 'driver:id,name',
    //             ])
    //             ->where('merchant_id', $merchantId)
    //             ->orderBy('created_at', 'desc');

    //         if ($request->filled('status')) {
    //             $allowed = ['created', 'pending', 'pickup_completed', 'cancelled', 'picked', 'to_pickup'];
    //             $requested = is_array($request->status) ? $request->status : [$request->status];
    //             $valid = array_intersect($requested, $allowed);
    //             if (!empty($valid)) {
    //                 $query->whereHas('merchant_pickup_shipment', function ($q) use ($valid) {
    //                     $q->whereIn('status', $valid);
    //                 });
    //             }
    //         }

    //         if ($request->filled('from_date')) {
    //             $query->whereDate('created_at', '>=', \Carbon\Carbon::parse($request->from_date)->startOfDay());
    //         }
    //         if ($request->filled('to_date')) {
    //             $query->whereDate('created_at', '<=', \Carbon\Carbon::parse($request->to_date)->endOfDay());
    //         }

    //         // البحث
    //         if ($request->filled('query')) {
    //             $term = '%' . $request->input('query') . '%';
    //             $query->where(function ($qq) use ($term) {
    //                 $qq->where('tracking_no', 'LIKE', $term)
    //                     ->orWhereHas('consignee', function ($sub) use ($term) {
    //                         $sub->where('name', 'LIKE', $term)
    //                             ->orWhere('cellphone', 'LIKE', $term);
    //                     })
    //                     ->orWhereHas('deliveryAddress', function ($sub) use ($term) {
    //                         $sub->where('streetAddress', 'LIKE', $term)
    //                             ->orWhere('zipcode', 'LIKE', $term);
    //                     });
    //             });
    //         }

    //         $shipments = $query->paginate($perPage);

    //         return sendResponse(
    //             "Merchant pickup shipments retrieved successfully.",
    //             new MerchantPickupShipmentResource($shipments)
    //         );
    //     } catch (\Exception $e) {
    //         return sendResponse(
    //             "An error occurred while fetching merchant pickup shipments.",
    //             [],
    //             false,
    //             [$e->getMessage()],
    //             500
    //         );
    //     }
    // }



    /**
     * Create New Shipment
     *
     * @OA\Post(
     *     path="/merchant/shipments/store",
     *     summary="Create a new shipment",
     *     description="
     * Create a new shipment with comprehensive validation and processing.
     *
     * **Features:**
     * - Complete shipment validation
     * - Address book integration
     * - Automatic pricing calculation
     * - Real-time inventory check
     *
     * **Security:**
     * - Merchant authentication required
     * - Commission validation
     * - Rate limiting applied
     * ",
     *     operationId="createMerchantShipment",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Shipment creation data",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shipment created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment created successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */

    public function store(StoreShipmentRequest $request)
    {
        if (!$request->filled('tracking_no') && !$request->filled('pre_id')) {
            $request->merge(['pre_id' => generate_pre_id()]);
        }
        $request->validated();
        // $request->validate([
        //     'tracking_no' => "required"
        // ]);
        $request->merge([
            "merchant_id" => Auth::user()->id,
            "shipper_id" => Shipper::where("name", "Parcel Express")->first()->id,
        ]);
        DB::beginTransaction();
        try {
            $consigneeInput = $request->only([
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

            $cellSplit = splitPhoneNumber($consigneeInput['cellphone']);
            $consigneeInput['country_key_cellphone'] = $cellSplit['country_code'];
            $consigneeInput['cellphone'] = $cellSplit['national_number'];

            $altSplit = $request->filled('alternatePhone')
                ? splitPhoneNumber($consigneeInput['alternatePhone'])
                : ['country_code' => null, 'national_number' => null];
            $consigneeInput['country_key_alternatePhone'] = $altSplit['country_code'];
            $consigneeInput['alternatePhone'] = $altSplit['national_number'];

            $consignee = Consignee::withoutGlobalScope(ConsigneeScope::class)
                ->where('cellphone', $consigneeInput['cellphone'])
                ->where('name', $consigneeInput['name'])
                ->when($consigneeInput['country_id'] ?? null, fn($q, $cid) => $q->where('country_id', $cid))
                ->first();

            if ($consignee) {
                $diff = collect($consigneeInput)->diffAssoc($consignee->only(array_keys($consigneeInput)));
                if ($diff->isNotEmpty())
                    $consignee->update($diff->toArray());
            } else {
                $consignee = Consignee::create($consigneeInput);
            }

            if ($request->filled('delivery_address_id')) {
                $address = Address::where('id', (int) $request->delivery_address_id)
                    ->where('consignee_id', $consignee->id)
                    ->first();

                if (!$address) {
                    DB::rollBack();
                    return sendResponse("Delivery address not found for this consignee.", [], false, [
                        "Invalid delivery_address_id for the given consignee"
                    ], 404);
                }
            } else {
                $address = Address::create([
                    'consignee_id' => $consignee->id,
                    'country_id' => $request->input('country_id'),
                    'governorate_id' => $request->input('governorate_id'),
                    'state_id' => $request->input('state_id'),
                    'place_id' => $request->input('place_id'),
                    'city_id' => $request->input('city_id'),
                    'zipcode' => $request->input('zipcode'),
                    'streetAddress' => $request->input('streetAddress'),
                    'longitude' => $request->input('longitude'),
                    'latitude' => $request->input('latitude'),
                    'location_url' => $request->input('location_url'),
                    'label' => null,
                    'approved' => false,
                    'is_active' => true,
                ]);
            }

            $shipmentData = $request->only([
                "notes",
                "payment_type",
                "value",
                "delivery_fee",
                "merchant_id",
                "fee_payer",
                "tracking_no"
            ]);

            $shipmentData['created_by'] = Auth::id();
            $shipmentData['shipper_id'] = Shipper::pe()->id;
            $shipmentData['merchant_id'] = $shipmentData['merchant_id'] ?? Auth::id();
            $shipmentData['owner_id'] = Auth::user()->owner_id;
            $shipmentData['owner_type'] = Auth::user()->owner_type;
            $shipmentData['consignee_id'] = $consignee->id;
            $shipmentData['created_source'] = 'dashboard';

            if ($request->boolean('is_walkin')) {
                $shipmentData['is_walkin'] = 1;
                $shipmentData['customer_name'] = $request->customer_name;
                $shipmentData['customer_phone'] = $request->customer_phone;
                $shipmentData['customer_id_card'] = $request->customer_id_card;
            }
            // ===== Pricing (merchant / shipper) - نفس لوجيك ShipmentController@store =====

            $stateId = $address->state_id ?? null;
            $countryId = $address->country_id ?? $request->input('country_id');

            if (!$stateId) {
                throw new \Exception("state_id is missing for delivery address");
            }

            // تأكيد الـ shipper_id
            $resolvedShipperId = $shipmentData['shipper_id']
                ?? $request->input('shipper_id')
                ?? optional(Shipper::pe())->id;

            if (!$resolvedShipperId) {
                throw new \Exception("shipper_id not resolved");
            }
            $shipmentData['shipper_id'] = $resolvedShipperId;

            // شحن الشاحن (shipper commission) حسب الولاية
            $shipperDeliveryFromCommission = optional(
                ShipperCommission::where('shipper_id', $resolvedShipperId)
                    ->where('state_id', $stateId)
                    ->first()
            )->delivery_fee;

            $shipperDeliveryFromCommission = is_null($shipperDeliveryFromCommission)
                ? null
                : (float) $shipperDeliveryFromCommission;

            // كوميشن التاجر (لو موجود)
            $merchantBaseFromCommission = null; // base_delivery_fee
            $merchantLegacyDelivery = null; // delivery_fee القديم
            $merchantDiscountFromCommission = null; // delivery_discount_amount

            // PRIORITY 1: Check state-specific merchant commission
            $merchantCommission = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
                ->where('state_id', $stateId)
                ->first();

            // PRIORITY 2: If no state-specific, check global merchant commission
            if (!$merchantCommission) {
                $merchantCommission = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
                    ->whereNull('state_id')
                    ->first();
            }

            if ($merchantCommission) {
                if (array_key_exists('base_delivery_fee', $merchantCommission->getAttributes())) {
                    $merchantBaseFromCommission = is_null($merchantCommission->base_delivery_fee)
                        ? null
                        : (float) $merchantCommission->base_delivery_fee;
                }

                if (array_key_exists('delivery_fee', $merchantCommission->getAttributes())) {
                    $merchantLegacyDelivery = is_null($merchantCommission->delivery_fee)
                        ? null
                        : (float) $merchantCommission->delivery_fee;
                }

                if (array_key_exists('delivery_discount_amount', $merchantCommission->getAttributes())) {
                    $merchantDiscountFromCommission = is_null($merchantCommission->delivery_discount_amount)
                        ? 0.0
                        : (float) $merchantCommission->delivery_discount_amount;
                }
            }

            // Template افتراضي حسب الدولة/الولاية
            $templateQuery = CommissionTemplate::query();
            $feePayerRaw = Str::lower($shipmentData['fee_payer'] ?? 'shipper');
            $feePayer = in_array($feePayerRaw, ['merchant', 'customer']) ? 'merchant' : 'shipper';
            $feePayerRaw = Str::lower($shipmentData['fee_payer'] ?? $request->input('fee_payer', 'shipper'));
            $feePayer = in_array($feePayerRaw, ['merchant', 'customer', 'shipper']) ? $feePayerRaw : 'shipper';
            $feePayerRaw = Str::lower($request->input('fee_payer', 'shipper'));
            $feePayer = in_array($feePayerRaw, ['merchant', 'customer', 'shipper']) ? $feePayerRaw : 'shipper';

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
                        ?: CommissionTemplate::whereNull('country_id')
                        ->whereNull('state_id')
                        ->first();
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
                if (!is_null($merchantBaseFromCommission) && $merchantBaseFromCommission > 0) {
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

            // Calculate base fee for discount calculation
            $baseFeeForDiscount = !is_null($merchantBaseFromCommission)
                ? (float) $merchantBaseFromCommission
                : (float) ($shipmentData['delivery_fee'] ?? 0.0);

            // Always set delivery_fee_before_discount in shipmentData for getTotalCOD() calculation
            $shipmentData['delivery_fee_before_discount'] = $baseFeeForDiscount;

            // before_discount / discount أعمدة
            if (Schema::hasColumn('shipments', 'delivery_fee_before_discount')) {
                // Already set above for database storage
            }

            if (Schema::hasColumn('shipments', 'delivery_fee_discount')) {
                // نفس لوجيك ShipmentController: بنحفظ اللي في MerchantCommission
                $shipmentData['delivery_fee_discount'] = (float) ($merchantDiscountFromCommission ?? 0.0);
            }

            // Apply discount to delivery_fee (net fee after discount)
            $discountAmount = (float) ($merchantDiscountFromCommission ?? 0.0);
            $shipmentData['delivery_fee'] = max(0, $baseFeeForDiscount - $discountAmount);

            // نكمّل بعدها زي ما هو عندك:
            $value = (float) ($request->value ?? 0);
            $shipmentData['value'] = $value;

            // Set fee_payer from request (الجزء اللي عندك أصلاً)
            $shipmentData['fee_payer'] = $feePayer;

            // Use centralized calculation service for total_cod
            $calculationService = app(\App\Services\CalculationLogicService::class);
            $shipmentObj = (object) $shipmentData; // Convert to object for calculation
            $shipmentData['total_cod'] = $calculationService->getTotalCOD($shipmentObj);


            // $merchantCommission = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
            //     ->where('state_id', $address->state_id)
            //     ->first();

            // if (!$merchantCommission) {
            //     DB::rollBack();
            //     return sendResponse(
            //         "Merchant commission not available for the selected state.",
            //         [],
            //         false,
            //         ["Merchant commission is not available for the state"],
            //         500
            //     );
            // }

            // // $feePayerRaw = Str::lower($shipmentData['fee_payer'] ?? 'shipper');
            // // $feePayer = in_array($feePayerRaw, ['merchant', 'customer']) ? 'merchant' : 'shipper';
            // // $feePayerRaw = Str::lower($shipmentData['fee_payer'] ?? $request->input('fee_payer', 'shipper'));
            // // $feePayer = in_array($feePayerRaw, ['merchant', 'customer', 'shipper']) ? $feePayerRaw : 'shipper';
            // $feePayerRaw = Str::lower($request->input('fee_payer', 'shipper'));
            // $feePayer = in_array($feePayerRaw, ['merchant', 'customer', 'shipper']) ? $feePayerRaw : 'shipper';

            // $defaultBase = 2.0;
            // $merchantBaseFee = isset($merchantCommission->base_delivery_fee) ? (float) $merchantCommission->base_delivery_fee : null;
            // $merchantDiscount = isset($merchantCommission->delivery_discount_amount) ? (float) $merchantCommission->delivery_discount_amount : 0.0;
            // $merchantEffective = null;

            // if (!is_null($merchantBaseFee)) {
            //     $merchantEffective = max(0, $merchantBaseFee - $merchantDiscount);
            // } elseif (!is_null($merchantCommission->delivery_fee)) {
            //     $merchantBaseFee = (float) $merchantCommission->delivery_fee;
            //     $merchantEffective = (float) $merchantCommission->delivery_fee;
            // } else {
            //     $merchantBaseFee = $defaultBase;
            //     $merchantEffective = $defaultBase;
            // }

            // $shipmentData['delivery_fee'] = (float) $merchantBaseFee;

            // if (Schema::hasColumn('shipments', 'delivery_fee_before_discount')) {
            //     $shipmentData['delivery_fee_before_discount'] = (float) $merchantBaseFee;
            // }
            // if (Schema::hasColumn('shipments', 'delivery_fee_discount')) {
            //     $shipmentData['delivery_fee_discount'] = max(0, (float) $merchantBaseFee - (float) $merchantEffective);
            // }

            // // $value = (float) ($request->value ?? 0);
            // // $paymentType = $shipmentData['payment_type'] ?? 'COD';

            // // if (strtoupper($paymentType) === 'COD') {
            // //     $shipmentData['amount'] = ($feePayer === 'merchant')
            // //         ? $value
            // //         : $value + (float) $shipmentData['delivery_fee'];
            // // } else {
            // //     $shipmentData['amount'] = 0;
            // // }
            // $value = (float) ($request->value ?? 0);
            // $shipmentData['value'] = $value;

            // // Set fee_payer from request
            // $shipmentData['fee_payer'] = $feePayer;

            // // Use centralized calculation service for total_cod
            // $calculationService = app(\App\Services\CalculationLogicService::class);
            // $shipmentObj = (object) $shipmentData; // Convert to object for calculation
            // $shipmentData['total_cod'] = $calculationService->getTotalCOD($shipmentObj);


            $shipmentData['tracking_no'] = $request->tracking_no ?: generate_tracking_no();
            // $shipmentData['pre_id'] = null;
            // $shipmentData['tracking_no'] = null;
            $shipmentData['delivery_address_id'] = $address->id;
            $waybill = null;

            // Start clean
            $shipmentData['tracking_no'] = null;
            $shipmentData['pre_id'] = null;

            $request->merge(['merchant_id' => Auth::id()]);

            if ($request->filled('tracking_no')) {
                // Normalize tracking_no for waybill lookup
                $trackingNo = $request->tracking_no;
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
                        // If no prefix after PE, try with ME prefix for merchant waybill
                        $possibleTrackingNos[] = 'ME' . $withoutPE;
                    }
                } elseif ($prefix !== 'ME' && $prefix !== 'DR' && $prefix !== 'PE') {
                    // If no prefix, try with ME prefix for merchant waybill
                    $possibleTrackingNos[] = 'ME' . $trackingNo;
                }

                // Validate waybill ownership & usage - try all possible formats
                $waybill = null;
                foreach ($possibleTrackingNos as $possibleTrackingNo) {
                    $waybill = MerchantWaybill::where('merchant_id', Auth::id())
                        ->where('tracking_no', $possibleTrackingNo)
                        ->lockForUpdate()
                        ->first();
                    if ($waybill) {
                        break;
                    }
                }
                if (!$waybill) {
                    DB::rollBack();
                    return sendResponse("Waybill not found for this merchant.", [], false, [
                        "Invalid tracking_no for the authenticated merchant"
                    ], 422);
                }

                if ($waybill->used) {
                    DB::rollBack();
                    return sendResponse("Waybill already used.", [], false, [
                        "Waybill already used previously"
                    ], 422);
                }

                $shipmentData['tracking_no'] = $waybill->tracking_no;
            } else {
                // FormRequest already injected pre_id when neither was provided,
                // but keep a final safeguard here:
                $shipmentData['pre_id'] = $request->pre_id ?: generate_pre_id();
            }


            $shipment = Shipment::create($shipmentData);
            $shipment->load(['consignee', 'deliveryAddress.state', 'deliveryAddress.governorate', 'deliveryAddress.place']);

            $zone = $shipment->zone();

            if ($zone) {
                $shipment->destination_owner_id = $zone->owner_id;
                $shipment->destination_owner_type = $zone->owner_type;
                $shipment->save();
            }

            $merchantAccount = Account::firstOrCreate(
                ['accountable_type' => User::class, 'accountable_id' => $shipment->merchant_id],
                ['parcel_value' => 0, 'balance' => 0]
            );
            // Use CalculationLogicService to get merchant COD (goods value only for COD shipments)
            $merchantCOD = $calculationService->getMerchantCOD($shipment);
            $merchantAccount->parcel_value += $merchantCOD;
            $merchantAccount->save();

            if ($request->tracking_no) {
                $merchantWaybill = MerchantWaybill::where("tracking_no", $shipmentData['tracking_no'])->first();
                if ($merchantWaybill) {
                    $merchantWaybill->used = 1;
                    $merchantWaybill->save();
                }
            }

            Transaction::create([
                "to_id" => Auth::user()->id,
                "to_type" => User::class,
                "shipment_id" => $shipment->id,
                "amount" => $shipment->total_cod,
                "type" => "merchant_created",
            ]);

            // DUAL-WRITE: Record in unified merchant transactions
            app(\App\Services\MerchantTransactionService::class)->recordRegistration($shipment);

            ShipmentInformation::create([
                'shipment_id' => $shipment->id,
                'merchant_id' => $shipmentData['merchant_id'],
                'unit_id' => $request->unit_id,
                'zone_id' => $zone->id ?? $request->zone_id,
                'package_id' => $request->package_id,
                'tracking_no' => $shipmentData['tracking_no'],
                'weight' => $request->weight,
                'height' => $request->height,
                'width' => $request->width,
                'length' => $request->length,
                'status' => $request->status ?? 0,
            ]);

            ShipmentDelivery::create(['shipment_id' => $shipment->id]);
            // ShipmentFinance::create(['shipment_tracking_no' => $shipment->tracking_no]);
            if ($shipment->tracking_no) {
                ShipmentFinance::create(['shipment_tracking_no' => $shipment->tracking_no]);
            }

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

            try {
                $link = app(AddressUpdateLinkService::class)->generate($shipment);
                $shipment->shipment_delivery->update([
                    "delivery_otp" => generate_otp(),
                    "otp_generated_at" => now()
                ]);
                $consignee->notify(new ShipmentCreatedNotification($shipment, $link['url'], $link['otp']));
            } catch (\Exception $e) {
                Log::error("Failed to generate address link/OTP for shipment {$shipment->tracking_no}: " . $e->getMessage());
            }

            $status1 = "ORDER_COLLECTED";
            shipmentHistory([
                "description" => status($status1)['description'],
                "shipment_id" => $shipment->id,
                "status" => status($status1)['label'],
                "time" => \Carbon\Carbon::now()->addSeconds(10)->toDateTimeString(),
            ]);
            activityLog("merchant_shipment_created", "Merchant shipment created with tracking #{$shipment->tracking_no}");

            DB::commit();

            return sendResponse(
                "Shipment created successfully.",
                new ShipmentResource($shipment->load("consignee", "shipment_items", "deliveryAddress"))
            );
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Database error occurred.", [], false, [$e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse("An unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }
    // public function destroy(Request $request, $id)
    // {
    //     $user = $request->user();

    //     // if (!$user) {
    //     //     return response()->json([
    //     //         'message' => 'You are not authorized as a merchant.',
    //     //         'success' => false,
    //     //     ], 403);
    //     // }

    //     // if (!$user->merchant) {
    //     //     return response()->json([
    //     //         'message' => 'User is not associated with a merchant.',
    //     //         'success' => false,
    //     //     ], 403);
    //     // }

    //     // $merchantId = $user->merchant->id;

    //     $shipment = Shipment::where('id', $id)
    //         ->where('merchant_id', $user->id)
    //         ->first();

    //     if (!$shipment) {
    //         return response()->json([
    //             'message' => 'Shipment not found.',
    //             'success' => false,
    //         ], 404);
    //     }

    //     // Uncomment these checks if needed to prevent deletion under certain conditions
    //     // if ($shipment->status !== ShipmentStatusEnum::CREATED) {
    //     //     return response()->json([
    //     //         'message' => 'You can only delete shipments in CREATED status.',
    //     //         'success' => false,
    //     //     ], 422);
    //     // }

    //     // if (!is_null($shipment->pickup_request_id)) {
    //     //     return response()->json([
    //     //         'message' => 'You cannot delete a shipment linked to a pickup request.',
    //     //         'success' => false,
    //     //     ], 422);
    //     // }

    //     try {
    //         $shipment->delete(); // Or update status to CANCELED if soft delete is preferred
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Failed to delete shipment.',
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }

    //     return response()->json([
    //         'message' => 'Shipment deleted successfully.',
    //         'success' => true,
    //     ]);
    // }

    public function finalizePreId(Shipment $shipment)
    {
        if ($shipment->tracking_no) {
            return sendResponse("Shipment already has a tracking number.", $shipment, true);
        }

        DB::transaction(function () use ($shipment) {
            $shipment->tracking_no = generate_tracking_no();
            $shipment->created_source = 'dashboard';
            $shipment->save();

            ShipmentFinance::firstOrCreate(['shipment_tracking_no' => $shipment->tracking_no]);

            shipmentHistory([
                "description" => "Converted PRE-ID to Tracking at warehouse",
                "shipment_id" => $shipment->id,
                "status" => "SORTED",
                "time" => now()->toDateTimeString(),
            ]);
        });

        return sendResponse("Shipment finalized with tracking number.", $shipment->fresh());
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
    public function registerShipment(RegisterShipmentRequest $request)
    {
        $request->validated();

        $request->merge([
            "shipper_id" => Shipper::where("name", "Parcel Express")->first()->id,
        ]);

        DB::beginTransaction();

        try {
            $shipment = null;
            $isNewShipment = false;
            $isFromUnassigned = false; // Track if shipment came from unified table
            $unassignedDriverId = null; // Store driver_id from pickup record
            $unassignedCreatedAt = null; // Store created_at from pickup record
            $wasCreatedWithoutTrackingNo = false; // Track if shipment was created without tracking_no
            $unassignedShipment = null; // Store pickup record object if found

            // Check if unassigned_shipment_id is provided and validate it exists
            $unassignedShipmentId = $request->input('unassigned_shipment_id');
            if ($unassignedShipmentId) {
                // Convert to integer if it's a string
                $unassignedShipmentId = is_numeric($unassignedShipmentId) ? (int) $unassignedShipmentId : $unassignedShipmentId;
                $unassignedShipment = MerchantPickupShipment::find($unassignedShipmentId);
                if (!$unassignedShipment) {
                    DB::rollBack();
                    return sendResponse(
                        "Unassigned shipment not found.",
                        [],
                        false,
                        ["The provided unassigned shipment ID ({$unassignedShipmentId}) does not exist."],
                        404
                    );
                }
            }

            $trackingNo = $request->tracking_no;

            if (!empty($trackingNo)) {

                $shipment = Shipment::where('tracking_no', $trackingNo)
                    ->lockForUpdate()
                    ->first();

                if (!$shipment) {
                    // جرّب الأول تدور في MerchantPickupShipment (only if not already found by ID)
                    if (!$unassignedShipment) {
                        $unassignedShipment = MerchantPickupShipment::where('shipment_tracking_no', $trackingNo)
                            ->whereNull('shipment_id') // Only unassigned records
                            ->lockForUpdate()
                            ->first();
                    }

                    if ($unassignedShipment) {
                        // عندك لوجيك تحويل من MerchantPickupShipment → Shipment
                        $shipment = $this->convertPickupToShipment($unassignedShipment, $request);
                        $trackingNo = $shipment->tracking_no; // لو حصل تعديل في رقم التتبع جوه الكونفرتر
                        $isFromUnassigned = true; // Mark that this shipment came from unified table
                        $unassignedDriverId = $unassignedShipment->driver_id; // Store driver_id for history operator
                        $unassignedCreatedAt = $unassignedShipment->created_at; // Store created_at for history time
                    } else {
                        // ❗ لا Shipment ولا Unassigned بنفس الرقم → اعتبرها شحنة جديدة بنفس tracking_no
                        $isNewShipment = true;
                        // نضمن إن الرقم يبقى متاح في باقي الكود
                        $request->merge(['tracking_no' => $trackingNo]);
                    }
                }
            } else {
                // 🔹 مفيش tracking_no مبعوت → شحنة جديدة بدون tracking_no (unassigned shipment)
                $trackingNo = generate_tracking_no();
                $isNewShipment = true;
                $wasCreatedWithoutTrackingNo = true; // Mark that this shipment was created without tracking_no

                // خليه متاح في باقي اللوجيك لو احتجناه
                $request->merge(['tracking_no' => $trackingNo]);
            }

            // Note: We already validated unassigned_shipment_id exists at the beginning (line 1104-1120)
            // If it was provided and not found, we would have already returned an error
            // So if we reach here and $unassignedShipmentId is set, $unassignedShipment should also be set

            // ================= Consignee Data =================
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
            $alternatePhoneSplit = splitPhoneNumber($consigneeData['alternatePhone']);

            $consigneeData['country_key_cellphone'] = $cellphoneSplit['country_code'];
            $consigneeData['country_key_alternatePhone'] = $alternatePhoneSplit['country_code'];
            $consigneeData['cellphone'] = $cellphoneSplit['national_number'];
            $consigneeData['alternatePhone'] = $alternatePhoneSplit['national_number'];

            $existingConsignee = Consignee::withoutGlobalScope(ConsigneeScope::class)
                ->where('cellphone', $consigneeData['cellphone'])
                ->where('name', $consigneeData['name'])
                ->where('country_id', $consigneeData['country_id'])
                ->first();

            $consignee = $existingConsignee ?: Consignee::create($consigneeData);

            // ================= Delivery Address (NEW) =================
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

            // نفس الفانكشن المستخدمة في store()
            $deliveryAddress = $this->createConsigneeAddress($consignee->id, $deliveryAddressInput);

            // لو الشحنة موجودة وفيها delivery_address_id قديم، ممكن تحدثه لو حابب:
            // if ($shipment && $shipment->delivery_address_id && $shipment->delivery_address_id !== $deliveryAddress->id) {
            //     $shipment->delivery_address_id = $deliveryAddress->id;
            //     $shipment->save();
            // }

            // ================= Shipment Data =================
            $shipmentData = $request->only([
                "notes",
                "payment_type",
                "country_id",
                "governorate_id",
                "state_id",
                "place_id",
                "value",
                "delivery_fee",
                "merchant_id",
                "fee_payer",
            ]);

            $shipmentData['created_by'] = Auth::id();
            $shipmentData['shipper_id'] = Shipper::pe()->id;
            $shipmentData['merchant_id'] = $shipmentData['merchant_id'] ?? Auth::id();
            $shipmentData['owner_id'] = Auth::user()->owner_id;
            $shipmentData['owner_type'] = Auth::user()->owner_type;
            $shipmentData['tracking_no'] = $trackingNo;       // سواء مولّد أو جاي من الفرونت
            $shipmentData['consignee_id'] = $consignee->id;
            $shipmentData['created_source'] = 'dashboard';

            // NEW: اربط الشحنة بالـ delivery address اللي لسه متكوّن
            $shipmentData['delivery_address_id'] = $deliveryAddress->id;

            if ($request->is_walkin == true) {
                $shipmentData['is_walkin'] = 1;
                $shipmentData['customer_name'] = $request->customer_name;
                $shipmentData['customer_phone'] = $request->customer_phone;
                $shipmentData['customer_id_card'] = $request->customer_id_card;
            }

            // ================= Commission =================
            // لو حابب تعتمد على state_id من العنوان بدل consignee:
            $stateIdForCommission = $deliveryAddress->state_id ?? $consigneeData['state_id'];

            $merchant_commission = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
                ->where('state_id', $stateIdForCommission)
                ->first();

            if (!$merchant_commission) {
                DB::rollBack();
                return sendResponse(
                    "Merchant commission not available for the selected state.",
                    [],
                    false,
                    ["Merchant commission is not available for the state"],
                    500
                );
            }

            // Use base_delivery_fee (without discount) for calculations, matching create-shipment behavior
            // Discounts are handled in later stages, not in the financial details display
            $shipmentData['delivery_fee'] = $merchant_commission->delivery_fee ?? $merchant_commission->base_delivery_fee;
            $shipmentData['delivery_fee_discount'] = $merchant_commission->delivery_discount_amount;

            $shipmentData['delivery_fee_before_discount'] = $merchant_commission->base_delivery_fee;
          

            // ================= COD Calculation =================
            $calculationService = app(\App\Services\CalculationLogicService::class);
            $shipmentObj = (object) $shipmentData;
            $shipmentData['total_cod'] = $calculationService->getTotalCOD($shipmentObj);
            $shipmentData['status'] = ShipmentStatusEnum::PICKED;
            // Set driver_id from unassigned shipment if available
            if ($unassignedShipment && $unassignedShipment->driver_id) {
                $shipmentData['driver_id'] = $unassignedShipment->driver_id;
            }

            //Adding created_source to shipment
            $shipmentData['created_source']="unCreated";

            // ================= Create / Update Shipment =================
            if ($isNewShipment) {
                // شحنة جديدة → create
                $shipment = Shipment::create($shipmentData);

                // If there's an unassigned shipment linked to this, update its shipment_id
                if ($unassignedShipment && !$unassignedShipment->shipment_id) {
                    $unassignedShipment->shipment_id = $shipment->id;
                    $unassignedShipment->save();
                }
            } else {
                // شحنة موجودة أو جاية من Unassigned → update
                $shipment->update($shipmentData);

                // If there's an unassigned shipment linked to this, update its shipment_id
                if ($unassignedShipment && !$unassignedShipment->shipment_id) {
                    $unassignedShipment->shipment_id = $shipment->id;
                    $unassignedShipment->save();
                }
            }

            $shipment->load('consignee', 'deliveryAddress'); // NEW: حمّل كمان العنوان

            // ================= Set hub information =================
            $hubService = app(\App\Services\HubInformationService::class);
            $hubService->setFinalHub($shipment);
            // Call setInitialCurrentHub for new shipments OR shipments from UnassignedShipment
            // This ensures current_owner/from_owner are explicitly NULL until first inbound/unload
            if ($isNewShipment || $isFromUnassigned) {
                $hubService->setInitialCurrentHub($shipment);
            }
            $shipment->save();

            // ================= Merchant Account / COD =================
            $merchantAccount = Account::firstOrCreate(
                [
                    'accountable_type' => User::class,
                    'accountable_id' => $shipment->merchant_id
                ],
                [
                    'parcel_value' => 0,
                    'balance' => 0
                ]
            );

            $merchantCOD = $calculationService->getMerchantCOD($shipment);
            $merchantAccount->parcel_value += $merchantCOD;
            $merchantAccount->save();

            // ================= Waybill Mark as Used =================
            $merchantWaybill = MerchantWaybill::where("tracking_no", $trackingNo)->first();
            if ($merchantWaybill) {
                $merchantWaybill->used = 1;
                $merchantWaybill->save();
            }

            // ================= Transaction =================
            $merchantCOD = $shipment->total_cod > 0 ? $shipment->total_cod : $shipment->delivery_fee;
            Transaction::create([
                "to_id" => $shipmentData['merchant_id'],
                "to_type" => User::class,
                "shipment_id" => $shipment->id,
                "amount" => $merchantCOD,
                "type" => "merchant_created",
            ]);

            // DUAL-WRITE: Record in unified merchant transactions
            app(\App\Services\MerchantTransactionService::class)->recordRegistration($shipment);




            // ================= ShipmentInformation =================
            ShipmentInformation::updateOrCreate(
                ['shipment_id' => $shipment->id],
                [
                    'merchant_id' => $shipmentData['merchant_id'],
                    'unit_id' => $request->unit_id,
                    'zone_id' => $request->zone_id,
                    'package_id' => $request->package_id,
                    'tracking_no' => $shipmentData['tracking_no'],
                    'weight' => $request->weight,
                    'height' => $request->height,
                    'width' => $request->width,
                    'length' => $request->length,
                    'status' => $request->status ?? 0,
                ]
            );

            ShipmentDelivery::firstOrCreate([
                'shipment_id' => $shipment->id
            ]);

            // Only create ShipmentFinance if tracking_no exists
            if ($shipment->tracking_no) {
                ShipmentFinance::firstOrCreate([
                    'shipment_tracking_no' => $shipment->tracking_no
                ]);
            }

            // ================= Items =================
            $shipment->shipment_items()->delete();
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

            // ================= Pickup Task / Shipment =================
            // PRIORITY 1: Use $unassignedShipment if already found by unassigned_shipment_id
            // This is the most reliable for Case 9 where tracking_no was initially NULL
            $existingPickupShipment = $unassignedShipment;

            // PRIORITY 2: Search by tracking_no or shipment_id if not found by ID
            if (!$existingPickupShipment) {
                $existingPickupShipment = MerchantPickupShipment::where('shipment_tracking_no', $shipment->tracking_no)
                    ->first();

                // Fallback: check by shipment_id
                if (!$existingPickupShipment && $shipment->id) {
                    $existingPickupShipment = MerchantPickupShipment::where('shipment_id', $shipment->id)->first();
                }
            }

            if ($existingPickupShipment) {
                // Update existing record with shipment details
                $existingPickupShipment->update([
                    'shipment_id' => $shipment->id,
                    'shipment_tracking_no' => $shipment->tracking_no,
                    'status' => \App\Enums\MerchantPickupTaskStatusEnum::PICKED
                ]);
            } else {
                // Find an existing task with status 'created', 'pending', or 'to_pickup' - do NOT auto-create
                $task = MerchantPickupTask::where('merchant_id', $request->merchant_id)
                    ->whereIn('status', [
                        \App\Enums\MerchantPickupTaskStatusEnum::CREATED,
                        \App\Enums\MerchantPickupTaskStatusEnum::PENDING,
                        \App\Enums\MerchantPickupTaskStatusEnum::TO_PICKUP,
                        \App\Enums\MerchantPickupTaskStatusEnum::PICKED
                    ])
                    ->latest()
                    ->first();

                // Only create MerchantPickupShipment if a valid task exists
                if ($task) {
                    $existingPickupShipment=MerchantPickupShipment::create([
                        'pickup_task_id' => $task->id,
                        'merchant_id' => $request->merchant_id,
                        'shipment_tracking_no' => $shipment->tracking_no,
                        'shipment_id' => $shipment->id,
                        'driver_id' => $task->driver_id,
                        'pickup_request_id' => $task->pickup_request_id,
                        'status' => \App\Enums\MerchantPickupTaskStatusEnum::PICKED
                    ]);
                }
            }
            


            // ================= Address Update Link & Activity =================
            try {
                $addressService = new AddressService();
                $tokenData = $addressService->generateAddressUpdateToken($shipment);
                $shipment->consignee->address_update_url = $tokenData['url'];
                $shipment->consignee->save();
                activityLog("address_token_generated", "Address update token generated for shipment #{$shipment->tracking_no}");
            } catch (Exception $e) {
                Log::error("Failed to generate address update token for shipment {$shipment->tracking_no}: " . $e->getMessage());
            }

            // ================= Delivery OTP =================
            $shipment->shipment_delivery->update([
                "delivery_otp" => generate_otp(),
                "otp_generated_at" => now()
            ]);
            $shipment->shipment_delivery->save();

            // ================= History =================
            if ($isFromUnassigned) {
                // For shipments from unified table, create only PICKED history with record's created_at
                // Get driver user info for operator display
                $driverUser = null;
                $operatorInfo = 'System';
                if ($unassignedDriverId) {
                    $driverUser = User::find($unassignedDriverId);
                    if ($driverUser) {
                        $driverUser->load('roles');
                        if ($driverUser->driver) {
                            $operatorInfo = $driverUser->name . " - " . ($driverUser->roles->first()->name ?? 'Driver');
                        } else {
                            $operatorInfo = $driverUser->name . " - " . ($driverUser->roles->first()->name ?? 'User');
                        }
                    }
                }

                $historyDataForCollected = [
                    "description" => status(ShipmentStatusEnum::PICKED)['description'],
                    "shipment_id" => $shipment->id,
                    "status" => ShipmentStatusEnum::PICKED, // Use enum constant directly for "PICKED" in capitals
                    "time" => $unassignedCreatedAt, // Use created_at from pickup record
                    "operatorId" => $unassignedDriverId, // Use driver from pickup record
                    "operatorInfo" => $operatorInfo, // Use driver's formatted name
                    "proof" => $existingPickupShipment->pickup_proof,
                ];
                shipmentHistory($historyDataForCollected);
            } elseif ($wasCreatedWithoutTrackingNo || empty($shipment->tracking_no)) {
                $operatorInfoForUnassigned = 'System';
                $operatorIdForUnassigned = Auth::id();

                if ($shipment->driver_id) {
                    $driverUserForUnassigned = User::find($shipment->driver_id);
                    if ($driverUserForUnassigned) {
                        $driverUserForUnassigned->load('roles');
                        $operatorInfoForUnassigned = $driverUserForUnassigned->name . " - " . ($driverUserForUnassigned->roles->first()->name ?? 'Driver');
                        $operatorIdForUnassigned = $shipment->driver_id;
                    }
                }
                // For unassigned shipments created without tracking_no, create PICKED history
                $timestamp = Carbon::now()->addSeconds(10)->toDateTimeString();
                $statusData = status(ShipmentStatusEnum::PICKED);
                $historyDataForCollected = [
                    "description" => $statusData['description'] ?? 'Shipment is picked from the merchant.',
                    "shipment_id" => $shipment->id,
                    "status" => ShipmentStatusEnum::PICKED,
                    "time" => $timestamp,
                    "operatorId" => $operatorIdForUnassigned,
                    "operatorInfo" => $operatorInfoForUnassigned,
                    "proof" => $existingPickupShipment->pickup_proof,
                ];
                shipmentHistory($historyDataForCollected);
            } else {
                // For regular shipments with tracking_no, create ORDER_COLLECTED history
                $timestamp = Carbon::now()->addSeconds(10)->toDateTimeString();
                $statusData = status(ShipmentStatusEnum::ORDER_COLLECTED);
                $historyDataForCollected = [
                    "description" => $statusData['description'] ?? 'The shipment has been successfully collected.',
                    "shipment_id" => $shipment->id,
                    "status" => $statusData['label'] ?? ShipmentStatusEnum::ORDER_COLLECTED,
                    "time" => $timestamp
                ];
                shipmentHistory($historyDataForCollected);
            }

            activityLog("merchant_shipment_registered", "Merchant shipment registered/updated with tracking #{$shipment->tracking_no}");

            // ================= Merchant Settings & Notification =================
            $settings = MerchantSetting::firstOrCreate(
                [
                    'merchant_id' => $shipmentData['merchant_id']
                ],
                [
                    'created_shipment_notification' => 1
                ]
            );

            if ($settings->created_shipment_notification) {
                $link = app(AddressUpdateLinkService::class)->generate($shipment);
                $consignee->notify(new ShipmentCreatedNotification(
                    $shipment,
                    $link['url'],
                    $link['otp']
                ));
            }
            // Adding pickup bonus to driver
            if ($shipment->driver_id) {
                ShipmentPickupFactory::addPickupBonus($shipment, $shipment->driver_id);
            }
              $this->adminCounterService->broadcastToAllAdmins();
            DB::commit();

            return sendResponse(
                "Shipment registered and updated successfully.",
                new ShipmentResource($shipment->load("consignee", "shipment_items", "deliveryAddress"))
            );
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Database error occurred.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("An unexpected error occurred.", [], false, [$e->getMessage()], 500);
        }
    }



    /**
     * Preview Shipment Import
     *
     * @OA\Post(
     *     path="/merchant/shipments/import_preview",
     *     summary="Preview bulk shipment import",
     *     description="
     * Preview and validate bulk shipment import before processing.
     *
     * **Features:**
     * - Excel/CSV file processing
     * - Data validation preview
     * - Error identification
     * - Import statistics
     *
     * **Security:**
     * - File type validation
     * - Size limitations
     * - Malware scanning
     * ",
     *     operationId="previewShipmentImport",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Import file data",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import preview generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Import preview ready"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function import_preview(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
            ]);

            $file = $request->file('file');
            $merchantId = Auth::id();

            // Create preview import instance with merchant ID
            $preview = new MerchantShipmentImportPreview($merchantId);
            Excel::import($preview, $file);

            $previewData = $preview->getPreviewData();

            $response = [
                'success' => true,
                'message' => 'Import preview generated successfully.',
                'data' => $previewData,
                'statistics' => [
                    'importable_count' => count($previewData['importableShipments']),
                    'unimportable_count' => count($previewData['unimportableShipments']),
                    'total_count' => count($previewData['importableShipments']) + count($previewData['unimportableShipments'])
                ]
            ];

            // Check for missing dependencies
            $hasMissingDependencies = !empty($previewData['missingCountries']) ||
                !empty($previewData['missingGovernorates']) ||
                !empty($previewData['missingStates']);

            if ($hasMissingDependencies) {
                $response['message'] = 'Preview generated with invalid location data. Please check country/governorate/state names.';
                $response['has_missing_dependencies'] = true;
            }

            return response()->json($response);
        } catch (Exception $e) {
            Log::error('Merchant Shipment Import Preview Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during import preview.',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Execute Bulk Shipment Import
     *
     * @OA\Post(
     *     path="/merchant/shipments/import",
     *     summary="Execute bulk shipment import",
     *     description="
     * Process and import validated bulk shipments into the system.
     *
     * **Features:**
     * - Batch processing
     * - Transaction safety
     * - Progress tracking
     * - Error handling
     *
     * **Security:**
     * - Pre-validation required
     * - Rate limiting
     * - Resource monitoring
     * ",
     *     operationId="executeShipmentImport",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Import execution data",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import executed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipments imported successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
                'confirmed' => 'sometimes|boolean'
            ]);

            $file = $request->file('file');
            $merchantId = Auth::id();
            $confirmed = $request->input('confirmed', false);

            // If not confirmed, run preview first to check for issues
            if (!$confirmed) {
                $preview = new MerchantShipmentImportPreview($merchantId);
                Excel::import($preview, $file);
                $previewData = $preview->getPreviewData();

                $hasMissingDependencies = !empty($previewData['missingCountries']) ||
                    !empty($previewData['missingGovernorates']) ||
                    !empty($previewData['missingStates']);

                if ($hasMissingDependencies) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Import cannot proceed due to missing dependencies.',
                        'data' => $previewData,
                        'requires_confirmation' => true
                    ], 422);
                }
            }

            // Proceed with actual import
            $import = new MerchantShipmentImport($merchantId);
            Excel::import($import, $file);

            $results = $import->getImportResults();

            return response()->json([
                'success' => true,
                'message' => 'Shipments imported successfully.',
                'data' => $results,
                'statistics' => [
                    'imported' => $results['imported'],
                    'skipped' => $results['skipped'],
                    'total_processed' => $results['imported'] + $results['skipped']
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Merchant Shipment Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during import.',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Download Shipment Import Template
     *
     * @OA\Get(
     *     path="/merchant/shipments/import_template",
     *     summary="Download shipment import template",
     *     description="
     * Download Excel template for bulk shipment import with proper formatting and examples.
     *
     * **Features:**
     * - Pre-formatted Excel template
     * - Sample data included
     * - Column validation rules
     * - Format guidelines
     *
     * **Security:**
     * - Merchant authentication required
     * - Download tracking
     * ",
     *     operationId="downloadImportTemplate",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Template downloaded successfully",
     *         @OA\MediaType(
     *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     )
     * )
     */
    public function import_template()
    {
        try {
            $merchantId = Auth::id();
            $fileName = "merchant_shipment_import_template_{$merchantId}.xlsx";
            $templatePath = "templates/merchant_shipments/{$fileName}";

            // Try to generate Excel template
            try {
                $export = new MerchantShipmentImportTemplateExport();
                $filePath = storage_path("app/public/{$templatePath}");

                // Ensure directory exists
                $directory = dirname($filePath);
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                Excel::store($export, "public/{$templatePath}");

                if (file_exists($filePath) && is_readable($filePath)) {
                    return Response::download($filePath, 'merchant_shipment_import_template.xlsx')->deleteFileAfterSend(true);
                }
            } catch (Exception $e) {
                Log::warning('Excel template generation failed: ' . $e->getMessage());
            }

            // Fallback to CSV template
            $csvContent = "tracking_no,recipient_country,recipient_state,recipient_city,recipient_name,recipient_cellphone,recipient_street_address,cod,payment_type,recipient_alternate_phone,recipient_zipcode,declare,weight_g,ofd_times\n";
            $csvContent .= "STICKER001,Oman,Muscat,Muscat,Ahmed Al-Balushi,96899123456,\"Ruwi, Near City Centre Mall\",25.50,COD,96899654321,100,Electronics,500,2\n";
            $csvContent .= "STICKER002,Oman,Dhofar,Salalah,Fatima Al-Rashid,96897123456,\"Al-Dahariz, Building 15\",15.75,PREPAID,,112,Clothing,300,1\n";

            $tempFile = tempnam(sys_get_temp_dir(), 'merchant_shipment_template_') . '.csv';
            file_put_contents($tempFile, $csvContent);

            if (file_exists($tempFile) && is_readable($tempFile)) {
                return Response::download($tempFile, 'merchant_shipment_import_template.csv')->deleteFileAfterSend(true);
            }

            throw new Exception('Unable to generate template file');
        } catch (Exception $e) {
            Log::error('Merchant Shipment Template Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate import template.',
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get Merchant Consignees
     *
     * @OA\Get(
     *     path="/merchant/consignees",
     *     summary="Get list of merchant consignees",
     *     description="
     * Retrieve list of consignees associated with the authenticated merchant.
     *
     * **Features:**
     * - Merchant-specific consignee list
     * - Contact information included
     * - Address details provided
     * - Delivery history summary
     *
     * **Security:**
     * - Merchant authentication required
     * - Data scoped to merchant only
     * ",
     *     operationId="getMerchantConsignees",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Consignees retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Consignees retrieved successfully"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function getConsignees()
    {
        $consignees_ids = Shipment::where('merchant_id', Auth::id())
            ->pluck('consignee_id');
        $consignees = Consignee::whereIn('id', $consignees_ids)->select('id', 'name')->get();
        return sendResponse("Consignees retrieved successfully.", $consignees);
    }

    /**
     * Calculate contribution to merchant totals when fee payer is the customer.
     * The merchant receives the shipment value only (no fee deduction here).
     *
     * Example: value=10 → contribution +10
     */
    protected function customerFeePayerShipment(float $amountSum): float
    {
        return max(0.0, $amountSum);
    }

    /**
     * Calculate contribution to merchant totals when fee payer is the merchant.
     * The merchant receives the shipment value minus the delivery fee.
     *
     * Example: value=10, fee=2 → contribution +8
     */
    protected function merchantFeePayerShipment(float $amountSum, float $feeSum): float
    {
        return max(0.0, $amountSum - $feeSum);
    }

    /**
     * Discount contribution when the fee is paid by the customer.
     * The discount benefit is credited to the merchant.
     *
     * Example: discount=0.4 → contribution +0.4
     */
    protected function customerFeePayerShipmentDiscount(float $discountSum): float
    {
        return max(0.0, $discountSum);
    }

    /**
     * Discount contribution when the fee is paid by the merchant.
     * The discount reduces the merchant's fee burden (adds back the discount).
     *
     * Example: fee=2, discount=0.4 → contribution +0.4 (final = value - (fee - discount))
     */
    protected function merchantFeePayerShipmentDiscount(float $discountSum): float
    {
        return max(0.0, $discountSum);
    }

    /**
     * Calculate the overall (all-time) merchant shipment operations total.
     *
     * Rules (aligned with dashboard expectation):
     * - If fee payer is customer (or shipper/empty):
     *   Merchant gets the shipment value only, PLUS the discount value credited to the merchant.
     *   Example: value=10, fee=2, discount=0.4 → customer pays 1.6; merchant total += 10 + 0.4 = 10.4
     * - If fee payer is merchant:
     *   Merchant gets the shipment value minus the effective delivery fee (fee - discount).
     *   Example: value=10, fee=2, discount=0.4 → effective fee=1.6; merchant total += 10 - 1.6 = 8.4
     */
    protected function calculateShipmentOperations(int $merchantId): float
    {
        // Treat 'customer' and 'shipper' as no-fee-deduction for merchant totals:
        // base is the shipment value only
        $totalCustomerLikeValue = Shipment::where('merchant_id', $merchantId)
            ->where('status', '!=', 'cancelled')
            ->whereIn(DB::raw("LOWER(COALESCE(fee_payer,''))"), ['customer', 'shipper', ''])
            ->sum(DB::raw('COALESCE(value,0)'));

        // Credit the discount back to the merchant when customer/shipper pays the fee
        $totalCustomerLikeDiscounts = Shipment::where('merchant_id', $merchantId)
            ->where('status', '!=', 'cancelled')
            ->whereIn(DB::raw("LOWER(COALESCE(fee_payer,''))"), ['customer', 'shipper', ''])
            ->sum(DB::raw('COALESCE(delivery_fee_discount,0)'));

        // For 'merchant' fee payer: merchant receives value minus effective delivery fee (fee - discount)
        $totalMerchantValue = Shipment::where('merchant_id', $merchantId)
            ->where('status', '!=', 'cancelled')
            ->whereRaw("LOWER(COALESCE(fee_payer,'')) = 'merchant'")
            ->sum(DB::raw('COALESCE(value,0)'));

        $totalMerchantEffectiveFees = Shipment::where('merchant_id', $merchantId)
            ->where('status', '!=', 'cancelled')
            ->whereRaw("LOWER(COALESCE(fee_payer,'')) = 'merchant'")
            ->sum(DB::raw('GREATEST(COALESCE(delivery_fee,0) - COALESCE(delivery_fee_discount,0), 0)'));

        // Final: (customer/shipper value + discounts) + (merchant value - effective fees)
        return (float) $totalCustomerLikeValue
            + (float) $totalCustomerLikeDiscounts
            + max(0.0, (float) $totalMerchantValue - (float) $totalMerchantEffectiveFees);
    }

    /**
     * Calculate the current-month merchant shipment operations total.
     * Same logic as overall, scoped to current month/year.
     *
     * - Customer/shipper: total += value + discount
     * - Merchant: total += value - max(fee - discount, 0)
     */
    protected function calculateThisMonthShipmentOperations(int $merchantId): float
    {
        $thisMonthCustomerLikeValue = Shipment::where('merchant_id', $merchantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', '!=', 'cancelled')
            ->whereIn(DB::raw("LOWER(COALESCE(fee_payer,''))"), ['customer', 'shipper', ''])
            ->sum(DB::raw('COALESCE(value,0)'));

        $thisMonthCustomerLikeDiscounts = Shipment::where('merchant_id', $merchantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', '!=', 'cancelled')
            ->whereIn(DB::raw("LOWER(COALESCE(fee_payer,''))"), ['customer', 'shipper', ''])
            ->sum(DB::raw('COALESCE(delivery_fee_discount,0)'));

        $thisMonthMerchantValue = Shipment::where('merchant_id', $merchantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', '!=', 'cancelled')
            ->whereRaw("LOWER(COALESCE(fee_payer,'')) = 'merchant'")
            ->sum(DB::raw('COALESCE(value,0)'));

        $thisMonthMerchantEffectiveFees = Shipment::where('merchant_id', $merchantId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', '!=', 'cancelled')
            ->whereRaw("LOWER(COALESCE(fee_payer,'')) = 'merchant'")
            ->sum(DB::raw('GREATEST(COALESCE(delivery_fee,0) - COALESCE(delivery_fee_discount,0), 0)'));

        return (float) $thisMonthCustomerLikeValue
            + (float) $thisMonthCustomerLikeDiscounts
            + max(0.0, (float) $thisMonthMerchantValue - (float) $thisMonthMerchantEffectiveFees);
    }

    /**
     * Calculate merchant account balance using the same logic as the accounts page.
     * This calculates balance from delivered shipments: COD - fees - settlements
     * Uses all-time data to show total current balance
     */
    protected function calculateMerchantAccountBalance(int $merchantId): float
    {
        // Use all-time data (very early date to current) to get total balance
        $from = \Carbon\Carbon::parse('2000-01-01')->startOfDay();
        $to = now()->endOfDay();

        $hasShipmentStatus = Schema::hasColumn('shipments', 'status');
        $hasDA = Schema::hasTable('driver_shipment_assignments')
            && Schema::hasColumn('driver_shipment_assignments', 'delivered_at')
            && Schema::hasColumn('driver_shipment_assignments', 'status')
            && Schema::hasColumn('driver_shipment_assignments', 'shipment_tracking_no');

        $deliveryFeeExpr = "COALESCE(o.delivery_fee,0)";
        $returnFeeExpr = "CASE WHEN COALESCE(o.is_return, 0) = 1 THEN COALESCE(cc.return_fee, 0) ELSE 0 END";

        // COD for merchant: Only COD shipments contribute, uses value field (goods value only)
        $codExpr = "CASE
            WHEN UPPER(o.payment_type)='COD' THEN COALESCE(o.value, 0)
            ELSE 0
        END";

        $shipmentsQ = DB::table('shipments as o')
            ->where(function ($q) use ($merchantId) {
                $q->where('o.merchant_id', $merchantId)
                    ->orWhere('o.shipper_id', $merchantId);
            });

        if ($hasShipmentStatus) {
            $shipmentsQ->whereRaw("UPPER(TRIM(o.status)) = 'DELIVERED'");
        }

        $dateCol = 'o.created_at';
        if ($hasDA) {
            $deliveredSub = DB::table('driver_shipment_assignments')
                ->select('shipment_tracking_no', DB::raw('MAX(delivered_at) as delivered_at'))
                ->whereRaw("UPPER(TRIM(status)) = 'DELIVERED'")
                ->groupBy('shipment_tracking_no');

            $shipmentsQ->joinSub($deliveredSub, 'da', function ($j) {
                $j->on('da.shipment_tracking_no', '=', 'o.tracking_no');
            });

            $dateCol = 'da.delivered_at';
        }

        $shipmentsQ->leftJoin('addresses as addr', 'addr.id', '=', 'o.delivery_address_id');
        $shipmentsQ->join('merchant_commission_transactions as cc', 'cc.shipment_id', '=', 'o.id');

        $shipmentsQ->whereBetween($dateCol, [$from, $to]);

        $baseFeeExpr = "COALESCE(cc.base_delivery_fee, 0)";
        $discountExpr = "COALESCE(cc.delivery_discount_amount, 0)";
        $effectiveExpr = "COALESCE(cc.delivery_fee, GREATEST($baseFeeExpr - $discountExpr, 0))";
        $feeOnMerchantExpr = "CASE WHEN LOWER(o.fee_payer)='merchant' THEN $effectiveExpr ELSE 0 END";
        $feeCreditExpr = "CASE WHEN LOWER(o.fee_payer) <> 'merchant' THEN GREATEST($baseFeeExpr - $effectiveExpr, 0) ELSE 0 END";

        $shipments = $shipmentsQ->select([
            DB::raw("MAX($dateCol) as row_date"),
            'o.tracking_no',
            DB::raw("MAX($codExpr) as cod_for_merchant"),
            DB::raw("MAX($feeOnMerchantExpr) as fee_on_merchant"),
            DB::raw("MAX($feeCreditExpr) as fee_credit"),
            DB::raw("MAX(CASE WHEN LOWER(o.fee_payer)='merchant' THEN $returnFeeExpr ELSE 0 END) as return_fee_on_merchant"),
        ])
            ->groupBy('o.tracking_no')
            ->get();

        $totalCOD = (float) $shipments->sum('cod_for_merchant');
        $totalDeliveryFeesOnMerchant = (float) $shipments->sum('fee_on_merchant');
        $totalReturnFeesOnMerchant = (float) $shipments->sum('return_fee_on_merchant');
        $totalFeesOnMerchant = $totalDeliveryFeesOnMerchant + $totalReturnFeesOnMerchant;
        $totalFeeCredits = (float) $shipments->sum('fee_credit');
        $netFees = $totalFeesOnMerchant - $totalFeeCredits;

        $totalSettlements = (float) DB::table('merchant_settlements')
            ->where('merchant_id', $merchantId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $currentBalance = $totalCOD - $netFees - $totalSettlements;

        return (float) $currentBalance;
    }

    protected function convertPickupToShipment(MerchantPickupShipment $unassigned, Request $request): Shipment
    {
        // هنا بتعمل المابينج حسب الأعمدة اللي عندك في الجدولين
        // عدّل الأعمدة حسب الـ schema عندك

        $shipment = new Shipment();

        $shipment->tracking_no = $unassigned->shipment_tracking_no;
        $shipment->merchant_id = $unassigned->merchant_id ?? ($request->merchant_id ?? Auth::id());
        $shipment->driver_id = $unassigned->driver_id; // Preserve driver_id from record
        $shipment->shipper_id = Shipper::pe()->id; // أو من unassigned لو عندك عمود shipper_id

        // لو عندك أعمدة إلزامية (NOT NULL) في جدول shipments لازم تتملي هنا
        $shipment->owner_id = Auth::user()->owner_id;
        $shipment->owner_type = Auth::user()->owner_type;
        
        // CRITICAL: Explicitly set current_owner/from_owner to NULL
        // These should only be set by sorting operations (inbound/unload)
        $shipment->current_owner_type = null;
        $shipment->current_owner_id = null;
        $shipment->current_hub_id = null;
        $shipment->from_owner_type = null;
        $shipment->from_owner_id = null;
        $shipment->from_hub_id = null;

        // مثال لو عندك status_id أو core_status_id إلزامي
        // $shipment->status_id = Status::where('name', 'REGISTERED')->first()->id ?? null;

        // لو في بيانات تانية مشتركة بين الجدولين تقدر تنقلها زي كده:
        // $shipment->country_id      = $unassigned->country_id;
        // $shipment->governorate_id  = $unassigned->governorate_id;
        // $shipment->state_id        = $unassigned->state_id;
        // $shipment->place_id        = $unassigned->place_id;
        // ... الخ

        $shipment->created_by = Auth::id();

        $shipment->save();

        // Update unassigned shipment with the created shipment_id
        $unassigned->shipment_id = $shipment->id;
        $unassigned->save();

        // لو المفروض بعد ما تتحوّل تتشال من جدول unassigned:
        // $unassigned->delete();

        return $shipment;
    }
    /**
     * Delete Shipment
     *
     * @OA\Delete(
     *     path="/merchant/shipments/{id}",
     *     summary="Delete a shipment",
     *     description="Delete a shipment if it is in a deletable state (created or pending) and belongs to the merchant.",
     *     operationId="deleteMerchantShipment",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Shipment ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Shipment deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Cannot delete shipment in current status",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $merchantId = Auth::id();

            // Debug: Log the query parameters
            \Log::info("Attempting to delete shipment", [
                'shipment_id' => $id,
                'merchant_id' => $merchantId,
                'user' => Auth::user()?->email
            ]);

            // First, check if the shipment exists at all
            $shipmentExists = Shipment::where('id', $id)->first();

            if (!$shipmentExists) {
                \Log::warning("Shipment does not exist in database", ['shipment_id' => $id]);
                return sendResponse("Shipment not found.", [], false, ["Shipment with ID {$id} does not exist."], 404);
            }

            // Check if it belongs to this merchant
            if ($shipmentExists->merchant_id != $merchantId) {
                \Log::warning("Shipment belongs to different merchant", [
                    'shipment_id' => $id,
                    'shipment_merchant_id' => $shipmentExists->merchant_id,
                    'auth_merchant_id' => $merchantId
                ]);
                return sendResponse("Shipment not found.", [], false, ["This shipment does not belong to you."], 403);
            }

            $shipment = $shipmentExists;

            // Define allowed statuses for deletion
            $allowedStatuses = ['created', 'pending', 'saved', 'CREATED', 'PENDING', 'SAVED', 'TO_PICKUP', 'to_pickup'];

            if (!in_array($shipment->status, $allowedStatuses)) {
                \Log::info("Cannot delete shipment due to status", [
                    'shipment_id' => $id,
                    'status' => $shipment->status
                ]);
                return sendResponse("Cannot delete shipment. Status is {$shipment->status}.", [], false, ["Cannot delete shipment in current status."], 403);
            }

            $shipment->delete();

            \Log::info("Shipment deleted successfully", ['shipment_id' => $id]);

            return sendResponse("Shipment deleted successfully.", []);
        } catch (\Exception $e) {
            \Log::error("Error deleting shipment", [
                'shipment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return sendResponse("An error occurred while deleting the shipment.", [], false, [$e->getMessage()], 500);
        }
    }
    public function merchantExceptionShipments(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return sendResponse(
                'You are not authorized as a merchant.',
                [],
                false,
                [],
                403
            );
        }

        $perPage = (int) $request->input('per_page', 20);

        $shipmentsPaginator = Shipment::query()
            ->where('merchant_id', $user->id)
            ->where('in_exception', true)
            ->when($request->filled('tracking_no'), function ($q) use ($request) {
                $q->where('tracking_no', 'LIKE', '%' . trim($request->tracking_no) . '%');
            })
            ->when($request->filled('from_date'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->from_date);
            })
            ->when($request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->to_date);
            })
            ->with([
                'consignee',
                'deliveryAddress',
                'core_exception',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $shipmentsPaginator->getCollection()->map(function ($shipment) {
            $exceptionHistory = $shipment->core_exception;

            return [
                'id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'status' => $shipment->status,
                'in_exception' => (bool) $shipment->in_exception,

                'consignee_name' => optional($shipment->consignee)->name,
                'consignee_phone' => optional($shipment->consignee)->phone,
                'delivery_address' => optional($shipment->deliveryAddress)->full_address ?? null,

                'exception_type' => optional($exceptionHistory)->type,
                'exception_status_name' => optional($exceptionHistory)->name,
                'exception_description' => optional($exceptionHistory)->description,
                'exception_at' => optional($exceptionHistory)->time ?? optional($exceptionHistory)->created_at,
                'exception_proof_path' => optional($exceptionHistory)->proof,
            ];
        });

        $response = [
            'data' => $data,
            'current_page' => $shipmentsPaginator->currentPage(),
            'last_page' => $shipmentsPaginator->lastPage(),
            'per_page' => $shipmentsPaginator->perPage(),
            'total' => $shipmentsPaginator->total(),
        ];

        return sendResponse('Merchant exception shipments list', $response, true);
    }

    public function merchantDeliveredShipments(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return sendResponse(
                'You are not authorized as a merchant.',
                [],
                false,
                [],
                403
            );
        }

        $perPage = (int) $request->input('per_page', 20);

        $shipmentsPaginator = \App\Models\Shipment::query()
            ->where('merchant_id', $user->id)
            ->where('status', \App\Enums\ShipmentStatusEnum::DELIVERED)
            ->when($request->filled('tracking_no'), function ($q) use ($request) {
                $q->where('tracking_no', 'LIKE', '%' . trim($request->tracking_no) . '%');
            })
            ->when($request->filled('from_date'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->from_date);
            })
            ->when($request->filled('to_date'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->to_date);
            })
            ->with([
                'consignee',
                'deliveryAddress',
                'core_delivered',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $shipmentsPaginator->getCollection()->map(function ($shipment) {
            $deliveredHistory = $shipment->core_delivered;

            return [
                'id' => $shipment->id,
                'tracking_no' => $shipment->tracking_no,
                'status' => $shipment->status,

                'consignee_name' => optional($shipment->consignee)->name,
                'consignee_phone' => optional($shipment->consignee)->phone,
                'delivery_address' => optional($shipment->deliveryAddress)->full_address ?? null,

                'delivered_at' => $shipment->delivered_at ?? optional($deliveredHistory)->time ?? optional($deliveredHistory)->created_at,
                'delivered_proof' => optional($deliveredHistory)->proof_url,
                'delivered_proof_path' => optional($deliveredHistory)->proof,
            ];
        });

        $response = [
            'data' => $data,
            'current_page' => $shipmentsPaginator->currentPage(),
            'last_page' => $shipmentsPaginator->lastPage(),
            'per_page' => $shipmentsPaginator->perPage(),
            'total' => $shipmentsPaginator->total(),
        ];

        return sendResponse('Merchant delivered shipments list', $response, true);
    }
}
