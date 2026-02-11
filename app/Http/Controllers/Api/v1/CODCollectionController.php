<?php

namespace App\Http\Controllers\Api\v1;


use App\Exports\CODCollectionExport;
use App\Http\Requests\StoreCODCollectionRequest;
use App\Http\Requests\StoreHoldingRunsheet;
use App\Http\Requests\StoreHoldingRunsheetRequest;
use App\Http\Resources\GeneralResource;
use App\Models\Account;
use App\Models\MerchantCommission;
use App\Models\CompanyCommission;
use App\Models\DriverBonus;
use App\Models\DriverBonusesTransaction;
use App\Models\DriverCommission;
use App\Models\DriverRunsheet;
use App\Models\DriverRunsheetSubmission;
use App\Models\Invoice;
use App\Models\InvoiceShipment;
use App\Models\ShipmentFinance;
use App\Models\ShipperCommission;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\FinancialReportsExport;
use App\Http\Controllers\Controller;
use App\Models\Shipper;
use App\Models\Transaction;
use App\Models\WarehouseTransaction;
use App\Services\FeeAllocator;
use App\Domain\Pickup\ShipmentPickupFactory;

/**
 * @OA\Tag(
 *     name="OMS",
 *     description="Shipment Management System"
 * )
 */
class CODCollectionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/cod_collection/drivers/{status}",
     *     summary="Get drivers by status",
     *     description="Retrieves a list of drivers based on their runsheet status.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the drivers (e.g., pending, settled)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Drivers retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Drivers not found."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index_drivers($status)
    {
        $ownerDriverIds = User::byOwner()->pluck('id');
        $drivers = DriverRunsheet::where('status', $status)
            ->with('driver')
            ->whereIn('driver_id', $ownerDriverIds)
            ->get()
            ->pluck('driver')
            ->unique('id')
            ->values();
        return sendResponse("Drivers retrieved successfully.", $drivers, []);
    }

    /**
     * @OA\Get(
     *     path="/cod_collection/{status}/{driver_id?}",
     *     summary="Get COD collection runshet by status and driver ID",
     *     description="Retrieves a list of COD collection runshet based on status and optionally driver ID.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         description="Status of the runsheet (e.g., pending, settled)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="driver_id",
     *         in="path",
     *         description="ID of the driver",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Parameter(
     *         name="company_id",
     *         in="query",
     *         description="ID of the company",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Parameter(
     *         name="manifest_date_from",
     *         in="query",
     *         description="Manifest date from",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="manifest_date_to",
     *         in="query",
     *         description="Manifest date to",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="receive_date_from",
     *         in="query",
     *         description="Receive date from",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="receive_date_to",
     *         in="query",
     *         description="Receive date to",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="manifest_id",
     *         in="query",
     *         description="Manifest ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="create_date_start",
     *         in="query",
     *         description="Create date start",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="create_date_end",
     *         in="query",
     *         description="Create date end",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="confirm_date_start",
     *         in="query",
     *         description="Confirm date start",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *      @OA\Parameter(
     *         name="confirm_date_end",
     *         in="query",
     *         description="Confirm date end",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Runsheets retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Runsheets not found."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request, $status, $driver_id = null)
    {
        $status = $status ?? "pending";
        $ownerDriverIds = User::byOwner()->pluck('id');

        $query = DriverRunsheet::where('status', $status)
            ->whereIn('driver_id', $ownerDriverIds);


        $selectedDriverId = $request->input('driver_id', $driver_id);
        if (!is_null($selectedDriverId) && $selectedDriverId !== '') {
            $query->where('driver_id', (int) $selectedDriverId);
        }

        if ($driver_id) {
            $query->where('driver_id', $driver_id);
        }
        $company_id = $request->input('company_id');
        if (!empty($company_id)) {
            $query->whereHas('driver.driver', function ($q) use ($company_id) {
                $q->where('company_id', $company_id);
            });
        }

        // $company_id = request()->input('company_id');
        // if ($company_id) {
        //     $query->whereHas('driver.driver', function ($q) use ($company_id) {
        //         $q->where('company_id', $company_id);
        //     });
        // }

        $dateFilters = [
            'created_at'   => ['manifest_date_from', 'manifest_date_to', 'create_date_start', 'create_date_end'],
            'confirmed_at' => ['receive_date_from', 'receive_date_to', 'confirm_date_start', 'confirm_date_end'],
        ];
        // Use centralized calculation service
        $calculationService = app(\App\Services\CalculationLogicService::class);

        foreach ($dateFilters as $dbField => $requestInputs) {
            $fromDate = null;
            $toDate = null;

            foreach ($requestInputs as $input) {
                if ($request->has($input)) {
                    $dateValue = urldecode($request->input($input));

                    try {
                        $date = Carbon::parse($dateValue); // parse date string safely

                        // Decide if this is "from" or "to"
                        if (str_ends_with($input, '_from') || str_ends_with($input, '_start')) {
                            $fromDate = $date->startOfDay();
                        } else {
                            $toDate = $date->endOfDay();
                        }
                    } catch (\Exception $e) {
                        // Ignore invalid date formats
                    }
                }
            }

            // Apply filters
            if ($fromDate && $toDate) {
                $query->whereBetween($dbField, [$fromDate, $toDate]);
            } elseif ($fromDate) {
                $query->where($dbField, '>=', $fromDate);
            } elseif ($toDate) {
                $query->where($dbField, '<=', $toDate);
            }
        }

        $manifest_id = request()->input('manifest_id');
        if ($manifest_id) {
            $query->where('id', $manifest_id);
        }

        $allRunsheets = $query->clone()
            ->with('delivered_shipments.shipment')
            ->get();

        // Helper function to calculate amount using CalculationLogicService
        // Use getTotalCOD for all calculations to match signed money totals
        $calculateAmount = function ($ro) use ($calculationService) {
            if (!$ro->shipment) {
                return 0;
            }

            // Use getTotalCOD which handles all payment_type and fee_payer combinations
            return $calculationService->getTotalCOD($ro->shipment);
        };

        // Calculate total_money: sum of all delivered shipments using getTotalCOD
        // getTotalCOD will return 0 for PAID shipments with fee_payer = 'merchant', so they are automatically excluded
        $totalCOD = $allRunsheets->reduce(function ($sum, $runsheet) use ($calculateAmount) {
            return $sum + $runsheet->delivered_shipments
                ->filter(fn($ro) => $ro->shipment)
                ->sum(fn($ro) => $calculateAmount($ro));
        }, 0);

        // Calculate signed_money.cash: sum for shipments with payment_method = 'cod'
        $signedCash = $allRunsheets->reduce(function ($sum, $runsheet) use ($calculateAmount) {
            return $sum + $runsheet->delivered_shipments
                ->filter(fn($ro) => strtolower((string) $ro->payment_method) === 'cod')
                ->sum(fn($ro) => $calculateAmount($ro));
        }, 0);

        // Calculate signed_money.pos: sum for shipments with payment_method = 'paid'
        $signedPos = $allRunsheets->reduce(function ($sum, $runsheet) use ($calculateAmount) {
            return $sum + $runsheet->delivered_shipments
                ->filter(fn($ro) => strtolower((string) $ro->payment_method) === 'paid')
                ->sum(fn($ro) => $calculateAmount($ro));
        }, 0);

        $performance_summary = [
            'driver_count' => $query->clone()->distinct('driver_id')->count(),
            'total_money' => $totalCOD,
            'signed_money' => [
                'cash' => $signedCash,
                'pos' => $signedPos,
                'total' => $signedCash + $signedPos
            ]
        ];

        $perPage = $request->input('per_page', 8);
        $page = $request->input('page', 1);

        $runsheets = $query->with([
            'driver.driver.company',
            'assigned_shipments.shipment.consignee',
            'delivered_shipments.shipment.consignee',
            'not_delivered_shipments.shipment.consignee',
            'returned_shipments.shipment.consignee',
            'holding_shipments.shipment.consignee',
            'difference_shipments.shipment.consignee',
            'submission'
        ])->withCount([
                    'assigned_shipments',
                    'delivered_shipments',
                    'not_delivered_shipments',
                    'returned_shipments',
                    'holding_shipments',
                    'difference_shipments',
                ])->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // Per-runsheet monetary summaries - calculate total from delivered shipments
        // Use CalculationLogicService for all calculations
        // Note: For signed money calculations, we use getTotalCOD (what should be collected)
        // For total_money (COD only), we use getDriverCollectibleAmount to match driver accounts page
        $runsheets->getCollection()->each(function ($rs) use ($calculationService) {
            // Calculate total using getTotalCOD which handles all payment_type and fee_payer combinations
            $signedTotal = $rs->delivered_shipments->sum(function ($ro) use ($calculationService) {
                if (!$ro->shipment) {
                    return 0;
                }
                return $calculationService->getTotalCOD($ro->shipment);
            });

            // Calculate cash and POS separately based on payment_method
            // Both use getTotalCOD since it already handles the correct calculation logic
            $signedCash = $rs->delivered_shipments
                ->filter(fn($ro) => strtolower((string) $ro->payment_method) === 'cod')
                ->sum(function ($ro) use ($calculationService) {
                    if (!$ro->shipment) {
                        return 0;
                    }
                    return $calculationService->getTotalCOD($ro->shipment);
                });

            $signedPos = $rs->delivered_shipments
                ->filter(fn($ro) => strtolower((string) $ro->payment_method) === 'paid')
                ->sum(function ($ro) use ($calculationService) {
                    if (!$ro->shipment) {
                        return 0;
                    }
                    return $calculationService->getTotalCOD($ro->shipment);
                });

            $submission = $rs->submission;
            $colCash = (float) ($submission->paid_by_cash ?? 0);
            $colPos = (float) ($submission->paid_by_bank ?? 0);
            $colTotal = $colCash + $colPos;

            $difference = $signedTotal - $colTotal;

            $rs->setAttribute('signed_money_cash', $signedCash);
            $rs->setAttribute('signed_money_pos', $signedPos);
            $rs->setAttribute('signed_money_total', $signedTotal);
            $rs->setAttribute('collection_money_cash', $colCash);
            $rs->setAttribute('collection_money_pos', $colPos);
            $rs->setAttribute('collection_money_total', $colTotal);
            $rs->setAttribute('collection_difference', $difference);
        });

        return sendResponse(
            "Runsheets retrieved successfully.",
            [
                'data' => new GeneralResource($runsheets),
                'success' => [
                    'count' => $runsheets->total(),
                    'performance_summary' => $performance_summary
                ]
            ],
            []
        );
    }

    /**
     * @OA\Post(
     *     path="/cod_collection/store",
     *     summary="Store COD collection",
     *     description="Stores COD collection data.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="driver_runsheet_id", type="integer", description="ID of the driver runsheet"),
     *             @OA\Property(property="paid_by_cash", type="number", format="float", description="Amount paid by cash"),
     *             @OA\Property(property="paid_by_bank", type="number", format="float", description="Amount paid by bank")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="COD Collected successfully."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *          name="driver_runsheet_id",
     *          in="query",
     *          description="ID of the driver runsheet",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="paid_by_cash",
     *          in="query",
     *          description="Amount paid by cash",
     *          required=true,
     *          @OA\Schema(type="number", format="float")
     *      ),
     *       @OA\Parameter(
     *          name="paid_by_bank",
     *          in="query",
     *          description="Amount paid by bank",
     *          required=true,
     *          @OA\Schema(type="number", format="float")
     *      ),
     *     @OA\Parameter(
     *         name="driver_runsheet_id",
     *         in="query",
     *         description="ID of the driver runsheet (required, exists:driver_runsheets,id)",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="paid_by_cash",
     *         in="query",
     *         description="Amount paid by cash (required_without:paid_by_bank, nullable, numeric)",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="paid_by_bank",
     *         in="query",
     *         description="Amount paid by bank (required_without:paid_by_cash, nullable, numeric)",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     )
     * )
     */
    public function store(StoreCODCollectionRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->all();
            $data['received_by'] = Auth::id();
            $data['paid_amount'] = ($data['paid_by_cash'] ?? 0) + ($data['paid_by_bank'] ?? 0);

            // Save receipt if any
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receiptPath = uploadFile($request->file('receipt'), 'public/cod_receipts');

            }

            $submission = DriverRunsheetSubmission::create($data);

            $runsheet = DriverRunsheet::with([
                'delivered_shipments.shipment.consignee.currentAddress',
                'delivered_shipments.shipment.deliveryAddress', // <-- مهم
                'delivered_shipments.shipment.shipment_delivery',
            ])->findOrFail($data['driver_runsheet_id']);

            $driver_invoice = Invoice::firstOrCreate(
                [
                    'invoiceable_id' => $runsheet->driver_id,
                    'invoiceable_type' => User::class,
                    'status' => 'pending',
                ],
                [
                    'driver_runsheet_id' => $runsheet->id,
                ]
            );

            $driverAccount = Account::where('accountable_id', $runsheet->driver_id)
                ->where('accountable_type', User::class)
                ->lockForUpdate()
                ->firstOrFail();

            $facilityId = Auth::user()->owner_id;
            $facilityType = Auth::user()->owner_type;

            $facilityAccount = Account::where('accountable_id', $facilityId)
                ->where('accountable_type', $facilityType)
                ->lockForUpdate()
                ->firstOrFail();

            /**
             * (1) Deposit (paid_amount)
             */
            if ($data['paid_amount'] > 0) {
                Transaction::create([
                    'from_id' => $runsheet->driver_id,
                    'from_type' => User::class,
                    'to_id' => $facilityId,
                    'to_type' => $facilityType,
                    'shipment_id' => null,
                    'amount' => (float) $data['paid_amount'],
                    'type' => 'deposit',
                    'reference' => 'DEP-RS-' . $runsheet->id,
                    'description' => "COD deposit for runsheet #{$runsheet->id}",
                    'receipt_path' => $receiptPath,
                    'receipt_uploaded_by' => $receiptPath ? Auth::id() : null,
                    'receipt_uploaded_at' => $receiptPath ? now() : null,
                    'created_by' => Auth::id(),
                    'warehouse_id' => $facilityId,
                ]);

                WarehouseTransaction::create([
                    'warehouse_type' => $facilityType,
                    'warehouse_id' => $facilityId,
                    'type' => 'cash_in',
                    'source' => 'cod_collection',
                    'amount' => (float) $data['paid_amount'],
                    'reference' => 'COD-RS-' . $runsheet->id,
                    'description' => "COD deposit for runsheet #{$runsheet->id}",
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                ]);

                // Update cash balances
                $driverAccount->cash_balance = (float) ($driverAccount->cash_balance ?? 0) - (float) $data['paid_amount'];
                $facilityAccount->cash_balance = (float) ($facilityAccount->cash_balance ?? 0) + (float) $data['paid_amount'];
            }

            /**
             * (2) Driver bonus transactions - per shipment per state
             * Create a separate record for each shipment based on its state_id
             */
            $deriveStateId = function ($shipment) {
                return optional($shipment->deliveryAddress)->state_id
                    ?? optional(optional($shipment->consignee)->currentAddress)->state_id
                    ?? optional($shipment->consignee)->state_id
                    ?? ($shipment->state_id ?? null);
            };

            // Get all unique state IDs from delivered shipments
            $stateIds = [];
            foreach ($runsheet->delivered_shipments as $ro) {
                $shipment = $ro->shipment;
                if (!$shipment)
                    continue;

                $stateId = $deriveStateId($shipment);
                if ($stateId) {
                    $stateIds[] = $stateId;
                }
            }
            $stateIds = array_unique($stateIds);

            if (empty($stateIds)) {
                \Illuminate\Support\Facades\Log::warning("COD Collection: No states found for bonus calculation", [
                    'runsheet_id' => $runsheet->id,
                    'driver_id' => $runsheet->driver_id,
                    'delivered_shipments_count' => $runsheet->delivered_shipments->count(),
                ]);
            } else {
                // Load all driver bonuses for the states in this runsheet
                $bonuses = DriverBonus::where('driver_id', $runsheet->driver_id)
                    ->whereIn('state_id', $stateIds)
                    ->get()
                    ->keyBy('state_id');

                \Illuminate\Support\Facades\Log::info("COD Collection: Starting per-shipment bonus calculation", [
                    'runsheet_id' => $runsheet->id,
                    'driver_id' => $runsheet->driver_id,
                    'state_ids' => $stateIds,
                    'bonuses_count' => $bonuses->count(),
                ]);

                // Process each delivered shipment individually
                foreach ($runsheet->delivered_shipments as $ro) {
                    $shipment = $ro->shipment;
                    if (!$shipment)
                        continue;

                    // Ensure we have shipment_id - get it from the shipment object or look it up by tracking_no
                    $shipmentId = $shipment->id ?? null;
                    if (!$shipmentId && $ro->shipment_tracking_no) {
                        $shipmentId = \App\Models\Shipment::where('tracking_no', $ro->shipment_tracking_no)->value('id');
                    }

                    if (!$shipmentId) {
                        \Illuminate\Support\Facades\Log::warning("COD Collection: Could not determine shipment_id", [
                            'runsheet_id' => $runsheet->id,
                            'shipment_tracking_no' => $ro->shipment_tracking_no ?? $shipment->tracking_no ?? null,
                        ]);
                        continue;
                    }

                    $stateId = $deriveStateId($shipment);
                    if (!$stateId) {
                        \Illuminate\Support\Facades\Log::warning("COD Collection: No state_id found for shipment", [
                            'runsheet_id' => $runsheet->id,
                            'shipment_id' => $shipmentId,
                            'shipment_tracking_no' => $shipment->tracking_no,
                        ]);
                        continue;
                    }

                    $bonusRecord = $bonuses[$stateId] ?? null;
                    $rate = 0;
                    if ($bonusRecord) {
                        $rate = $shipment->is_return 
                            ? (float) $bonusRecord->return_bonus 
                            : (float) $bonusRecord->delivery_bonus;
                    }

                    if ($rate <= 0) {
                        \Illuminate\Support\Facades\Log::info("COD Collection: Skipping bonus - rate is zero or bonus not found", [
                            'runsheet_id' => $runsheet->id,
                            'driver_id' => $runsheet->driver_id,
                            'shipment_id' => $shipmentId,
                            'shipment_tracking_no' => $shipment->tracking_no,
                            'state_id' => $stateId,
                            'rate' => $rate,
                        ]);
                        continue;
                    }

                    // Check if bonus transaction already exists for this shipment
                    $ref = "BON-RS-{$runsheet->id}-SHP-{$shipmentId}-S{$stateId}";
                    // $exists = DriverBonusesTransaction::where('reference', $ref)->exists();

                    // if ($exists) {
                    //     \Illuminate\Support\Facades\Log::info("COD Collection: Bonus transaction already exists, skipping", [
                    //         'runsheet_id' => $runsheet->id,
                    //         'shipment_id' => $shipmentId,
                    //         'reference' => $ref,
                    //     ]);
                    //     continue;
                    // }

                    // try {
                    // Get tracking_no and pre_id from shipment
                    $trackingNo = $shipment->tracking_no ?? null;
                    $preId = $shipment->pre_id ?? null;
                    $shipmentIdentifier = $trackingNo ?? $preId ?? "ID-{$shipmentId}";

                    // Create driver bonus transaction record per shipment
                    $bonusTransaction = DriverBonusesTransaction::create([
                        'driver_id' => $runsheet->driver_id,
                        'shipment_id' => $shipmentId,
                        'shipment_tracking_no' => $trackingNo,
                        'pre_id' => $preId,
                        'state_id' => $stateId,
                        'driver_runsheet_id' => $runsheet->id,
                        'bonus_amount' => $rate, // Per shipment, so amount equals rate
                        'bonus_rate' => $rate,
                        'reference' => $ref,
                        'description' => "Delivery bonus for shipment #{$shipmentIdentifier} in state #{$stateId} [RS {$runsheet->id}]",
                        'active' => true,
                        'action' => 'delivery',
                        'status' => 'delivered',
                        'created_by' => Auth::id(),
                    ]);
                    // allow pickup bonus to driver
                    DriverBonusesTransaction::where('shipment_id', $shipment->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'delivered',
                        'description' => 'Pickup bonus has been sent to you.'
                    ]);

                    // Activate Pickup bonus for the shipment
                    ShipmentPickupFactory::activatePickupBonus($shipmentId);

                    \Illuminate\Support\Facades\Log::info("COD Collection: Driver bonus transaction created successfully", [
                        'bonus_transaction_id' => $bonusTransaction->id,
                        'runsheet_id' => $runsheet->id,
                        'driver_id' => $runsheet->driver_id,
                        'shipment_id' => $shipmentId,
                        'shipment_tracking_no' => $trackingNo,
                        'pre_id' => $preId,
                        'state_id' => $stateId,
                        'bonus_amount' => $rate,
                        'bonus_rate' => $rate,
                        'reference' => $ref,
                    ]);
                    // } catch (\Exception $e) {
                    //     \Illuminate\Support\Facades\Log::error("COD Collection: Failed to create driver bonus transaction", [
                    //         'runsheet_id' => $runsheet->id,
                    //         'driver_id' => $runsheet->driver_id,
                    //         'shipment_id' => $shipmentId,
                    //         'shipment_tracking_no' => $trackingNo ?? null,
                    //         'pre_id' => $preId ?? null,
                    //         'state_id' => $stateId,
                    //         'rate' => $rate,
                    //         'reference' => $ref,
                    //         'error' => $e->getMessage(),
                    //         'trace' => $e->getTraceAsString(),
                    //     ]);
                    //     // Continue with next shipment even if one fails
                    // }
                }
            }

            /**
             * (3) Delivery Fee allocation per shipment — UPDATED
             * - Preserve first_warehouse_id if already set (from inbound).
             * - Merge other warehouses (existing + derived), exclude FIRST.
             * - Use DriverBonus.delivery_bonus for delivery driver amount (by driver+state).
             */
            $allocator = app(\App\Services\FeeAllocator::class);
            $runsheet->loadMissing(['delivered_shipments.shipment']);

            foreach ($runsheet->delivered_shipments as $ro) {
                $shipment = $ro->shipment;
                if (!$shipment)
                    continue;

                // Ensure delivery_fee present on Shipment object
                if (!isset($shipment->delivery_fee)) {
                    $shipment->delivery_fee = (float) optional($shipment->shipment_delivery)->delivery_fee ?? 0.0;
                }

                // Prefer existing allocation (so FIRST stays as set during inbound)
                $existingAlloc = \App\Models\ShipmentFeeAllocation::with('others')
                    ->where('shipment_tracking_no', $shipment->tracking_no)
                    ->first();

                // FIRST: existing first_warehouse_id (Muscat) OR fall back to cashier facility
                $firstWarehouseId = $existingAlloc?->first_warehouse_id ?: (int) $facilityId;

                // OTHERS: prefer existing children/meta; else derive from shipment fields
                $existingOthers = $existingAlloc
                    ? array_map('intval', array_merge(
                        $existingAlloc->others->pluck('warehouse_id')->all(),
                        (array) data_get($existingAlloc, 'meta.others_ids', [])
                    ))
                    : [];

                if (empty($existingOthers)) {
                    $candidateOthers = collect([
                        $shipment->other_warehouse_id ?? null,
                        $shipment->to_warehouse_id ?? null,
                        $shipment->from_hub_id ?? null,
                        $shipment->current_hub_id ?? null,
                        $shipment->final_hub_id ?? null,
                        // add any other source fields you rely on
                    ])->filter()->unique()->values();

                    $existingOthers = $candidateOthers
                        ->reject(fn($wid) => (int) $wid === (int) $firstWarehouseId)
                        ->map(fn($wid) => (int) $wid)
                        ->values()
                        ->all();
                }

                // Recipient IDs
                $ids = [
                    'first_warehouse_id' => $firstWarehouseId,
                    'pickup_driver_id' => $shipment->pickup_driver_id ?? null,  // null => merchant brought it
                    'delivery_driver_id' => $runsheet->driver_id ?? null,
                ];

                // State for DriverBonus (delivery)
                $stateId = optional($shipment->consignee)->state_id ?? ($shipment->state_id ?? null);

                // Allocate:
                // - surplus -> company
                // - shortage -> proportional downscale (company=0)
                // - PRESERVE existing FIRST (very important)
                $allocator->allocateForShipment(
                    $shipment,
                    $ids,
                    $existingOthers,
                    false,  // upscaleWhenSurplus
                    $stateId,
                    true,   // useDriverBonusForDelivery
                    true    // preserveExistingFirst
                );
            }

            // Persist balances
            $driverAccount->save();
            $facilityAccount->save();

            // Settle runsheet
            $runsheet->update(['status' => 'settled', 'confirmed_at' => now()]);

            DB::commit();
            return sendResponse("COD Collected successfully.", [], []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }





    /**
     * @OA\Post(
     *     path="/cod_collection/hold",
     *     summary="Hold COD collection",
     *     description="Holds a COD collection runsheet.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="paid_by_cash", type="number", format="float", description="Amount paid by cash (nullable, numeric, min:0, required_without:paid_by_bank)"),
     *             @OA\Property(property="paid_by_bank", type="number", format="float", description="Amount paid by bank (nullable, numeric, min:0, required_without:paid_by_cash)"),
     *             @OA\Property(property="total_amount", type="number", format="float", description="Total amount (required)"),
     *             @OA\Property(property="notes", type="string", description="Notes (required)"),
     *             @OA\Property(property="driver_runsheet_id", type="integer", description="ID of the driver runsheet (required)"),
     *             @OA\Property(property="driver_id", type="integer", description="ID of the driver (required)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Runsheet held successfully."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function hold(Request $request)
    {
        $request->validate([
            'paid_by_cash' => 'nullable|numeric|min:0|required_without:paid_by_bank',
            'paid_by_bank' => 'nullable|numeric|min:0|required_without:paid_by_cash',
            "total_amount" => "required",
            "notes" => "required",
            "driver_runsheet_id" => "required",
            "driver_id" => "required",
        ]);
        DB::beginTransaction();
        try {
            $data = $request->all();
            $data['received_by'] = Auth::id();
            $data['paid_amount'] = $data['paid_by_cash'] + $data['paid_by_bank'];
            $runsheet = DriverRunsheet::where('driver_id', $data['driver_id'])
                ->where('status', 'holding')->first();
            if ($runsheet) {
                return sendResponse("Error occurred.", [], false, ["Only one holding runsheet is allowed"], 500);
            }
            if ($request->paid_by_cash + $request->paid_by_bank > $request->total_amount) {
                return sendResponse("Error occurred.", [], false, ["Amount is greater then total amount"], 500);
            }
            DriverRunsheetSubmission::create($data);
            $runsheet = DriverRunsheet::findOrFail($data['driver_runsheet_id']);
            $runsheet->update(['status' => 'holding', 'holded_at' => now()]);
            DB::commit();
            return sendResponse("Runsheet held successfully.", [], []);
        } catch (Exception $e) {
            DB::rollback();
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/cod_collection/store_holding",
     *     summary="Store holding COD collection",
     *     description="Stores holding COD collection data.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="driver_runsheet_id", type="integer", description="ID of the driver runsheet (required, exists:driver_runsheets,id)"),
     *             @OA\Property(property="driver_id", type="integer", description="ID of the driver (required, exists:users,id)"),
     *             @OA\Property(property="remaining_amount", type="number", format="float", description="Remaining amount (required, numeric, min:0)"),
     *             @OA\Property(property="total_amount", type="number", format="float", description="Total amount (required, numeric, min:0)"),
     *             @OA\Property(property="notes", type="string", description="Notes (sometimes)"),
     *             @OA\Property(property="paid_by_cash", type="number", format="float", description="Amount paid by cash (nullable, numeric, min:0, requiredIf:remaining_amount > 0, required_without:paid_by_bank)"),
     *             @OA\Property(property="paid_by_bank", type="number", format="float", description="Amount paid by bank (nullable, numeric, min:0, requiredIf:remaining_amount > 0, required_without:paid_by_cash)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="COD Collected successfully."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store_holding(StoreHoldingRunsheetRequest $request)
    {
        $request->validated();
        if ($request->paid_by_cash || $request->paid_by_bank) {
            if ($request->paid_by_cash + $request->paid_by_bank != $request->remaining_amount) {
                return sendResponse("Error occurred.", [], false, ["Amount is not equal to remaining amount"], 500);
            }
        }
        DB::beginTransaction();
        try {
            $data = $request->all();
            $data['received_by'] = Auth::id();
            $data['paid_amount'] = $data['paid_by_cash'] + $data['paid_by_bank'];
            DriverRunsheetSubmission::create($data);
            $runsheet = DriverRunsheet::find($data['driver_runsheet_id']);
            if ($runsheet->difference_shipments->count() > 0) {
                return sendResponse("Error occurred.", [], false, ["still there are some difference shipments which needs to be cleared"], 500);
            }
            $paid = $runsheet->submissions->sum("paid_amount");
            if ($paid != $runsheet->submission->total_amount) {
                return sendResponse("Error occurred.", [], false, ["there is descrapency in the paid and total amount"], 500);
            }
            if ($paid != $runsheet->submission->total_amount) {
                return sendResponse("Error occurred.", [], false, ["there is descrapency in the paid and total amount"], 500);
            }
            $runsheet_shipments = $runsheet->delivered_shipments;
            $driver_invoice = Invoice::where('invoiceable_id', $runsheet->driver_id)
                ->where('invoiceable_type', User::class)
                ->where('status', 'pending')
                ->first();
            if (!$driver_invoice) {
                $driver_invoice = Invoice::create([
                    'invoiceable_id' => $runsheet->driver_id,
                    'invoiceable_type' => User::class,
                    'driver_runsheet_id' => $runsheet->id,
                    'status' => 'pending',
                ]);
            }
            foreach ($runsheet_shipments as $runsheet_shipment) {
                $driver_commission = CompanyCommission::where('company_id', $runsheet->driver->driver->company_id)->where('state_id', $runsheet_shipment->shipment->consignee->state_id)->first();
                $driver_bonus = DriverBonus::where('driver_id', $runsheet->driver_id)->where('state_id', $runsheet_shipment->shipment->consignee->state_id)->first();
                $shipper_commission = ShipperCommission::where('shipper_id', $runsheet_shipment->shipment->shipper_id)->where('state_id', $runsheet_shipment->shipment->consignee->state_id)->first();
                if (!$driver_commission) {
                    return sendResponse("Error occurred.", [], false, ["Company commission is not available for the state: " . $runsheet_shipment->shipment->consignee->state->en_name], 500);
                }
                if (!$shipper_commission) {
                    return sendResponse("Error occurred.", [], false, ["Shipper commission is not available for the state: " . $runsheet_shipment->shipment->consignee->state->en_name], 500);
                }
                $shipper_invoice = Invoice::where('invoiceable_id', $runsheet_shipment->shipment->shipper_id)
                    ->where('invoiceable_type', User::class)
                    ->where('status', 'pending')
                    ->first();
                if (!$shipper_invoice) {
                    $shipper_invoice = Invoice::create([
                        'invoiceable_id' => $runsheet_shipment->shipment->shipper_id,
                        'invoiceable_type' => User::class,
                        'driver_runsheet_id' => $runsheet->id,
                        'status' => 'pending',
                        'amount' => 0,
                    ]);
                }
                InvoiceShipment::create([
                    "invoice_id" => $shipper_invoice->id,
                    "shipment_tracking_no" => $runsheet_shipment->shipment_tracking_no
                ]);
                InvoiceShipment::create([
                    "invoice_id" => $driver_invoice->id,
                    "shipment_tracking_no" => $runsheet_shipment->shipment_tracking_no
                ]);
                $finance = ShipmentFinance::where('shipment_tracking_no', $runsheet_shipment->shipment_tracking_no)->first();
                if ($driver_bonus) {
                    $finance->update(["driver_bonus" => $driver_bonus->delivery_bonus]);
                }
                $finance->update(["merchant_balance" => $shipper_commission->delivery_fee]);
                $finance->update(["driver_delivery_fee" => $driver_commission->delivery_fee]);
                $runsheet_shipment->update(['status' => 'delivered']);
                $finance->save();
                $runsheet_shipment->shipment->save();
                $runsheet_shipment->save();

                // Use CalculationLogicService to get total_cod
                $calculationService = app(\App\Services\CalculationLogicService::class);
                $transactionAmount = $calculationService->getTotalCOD($runsheet_shipment->shipment);

                $driverAccount = Account::where('accountable_id', $runsheet->driver_id)
                    ->where('accountable_type', User::class)
                    ->first();
                $facilityAccount = Account::where('accountable_id', Auth::user()->owner_id)
                    ->where('accountable_type', Auth::user()->owner_type)
                    ->first();
                $driverAccount->cash_balance -= $transactionAmount;
                $facilityAccount->cash_balance += $transactionAmount;
                $driverAccount->save();
                $facilityAccount->save();
            }
            DriverRunsheet::find($data['driver_runsheet_id'])->update(['status' => 'settled', 'confirmed_at' => now()]);
            DB::commit();
            return sendResponse("COD Collected successfully.", [], []);
        } catch (Exception $e) {
            DB::rollback();
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/cod_collection/reports",
     *     summary="Get financial reports",
     *     description="Retrieves financial reports.",
     *     tags={"OMS"},
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for the report (default: start of the current month)",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="End date for the report (default: current date)",
     *         required=false,
     *         @OA\Schema(type="date")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type of report (daily, weekly, monthly, default: monthly)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Financial reports retrieved successfully."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function reports(Request $request)
    {
        $fromDate = $request->input('from_date', now()->startOfMonth());
        $toDate = $request->input('to_date', now());
        $type = $request->input('type', 'monthly');
        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate = Carbon::parse($toDate)->endOfDay();
        $dateFormat = match ($type) {
            'daily' => 'Y-m-d',
            'weekly' => 'Y-W',
            'monthly' => 'Y-m',
            default => 'Y-m'
        };
        $financialReports = ShipmentFinance::with('shipment')
            ->whereHas('shipment', function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('created_at', [$fromDate, $toDate]);
            })
            ->select(
                DB::raw("DATE_FORMAT(shipments.created_at, '$dateFormat') as period"),
                DB::raw('SUM(driver_delivery_fee + merchant_balance) as total_revenue'),
                DB::raw('SUM(cod_amount) as cod_collected'),
                DB::raw('SUM(expenses) as total_expenses')
            )
            ->join('shipments', 'shipment_finances.shipment_tracking_no', '=', 'shipments.tracking_no')
            ->groupBy('period')
            ->orderBy('period')
            ->get();
        $reports = $financialReports->map(function ($report) {
            return [
                'date' => $report->period,
                'total_revenue' => floatval($report->total_revenue ?? 0),
                'cod_collected' => floatval($report->cod_collected ?? 0),
                'expenses' => floatval($report->total_expenses ?? 0),
                'net_profit' => floatval(($report->total_revenue ?? 0) - ($report->total_expenses ?? 0))
            ];
        });
        $summary = [
            'total_revenue' => floatval($financialReports->sum('total_revenue')),
            'total_cod_collected' => floatval($financialReports->sum('cod_collected')),
            'total_expenses' => floatval($financialReports->sum('total_expenses')),
            'net_profit' => floatval($financialReports->sum('total_revenue') - $financialReports->sum('total_expenses'))
        ];
        $chartData = [
            [
                'name' => 'Revenue',
                'value' => $summary['total_revenue']
            ],
            [
                'name' => 'Expenses',
                'value' => $summary['total_expenses']
            ]
        ];
        return sendResponse('Financial reports retrieved successfully.', [
            'summary' => $summary,
            'reports' => $reports,
            'chart_data' => $chartData
        ]);
    }
    /**
     * @OA\Post(
     *     path="/cod_collection/reports/export",
     *     summary="Export financial reports",
     *     description="Export financial reports in CSV or PDF format.",
     *     tags={"OMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="from_date", type="string", format="date", description="Start date for the report"),
     *             @OA\Property(property="to_date", type="string", format="date", description="End date for the report"),
     *             @OA\Property(property="type", type="string", enum={"daily", "weekly", "monthly"}, description="Type of report"),
     *             @OA\Property(property="format", type="string", enum={"csv", "pdf"}, description="Export format", required={"format"}),
     *             @OA\Property(property="columns", type="array", @OA\Items(type="string"), description="Array of columns to include in export")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File downloaded successfully",
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="File attachment header",
     *             @OA\Schema(
     *                 type="string",
     *                 example="attachment; filename=financial_reports.csv"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function export(Request $request)
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'type' => 'nullable|in:daily,weekly,monthly',
            'format' => 'required|in:csv,pdf',
            'columns' => 'nullable|array'
        ]);
        $fromDate = $request->input('from_date', now()->startOfMonth());
        $toDate = $request->input('to_date', now());
        $type = $request->input('type', 'monthly');
        $format = $request->input('format', 'csv');
        $fromDate = Carbon::parse($fromDate)->startOfDay();
        $toDate = Carbon::parse($toDate)->endOfDay();
        $dateFormat = match ($type) {
            'daily' => 'Y-m-d',
            'weekly' => 'Y-W',
            'monthly' => 'Y-m',
            default => 'Y-m'
        };
        $financialReports = ShipmentFinance::with('shipment')
            ->whereHas('shipment', function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('created_at', [$fromDate, $toDate]);
            })
            ->select(
                DB::raw("DATE_FORMAT(shipments.created_at, '$dateFormat') as period"),
                DB::raw('SUM(driver_delivery_fee + merchant_balance) as total_revenue'),
                DB::raw('SUM(cod_amount) as cod_collected'),
                DB::raw('SUM(expenses) as total_expenses')
            )
            ->join('shipments', 'shipment_finances.shipment_tracking_no', '=', 'shipments.tracking_no')
            ->groupBy('period')
            ->orderBy('period')
            ->get();
        $exportData = $financialReports->map(function ($report) {
            return [
                'date' => $report->period,
                'total_revenue' => $report->total_revenue ?? 0,
                'cod_collected' => $report->cod_collected ?? 0,
                'expenses' => $report->total_expenses ?? 0,
                'net_profit' => ($report->total_revenue ?? 0) - ($report->total_expenses ?? 0)
            ];
        })->toArray();
        if ($format === 'csv') {
            return Excel::download(
                new CODCollectionExport($exportData),
                'financial_reports.csv',
                \Maatwebsite\Excel\Excel::CSV
            );
        } else {
            $pdf = PDF::loadView('exports.financial_reports', [
                'reports' => $exportData,
                'title' => 'Financial Reports'
            ]);
            return $pdf->download('financial_reports.pdf');
        }
    }

    /**
     * @OA\Post(
     * path="/cod_collection/export_runsheets",
     * summary="Export COD collection runsheets",
     * description="Export COD collection runsheets in CSV or Excel format.",
     * tags={"OMS"},
     * @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="driver_id", type="integer", description="Driver ID filter"),
     *             @OA\Property(property="company_id", type="integer", description="Company ID filter"),
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date filter"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date filter"),
     *             @OA\Property(property="create_date_start", type="string", format="date", description="Create date start filter"),
     *             @OA\Property(property="create_date_end", type="string", format="date", description="Create date end filter"),
     *             @OA\Property(property="confirm_date_start", type="string", format="date", description="Confirm date start filter"),
     *             @OA\Property(property="confirm_date_end", type="string", format="date", description="Confirm date end filter"),
     *             @OA\Property(property="manifest_id", type="integer", description="Manifest ID filter"),
     *             @OA\Property(property="status", type="string", description="Status filter (pending, holding, settled)"),
     *             @OA\Property(property="format", type="string", enum={"csv", "xlsx"}, description="Export format", required={"format"}),
     *             @OA\Property(property="columns", type="array", @OA\Items(type="string"), description="Array of columns to include in export")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File downloaded successfully",
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="File attachment header",
     *             @OA\Schema(
     *                 type="string",
     *                 example="attachment; filename=cod_collection_runsheets.csv"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function exportRunsheets(Request $request)
    {
        $request->validate([
            'driver_id' => 'nullable|exists:users,id',
            'company_id' => 'nullable|exists:companies,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'create_date_start' => 'nullable|date',
            'create_date_end' => 'nullable|date',
            'confirm_date_start' => 'nullable|date',
            'confirm_date_end' => 'nullable|date',
            'manifest_id' => 'nullable|integer',
            'status' => 'nullable|in:pending,holding,settled',
            'format' => 'required|in:csv,xlsx,pdf',
            'columns' => 'nullable|array'
        ]);

        try {
            // $status = $request->input('status', 'pending');
            $query = DriverRunsheet::query();

            // Apply filters
            if ($request->driver_id) {
                $query->where('driver_id', $request->driver_id);
            }

            if ($request->company_id) {
                $query->whereHas('driver.driver', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
                });
            }

            if ($request->start_date) {
                $query->where('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
            }

            if ($request->end_date) {
                $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
            }

            if ($request->create_date_start) {
                $query->where('created_at', '>=', Carbon::parse($request->create_date_start)->startOfDay());
            }

            if ($request->create_date_end) {
                $query->where('created_at', '<=', Carbon::parse($request->create_date_end)->endOfDay());
            }

            if ($request->confirm_date_start) {
                $query->where('confirmed_at', '>=', Carbon::parse($request->confirm_date_start)->startOfDay());
            }

            if ($request->confirm_date_end) {
                $query->where('confirmed_at', '<=', Carbon::parse($request->confirm_date_end)->endOfDay());
            }

            if ($request->manifest_id) {
                $query->where('id', $request->manifest_id);
            }

            // Get runsheets with relationships and counts
            $runsheets = $query->with([
                'driver.driver.company',
                'assigned_shipments.shipment.consignee',
                'delivered_shipments.shipment.consignee',
                'not_delivered_shipments.shipment.consignee',
                'returned_shipments.shipment.consignee',
                'holding_shipments.shipment.consignee',
                'difference_shipments.shipment.consignee',
                'submission.received_by'
            ])->withCount([
                        'assigned_shipments',
                        'delivered_shipments',
                        'not_delivered_shipments',
                        'returned_shipments',
                        'holding_shipments',
                        'difference_shipments',
                    ])->orderBy('id', 'desc')->get();

            $format = $request->input('format', 'csv');
            $columns = $request->input('columns');

            $filename = 'cod_collection_runsheets_' . now()->format('Y_m_d_H_i_s');

            if ($format === 'csv') {
                return Excel::download(
                    new CODCollectionExport($runsheets, $columns),
                    $filename . '.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            }elseif($format === 'pdf'){
               $html = view('exports.general_export', [
                'name' => "runsheets",
                'rows' => $runsheets,
                'columns' => $columns,
                'date' => now()->format('Y-m-d H:i:s')
            ])->render();
            return response()->json(['html' => $html]);
            } else {
                return Excel::download(
                    new CODCollectionExport($runsheets, $columns),
                    $filename . '.xlsx'
                );
            }
        } catch (Exception $e) {
            Log::error('COD Collection Export Error: ' . $e->getMessage());
            return sendResponse("Error occurred during export.", [], false, [$e->getMessage()], 500);
        }
    }
}
