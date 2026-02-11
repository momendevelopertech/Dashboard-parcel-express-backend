<?php

namespace App\Http\Controllers\Api\v1;
use App\Models\User;


use App\Models\State;
use App\Models\Merchant;
use Illuminate\Http\Request;
use App\Models\CommissionTemplate;
use App\Models\MerchantCommission;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use App\Http\Resources\MerchantCommissionResource;
use App\Http\Requests\StoreMerchantCommissionRequest;
use App\Http\Requests\UpdateMerchantCommissionRequest;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
/**
 * Controller handling merchant commission configuration management
 *
 * Manages commission structures and geographic rules. Features:
 * - Merchant-specific rate configuration
 * - Multi-jurisdiction compliance checks
 * - Historical version control
 * - Transactional rate updates
 * - Permanent audit trail for financial compliance
 */
class MerchantCommissionController extends Controller
{
    /**
     * Get merchant's commission rules
     *
     * @OA\Get(
     *   path="/merchant_commissions/{merchant_id}",
     *   tags={"WMS"},
     *   summary="Get merchant's commission rules",
     *   description="Retrieve merchant's commission rules with optional search",
     *   operationId="getMerchantCommissions",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="merchant_id",
     *     in="path",
     *     description="Merchant ID",
     *     required=true,
     *     @OA\Schema(
     *         type="integer",
     *         format="int64"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for merchant name",
     *     required=false,
     *     @OA\Schema(
     *         type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant Commissions retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="merchant_id", type="integer", format="int64"),
     *             @OA\Property(property="country_id", type="integer", format="int64"),
     *             @OA\Property(property="state_id", type="integer", format="int64"),
     *             @OA\Property(property="commission_rate", type="number", format="float"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *               property="merchant",
     *               type="object",
     *               @OA\Property(property="name", type="string")
     *             ),
     *             @OA\Property(
     *               property="country",
     *               type="object",
     *               @OA\Property(property="name", type="string")
     *             ),
     *             @OA\Property(
     *               property="state",
     *               type="object",
     *               @OA\Property(property="en_name", type="string"),
     *               @OA\Property(property="ar_name", type="string"),
     *               @OA\Property(property="country_id", type="integer", format="int64")
     *             )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Invalid search syntax",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function index($merchant_id)
    {
        $merchant_commissions = MerchantCommission::where('merchant_id', $merchant_id);
        if (request()->has('query')) {
            $query = request()->input('query');
            $merchant_commissions = $merchant_commissions
                ->whereHas('merchant', function ($q) use ($query) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
                })
                ->with("merchant", "country:id,name", "state:id,en_name,ar_name,country_id")
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $merchant_commissions = $merchant_commissions->with("merchant", "country:id,name", "state:id,en_name,ar_name,country_id")->orderBy('id', 'desc')->paginate(8);
        }
        return sendResponse("Merchant Commissions reterived successfully.", new MerchantCommissionResource($merchant_commissions), []);
    }

    /**
     * Create new commission configuration
     *
     * @OA\Post(
     *   path="/merchant_commissions/store",
     *   tags={"WMS"},
     *   summary="Create new commission configuration",
     *   description="Create a new merchant commission rate",
     *   operationId="createMerchantCommission",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Commission creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "merchant_id",
     *         "country_id",
     *         "state_id",
     *         "delivery_fee",
     *         "return_fee"
     *       },
     *       @OA\Property(property="merchant_id", type="integer", format="int64", example=1),
     *       @OA\Property(property="country_id", type="integer", format="int64", example=1),
     *       @OA\Property(property="state_id", type="integer", format="int64", example=1),
     *       @OA\Property(property="delivery_fee", type="number", format="float", example=10.5),
     *       @OA\Property(property="return_fee", type="number", format="float", example=5.0)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Commission created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant Commission created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="merchant_id", type="integer", format="int64"),
     *         @OA\Property(property="country_id", type="integer", format="int64"),
     *         @OA\Property(property="state_id", type="integer", format="int64"),
     *         @OA\Property(property="commission_rate", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    private function computeAmountDiscount(float $base, float $discount, int $precision = 3): float
    {
        return round(max(0, $base - $discount), $precision);
    }

    /**
     * يحفظ/يحدّث عمولات عميل واحد لمجموعة ولايات دفعة واحدة
     * Expect payload:
     * {
     *   "merchant_id": 123,
     *   "commissions": [
     *     {"state_id":1,"base_delivery_fee":6,"delivery_discount_amount":4,"base_return_fee":0,"return_discount_amount":0},
     *     ...
     *   ]
     * }
     */
    public function store(StoreMerchantCommissionRequest $request)
    {
        $validated = $request->validated();

        $merchantId = $validated['merchant_id'];
        $rows = $validated['commissions'] ?? [];

        DB::beginTransaction();
        try {
            // Delete all existing commissions for this merchant
            MerchantCommission::where('merchant_id', $merchantId)->delete();

            // Insert all new commissions
            foreach ($rows as $row) {
                $stateId = (int) $row['state_id'];

                $baseDelivery = (float) ($row['base_delivery_fee'] ?? 0);
                $baseReturn = (float) ($row['base_return_fee'] ?? 0);

                $delDisc = (float) ($row['delivery_discount_amount'] ?? 0);
                $retDisc = (float) ($row['return_discount_amount'] ?? 0);

                // احسب النهائي
                $deliveryFee = $this->computeAmountDiscount($baseDelivery, $delDisc);
                $returnFee = $this->computeAmountDiscount($baseReturn, $retDisc);

                MerchantCommission::create([
                    'merchant_id' => $merchantId,
                    'country_id' => $row['country_id'] ?? null,
                    'state_id' => $stateId,
                    'base_delivery_fee' => $baseDelivery,
                    'base_return_fee' => $baseReturn,
                    'delivery_discount_amount' => $delDisc,
                    'return_discount_amount' => $retDisc,
                    'delivery_fee' => $deliveryFee,
                    'return_fee' => $returnFee,
                ]);
            }

            DB::commit();
            return sendResponse('Merchant Commissions saved successfully.', []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse('Error saving merchant commissions.', [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * Update commission configuration
     *
     * @OA\Post(
     *   path="/merchant_commissions/update",
     *   tags={"WMS"},
     *   summary="Update commission configuration",
     *   description="Update an existing merchant commission rate",
     *   operationId="updateMerchantCommission",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Commission update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id",
     *         "merchant_id",
     *         "country_id",
     *         "state_id",
     *         "delivery_fee",
     *         "return_fee"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="merchant_id", type="integer", format="int64", example=1),
     *       @OA\Property(property="country_id", type="integer", format="int64", example=1),
     *       @OA\Property(property="state_id", type="integer", format="int64", example=1),
     *       @OA\Property(property="delivery_fee", type="number", format="float", example=10.5),
     *       @OA\Property(property="return_fee", type="number", format="float", example=5.0)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Commission updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant Commission updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="merchant_id", type="integer", format="int64"),
     *         @OA\Property(property="country_id", type="integer", format="int64"),
     *         @OA\Property(property="state_id", type="integer", format="int64"),
     *         @OA\Property(property="commission_rate", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function update(UpdateMerchantCommissionRequest $request)
    {
        try {
            $merchant_commission = MerchantCommission::findOrFail($request->id);
            $merchant_commission->update($request->validated());
            return sendResponse("Merchant Commission updated successfully.", new MerchantCommissionResource($merchant_commission));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating merchant commission.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * Delete commission configuration
     *
     * @OA\Post(
     *   path="/merchant_commissions/delete",
     *   tags={"WMS"},
     *   summary="Delete commission configuration",
     *   description="Delete a merchant commission configuration",
     *   operationId="deleteMerchantCommission",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Commission deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Commission deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant Commission deleted successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         items={
     *           @OA\Property(type="string")
     *         }
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Constraint violations",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error Occured."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function delete(Request $request)
    {
        try {
            MerchantCommission::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Merchant Commission deleted successfully.", []);
    }

    /**
     * Get all commissions
     *
     * @OA\Get(
     *   path="/merchant_commissions/all",
     *   tags={"WMS"},
     *   summary="Get all commissions",
     *   description="Retrieve all merchant commissions without pagination",
     *   operationId="getAllCommissions",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Commissions retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant Commissions"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="merchant_id", type="integer", format="int64"),
     *             @OA\Property(property="country_id", type="integer", format="int64"),
     *             @OA\Property(property="state_id", type="integer", format="int64"),
     *             @OA\Property(property="commission_rate", type="number", format="float"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function all()
    {
        return sendResponse("Merchant Commissions", new MerchantCommissionResource(MerchantCommission::all()));
    }

    /**
     * Get state-specific commission rate
     *
     * @OA\Get(
     *   path="/merchant_commissions/by_state/{merchant_id}/{state_id}",
     *   tags={"WMS"},
     *   summary="Get state-specific commission rate",
     *   description="Retrieve commission rate for a specific merchant and state",
     *   operationId="getStateCommission",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="merchant_id",
     *     in="path",
     *     description="Merchant ID",
     *     required=true,
     *     @OA\Schema(
     *         type="integer",
     *         format="int64"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="state_id",
     *     in="path",
     *     description="State ID",
     *     required=true,
     *     @OA\Schema(
     *         type="integer",
     *         format="int64"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Commission rate retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant Commissions retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="merchant_id", type="integer", format="int64"),
     *         @OA\Property(property="country_id", type="integer", format="int64"),
     *         @OA\Property(property="state_id", type="integer", format="int64"),
     *         @OA\Property(property="commission_rate", type="number", format="float"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Commission not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Commission not found.")
     *     )
     *   )
     * )
     */
    public function by_state($merchant_id, $state_id)
    {
        $merchant_commissions = MerchantCommission::where('merchant_id', $merchant_id)->where('state_id', $state_id)
            ->first();

        return sendResponse("Merchant Commissions retrieved successfully.", $merchant_commissions);
    }

    public function pricing($merchant_id)
    {
        $merchant_commissions = MerchantCommission::where('merchant_id', $merchant_id)->get();
        return sendResponse("Merchant Commissions retrieved successfully.", $merchant_commissions);
    }

    /**
     * Get single shipper details
     *
     * @OA\Get(
     *   path="/merchants/getSingle",
     *   tags={"WMS"},
     *   summary="Get merchant details with states",
     *   description="Get detailed information about a specific merchant including available states",
     *   operationId="getMerchantCommission",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="merchant_id",
     *     in="query",
     *     description="Merchant ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Merchant retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="setting", type="object"),
     *         @OA\Property(property="country", type="object"),
     *         @OA\Property(property="state", type="object"),
     *         @OA\Property(property="governorate", type="object"),
     *         @OA\Property(property="place", type="object"),
     *         @OA\Property(property="owner", type="object"),
     *         @OA\Property(property="states", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Merchant not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Merchant not found."),
     *       @OA\Property(property="success", type="boolean", example=false)
     *     )
     *   )
     * )
     */
    public function getSingle(Request $request)
    {
        $request->validate([
            'merchant_id' => 'required',
        ]);

        $merchantId = $request->input('merchant_id');

        $merchant = User::find($merchantId);

        if (!$merchant) {
            return sendResponse("Merchant not found.", [], 404);
        }

        $states = State::where('country_id', $merchant->country_id)->get();
        $merchant->setRelation('states', $states);

        return sendResponse("Merchant retrieved successfully.", $merchant, []);
    }

    /**
     * @OA\Get(
     *     path="/merchant_commissions",
     *     summary="Get commissions for a merchant",
     *     description="Retrieves all commissions for a given merchant ID.",
     *     tags={"WMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="merchant_id",
     *         in="query",
     *         description="ID of the merchant",
     *         required=true,
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commissions retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Merchant not found",
     *     )
     * )
     */
    public function merchant_commissions(Request $request)
    {
        $merchant_id = $request->merchant_id;

        $commissions = MerchantCommission::where('merchant_id', $merchant_id)
            ->with(['merchant', 'state.governorate'])
            ->join('states', 'merchant_commissions.state_id', '=', 'states.id')
            ->join('governorates', 'states.governorate_id', '=', 'governorates.id')
            ->orderBy('governorates.en_name')
            ->orderBy('states.en_name')
            ->select('merchant_commissions.*')
            ->get();

        return sendResponse("Commissions retrieved successfully.", $commissions->values(), []);
    }

    // private function computeAmountDiscount(float $base, float $discount, int $precision = 3): float
    // {
    //     return round(max(0, $base - $discount), $precision);
    // }

    public function store_commissions(StoreMerchantCommissionRequest $request)
    {
        $validated = $request->validated();

        $merchantId = $validated['merchant_id'];
        $rows = $validated['commissions'] ?? [];

        DB::beginTransaction();
        try {
            // Delete all existing commissions for this merchant
            MerchantCommission::where('merchant_id', $merchantId)->delete();

            // Insert all new commissions
            foreach ($rows as $row) {
                $stateId = (int) $row['state_id'];
                $baseDelivery = (float) ($row['base_delivery_fee'] ?? 0);
                $baseReturn = (float) ($row['base_return_fee'] ?? 0);
                $delDisc = (float) ($row['delivery_discount_amount'] ?? 0);
                $retDisc = (float) ($row['return_discount_amount'] ?? 0);

                $deliveryFee = $this->computeAmountDiscount($baseDelivery, $delDisc);
                $returnFee = $this->computeAmountDiscount($baseReturn, $retDisc);

                MerchantCommission::create([
                    'merchant_id' => $merchantId,
                    'state_id' => $stateId,
                    'country_id' => $row['country_id'] ?? null,
                    'base_delivery_fee' => $baseDelivery,
                    'base_return_fee' => $baseReturn,
                    'delivery_discount_amount' => $delDisc,
                    'return_discount_amount' => $retDisc,
                    'delivery_fee' => $deliveryFee,
                    'return_fee' => $returnFee,
                ]);
            }

            DB::commit();
            return sendResponse('Merchant Commissions saved successfully.', []);
        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse('Error saving merchant commissions.', [], false, [$e->getMessage()], 422);
        }
    }
    public function getDefaults()
    {
        $rows = CommissionTemplate::query()
            // ->where('owner_id', facility('id'))
            // ->where('owner_type', facility('type'))
            ->orderBy('country_id')
            ->orderBy('state_id')
            ->get([
                'owner_id',
                'owner_type',
                'country_id',
                'state_id',
                'base_delivery_fee',
                'base_return_fee',
                'delivery_discount_amount',
                'return_discount_amount',
                'delivery_fee',
                'return_fee',
                'updated_at',
                'created_at'
            ]);

        return sendResponse('Commissions retrieved.', $rows, true, []);
    }

    public function saveDefaults(Request $request)
    {
        $validated = $request->validate([
            'commissions' => ['required', 'array', 'min:1'],
            'commissions.*.country_id' => ['nullable', 'exists:countries,id'],
            'commissions.*.state_id' => ['nullable', 'exists:states,id'],
            'commissions.*.base_delivery_fee' => ['required', 'numeric', 'min:0'],
            'commissions.*.delivery_discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:commissions.*.base_delivery_fee'],
            'commissions.*.base_return_fee' => ['required', 'numeric', 'min:0'],
            'commissions.*.return_discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:commissions.*.base_return_fee'],
            'commissions.*.delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'commissions.*.return_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ownerId = facility('id');
        $ownerType = facility('type');

        \DB::transaction(function () use ($validated, $ownerId, $ownerType) {
            foreach ($validated['commissions'] as $row) {
                $baseDelivery = (float) ($row['base_delivery_fee'] ?? 0);
                $baseReturn = (float) ($row['base_return_fee'] ?? 0);
                $delDisc = (float) ($row['delivery_discount_amount'] ?? 0);
                $retDisc = (float) ($row['return_discount_amount'] ?? 0);

                $deliveryFee = isset($row['delivery_fee']) ? (float) $row['delivery_fee'] : max(0, $baseDelivery - $delDisc);
                $returnFee = isset($row['return_fee']) ? (float) $row['return_fee'] : max(0, $baseReturn - $retDisc);

                CommissionTemplate::updateOrCreate(
                    [
                        'owner_id' => $ownerId,
                        'owner_type' => $ownerType,
                        'country_id' => $row['country_id'] ?? null,
                        'state_id' => $row['state_id'] ?? null,
                    ],
                    [
                        'base_delivery_fee' => $baseDelivery,
                        'base_return_fee' => $baseReturn,
                        'delivery_discount_amount' => $delDisc,
                        'return_discount_amount' => $retDisc,
                        'delivery_fee' => $deliveryFee,
                        'return_fee' => $returnFee,
                    ]
                );
            }
        });

        return sendResponse('Defaults saved.', []);
    }
}
