<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreMerchantWaybillRequest;
use App\Http\Requests\UpdateMerchantWaybillRequest;
use App\Http\Resources\MerchantWaybillResource;
use App\Models\Merchant;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\MerchantWaybill;
use App\Models\MerchantWaybillBatch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Log;

/**
 * @OA\Tag(name="WMS", description="Warehouse Management System")
 * @OA\Controller(description="Merchant Waybill Management")
 */
class MerchantWaybillController extends Controller
{
    /**
     * @OA\Get(
     *     path="/merchant_waybills",
     *     summary="Get a list of merchant waybills",
     *     description="Retrieve a list of merchant waybills. You can optionally filter by query parameter.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for merchant name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant Waybills retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function index()
    {
        $perPage = request()->input('per_page', 8);
        $merchant_waybills = User::byOwner()->whereHas('waybills');
        if (request()->has('query')) {
            $query = request()->input('query');
            $merchant_waybills = $merchant_waybills
                ->whereHas('merchant', function ($q) use ($query) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%']);
                })
                ->with("merchant")
                ->withCount('waybills')
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $merchant_waybills = $merchant_waybills->with("merchant")->withCount('waybills')->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("Merchant Waybills reterived successfully.", new MerchantWaybillResource($merchant_waybills), []);
    }

    /**
     * @OA\Get(
     *     path="/merchant_waybills/show",
     *     summary="Get merchant waybills by merchant ID or all merchant waybills",
     *     description="Retrieve merchant waybills by providing merchant_id as a query parameter. If no merchant_id is provided, all merchant waybills are returned. You can optionally filter by query parameter.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="merchant_id",
     *         in="query",
     *         description="Merchant ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for tracking number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant Waybills retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function show(Request $request)
    {
        $perPage = (int) $request->input('per_page', 8);
        $user = Auth::user();

        $query = MerchantWaybill::query()->with('merchant', 'batch');

        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            $merchantId = $request->route('id');
            if ($request->filled('merchant_id')) {
                $query->where('merchant_id', $merchantId);
            }
        } else {
            $query->where('merchant_id', $user->id);
        }

        if ($request->filled('query')) {
            $term = strtolower($request->input('query'));

            $items = $query
                ->whereRaw('LOWER(tracking_no) LIKE ?', ["%{$term}%"])
                ->orderBy('used', 'asc')
                ->orderByDesc('id')
                ->get();

            return sendResponse(
                "Merchant Waybills retrieved successfully.",
                new MerchantWaybillResource($items),
                []
            );
        }
        if ($request->filled('batch_id')) {
            $query->where('batch_id', (int) $request->input('batch_id'));
        }
        $items = $query
            ->orderBy('used', 'asc')
            ->orderByDesc('id')
            ->paginate($perPage);

        return sendResponse(
            "Merchant Waybills retrieved successfully.",
            new MerchantWaybillResource($items),
            []
        );
    }


    /**
     * @OA\Post(
     *     path="/merchant_waybills/store",
     *     summary="Create a new merchant waybill",
     *     description="Create a new merchant waybill.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="merchant_id", type="integer", description="Merchant ID", example=1),
     *             @OA\Property(property="quantity", type="integer", description="Quantity", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant Waybill created successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function batches(Request $request)
    {
        $perPage = (int) $request->input('per_page', 8);
        $user = Auth::user();

        $q = MerchantWaybillBatch::query()
            ->with('merchant')
            ->withCount([
                'waybills', // waybills_count = total
                'waybills as used_count' => function ($qq) {
                    $qq->where('used', true);
                },
            ]);
        // if (!($user->hasRole('Super Admin') || $user->hasRole('Admin'))) {
        //     $q->where('merchant_id', $user->id);
        // } elseif ($request->filled('merchant_id')) {
        // }
        $q->where('merchant_id', (int) $request->input('merchant_id'));

        $items = $q->orderByDesc('id')->paginate($perPage);

        // unused = total - used
        $items->getCollection()->transform(function ($item) {
            $item->unused_count = max(0, ($item->waybills_count - $item->used_count));
            return $item;
        });

        return sendResponse('Merchant Waybill Batches retrieved successfully.', $items, []);
    }


    public function showBatches(Request $request, $id)
    {
        $perPage = (int) $request->input('per_page', 8);
        $user = Auth::user();
        $merchantId = (int) $request->input('merchant_id');
        $q = MerchantWaybill::query()
            ->where('batch_id', $id)
            ->orderBy('used', 'asc') 
            ->with('batch.merchant');

        if (!($user->hasRole('Super Admin') || $user->hasRole('Admin'))) {
            $q->whereHas('batch', function ($query) use ($user) {
                $query->where('merchant_id', $user->id);
            });
        } elseif ($merchantId) {
            $q->whereHas('batch', function ($query) use ($merchantId) {
                $query->where('merchant_id', $merchantId);
            });
        }
        $items = $q->orderByDesc('id')->paginate($perPage);
        return sendResponse('Merchant Waybills retrieved successfully.', $items, []);
    }

    public function store(StoreMerchantWaybillRequest $request)
    {
        try {
            $data = $request->all();
            $merchant = Merchant::with("user")->where('user_id', $data['merchant_id'])->first();
            $user = $merchant->user;

            // ADDED: نضمن الذرّة
            return DB::transaction(function () use ($data, $user) {

                // ADDED: إنشاء Batch جديد للعملية الحالية
                $batch = MerchantWaybillBatch::create([
                    'merchant_id' => $data['merchant_id'],
                    'created_by' => Auth::id(),
                    'quantity' => (int) $data['quantity'],
                ]);

                $waybill = null;

                // === كودك الأصلي مع إضافة batch_id فقط ===
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $waybill = MerchantWaybill::create([
                        "merchant_id" => $data['merchant_id'],
                        "batch_id" => $batch->id,               // ADDED
                        "tracking_no" => generate_merchant_tracking_no()
                    ]);
                }

                // ADDED: تحديث العدد الفعلي لضمان الدقة
                $batch->update([
                    'quantity' => $batch->waybills()->count()
                ]);

                // === كود الإشعار الأصلي كما هو ===
                if ($waybill) {
                    $merchantModel = $waybill->merchant; // rename لتفادي الظل
                    $merchantName = $merchantModel->name ?? 'Unknown Merchant';
                    $trackingNo = $waybill->tracking_no ?? 'N/A';
                    $notificationContent = "🏷️ {$data['quantity']} New Waybills Created For You \n" .
                        "👤 Merchant: {$merchantName}\n" .
                        "🔢 Quantity: {$data['quantity']} waybill(s)\n" .
                        "📦 Tracking: {$trackingNo}\n" .
                        "⏰ " . now()->format('Y-m-d H:i:s');

                    create_notification(
                        $user,
                        "🏷️ New Waybills Created For You {$merchantName}",
                        $notificationContent,
                        [
                            'waybill_id' => $waybill->id,
                            'merchant_id' => $merchantModel->id,
                            'merchant_name' => $merchantName,
                            'quantity' => $data['quantity'],
                            'tracking_no' => $trackingNo,
                            'timestamp' => now()->toDateTimeString(),
                            'priority' => 'medium'
                        ],
                        'waybill_requested',
                        false
                    );
                }
                    activityLog("merchant waybill created","new merchant waybill created for {$merchantName} with batch count : {$batch->quantity}");
                     
                // ADDED: رجّع batch_id علشان الـ FE يفتح الـ View الخاص بالباتش
                return response()->json([
                    "message" => "Merchant Waybill created successfully.",
                    "batch_id" => $batch->id
                ]);
            });

        } catch (QueryException $e) {
            return sendResponse("Error occurred while creating Merchant Waybill.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/merchant_waybills/update",
     *     summary="Update a merchant waybill",
     *     description="Update an existing merchant waybill.",
     *     tags={"WMS"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the merchant waybill to update"),
     *             @OA\Property(property="merchant_id", type="integer", description="Merchant ID"),
     *             @OA\Property(property="quantity", type="integer", description="Quantity"),
     *             @OA\Property(property="status", type="string", description="Waybill status"),
     *             @OA\Property(property="notes", type="string", description="Additional notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant Waybill updated successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function update(UpdateMerchantWaybillRequest $request)
    {
        try {
            $merchant_waybill = MerchantWaybill::findOrFail($request->id);
            $merchant_waybill->update($request->validated());
            return sendResponse("Merchant Waybill updated successfully.", new MerchantWaybillResource($merchant_waybill));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating merchant_waybill.", [], [$e->getMessage()], 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/merchant_waybills/delete",
     *     summary="Delete a merchant waybill",
     *     description="Delete an existing merchant waybill.",
     *     tags={"WMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the merchant waybill to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant Waybill deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function delete(Request $request)
    {
        try {
            MerchantWaybill::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Merchant Waybill deleted successfully.", []);
    }

    public function all()
    {
        return sendResponse("Merchant Waybills", new MerchantWaybillResource(MerchantWaybill::merchantOwner()->all()));
    }
    /**
     * @OA\Get(
     *     path="/merchant_waybills/printMultipleWaybills",
     *     summary="Print Multiple Waybills a merchant waybill",
     *     description="Print Multiple Waybills an existing merchant waybill.",
     *     tags={"WMS"},
     *      @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="ID of the merchant waybill to Print Multiple Waybills",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Merchant Waybill"
     *     ),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function printMultipleWaybills(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['exists:merchant_waybills,id'],
        ]);
        $ids = $data['ids'];
        $waybills = MerchantWaybill::with([
            'merchant',
            'shipment.consignee.city',
            'shipment.consignee.governorate',
            'shipment.consignee.state',
            'shipment.consignee.place',
            'shipment.deliveryAddress.governorate',
            'shipment.deliveryAddress.state',
            'shipment.deliveryAddress.place',
            'shipment.shipper.country',
            'shipment.shipper.state',
            'shipment.shipment_information.zone',
            'shipment.shipment_information',
            'shipment.shipment_items',
            'shipment.shipment_amounts',
            'shipment.merchant'
        ])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn($o) => array_search($o->tracking_no, $ids))
            ->values();
        $html = view('printWaybills', ['waybills' => $waybills])->render();

        return response()->json(['html' => $html]);
    }
}
