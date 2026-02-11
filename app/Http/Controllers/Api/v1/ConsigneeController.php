<?php

namespace App\Http\Controllers\Api\v1;


use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsigneeRequest;
use App\Http\Requests\UpdateConsigneeRequest;
use App\Http\Resources\MerchantAddressBookResource;
use App\Http\Resources\ConsigneeResource;
use App\Models\MerchantAddressBook;
use App\Models\Scopes\ConsigneeScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Consignee;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
/**
 * Controller handling consignee management operations
 *
 * Manages CRUD operations for shipment recipients. Features:
 * - Ownership scoping via ConsigneeScope
 * - Searchable consignee database
 * - Geographic relationship management
 * - Bulk retrieval endpoints
 */
class ConsigneeController extends Controller
{
    /**
     * List consignees
     *
     * @OA\Get(
     *   path="/consignees",
     *   tags={"WMS"},
     *   summary="Get paginated list of consignees with search",
     *   description="Get a list of consignees with optional search query",
     *   operationId="getConsigneesList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for consignee name",
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
     *       @OA\Property(property="message", type="string", example="Consignees retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="country", type="object"),
     *             @OA\Property(property="state", type="object"),
     *             @OA\Property(property="city", type="object"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
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
    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $consignees = Consignee::query();
        $consignees = $consignees->byOwner();
        if (request()->has('query')) {
            $query = request()->input('query');
            $consignees = $consignees
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->with("country", "state", "city", "governorate", "addresses")
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $consignees = $consignees->with("country", "state", "city", "governorate", "addresses")->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("Consignees reterived successfully.", new ConsigneeResource($consignees), []);
    }

    /**
     * Create consignee
     *
     * @OA\Post(
     *   path="/consignees/store",
     *   tags={"WMS"},
     *   summary="Create a new consignee",
     *   description="Create a new consignee with specified details",
     *   operationId="createConsignee",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Consignee creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "country_id",
     *         "state_id",
     *         "city_id"
     *       },
     *       @OA\Property(property="name", type="string", example="Consignee Name", maxLength=255),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="city_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Consignee created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Consignee created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="city_id", type="integer"),
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
    public function store(StoreConsigneeRequest $request)
    {
        $request->validated();
        try {
            $data = $request->all();
            if ($data['cellphone']) {
                $cellphoneSplit = splitPhoneNumber($data['cellphone']);
                $data['country_key_cellphone'] = $cellphoneSplit['country_code'];
                $data['cellphone'] = $cellphoneSplit['national_number'];
            }
            if ($data['alternatePhone']) {
                $alternatePhoneSplit = splitPhoneNumber($data['alternatePhone']);
                $data['country_key_alternatePhone'] = $alternatePhoneSplit['country_code'];
                $data['alternatePhone'] = $alternatePhoneSplit['national_number'];
            }
            $consignee = Consignee::create($data);
            activityLog('consignee created',"new consignee created with name : {$consignee->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Consignee created successfully.", new ConsigneeResource($consignee));
    }

    /**
     * Update consignee
     *
     * @OA\Post(
     *   path="/consignees/update",
     *   tags={"WMS"},
     *   summary="Update consignee details",
     *   description="Update an existing consignee's details",
     *   operationId="updateConsignee",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Consignee update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id",
     *         "name"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="name", type="string", example="Updated Consignee Name", maxLength=255),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="city_id", type="integer", example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Consignee updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Consignee updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="city_id", type="integer"),
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
    public function update(UpdateConsigneeRequest $request)
    {
        $request->validated();
        try {
            $data = $request->all();
            if ($data['cellphone']) {
                $cellphoneSplit = splitPhoneNumber($data['cellphone']);
                $data['country_key_cellphone'] = $cellphoneSplit['country_code'];
                $data['cellphone'] = $cellphoneSplit['national_number'];
            }
            if ($data['alternatePhone']) {
                $alternatePhoneSplit = splitPhoneNumber($data['alternatePhone']);
                $data['country_key_alternatePhone'] = $alternatePhoneSplit['country_code'];
                $data['alternatePhone'] = $alternatePhoneSplit['national_number'];
            }
            $consignee = Consignee::withoutGlobalScope(ConsigneeScope::class)->find($request->id);
            $consignee->update($data);
            activityLog('consignee updated',"consignee updated with name : {$consignee->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Consignee updated successfully.", new ConsigneeResource($consignee));
    }

    /**
     * Delete consignee
     *
     * @OA\Post(
     *   path="/consignees/delete",
     *   tags={"WMS"},
     *   summary="Delete a consignee",
     *   description="Delete a consignee by its ID",
     *   operationId="deleteConsignee",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Consignee deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Consignee deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Consignee deleted successfully."),
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
            $consignee = Consignee::withoutGlobalScope(ConsigneeScope::class)->findOrFail($request->id);
            $consignee->delete();
            activityLog('consignee deleted',"consignee deleted with name : {$consignee->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Consignee deleted successfully.", []);
    }

    /**
     * Get all consignees
     *
     * @OA\Get(
     *   path="/consignees/all",
     *   tags={"WMS"},
     *   summary="Get all consignees",
     *   description="Retrieve all consignees without pagination",
     *   operationId="getAllConsignees",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Consignees retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Consignees"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="country_id", type="integer"),
     *             @OA\Property(property="state_id", type="integer"),
     *             @OA\Property(property="city_id", type="integer"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function all(Request $request)
    {
        $relations = [
            "state:id,en_name",
            "country:id,name",
            "governorate:id,en_name",
            "place:id,en_name",
            "addresses"
        ];

        $consignees = null;

        if (auth()->user()->roles()->first()->name === 'Merchant') {
            $consignees = MerchantAddressBook::with($relations)->where('merchant_id', auth()->user()->id);
        } else {
            $consignees = Consignee::with($relations)->byOwner();
        }

        if ($request->timestamp) {
            $consignees = $consignees->where('updated_at', '<', $request->timestamp);
            if ($consignees->count() === 0) {
                return sendResponse("Consignees", []);
            }
        }
        if (auth()->user()->roles()->first()->name === 'Merchant') {
            return sendResponse("Consignees", new MerchantAddressBookResource($consignees->get()));
        } else {
            return sendResponse("Consignees", new ConsigneeResource($consignees->get()));
        }
    }
    // App/Http/Controllers/ConsigneeController.php
    public function addresses(Consignee $consignee, Request $request)
    {
        // Base query مع العلاقات + ترتيب بحسب الاستخدام/الإنشاء
        $baseQuery = $consignee->addresses()->with([
            'country:id,name',
            'governorate:id,en_name,ar_name',
            'state:id,en_name,ar_name',
            'place:id,en_name,ar_name',
            'city:id,name',
        ])
            ->orderByDesc('last_used_at')
            ->orderByDesc('id');

        // ⬅️ أولاً: هات العناوين الـ verified بس
        $verifiedAddresses = (clone $baseQuery)
            ->where('is_verified', 1)   // مهم نخليها 1 مش true
            ->get();

        if ($verifiedAddresses->isNotEmpty()) {
            // لو فيه verified رجّعها بس
            return sendResponse('Consignee addresses (verified only)', $verifiedAddresses);
        }

        // لو مفيش verified رجّع آخر عنوان واحد (أيًا كان حاله)
        $fallback = $baseQuery->limit(1)->get();

        return sendResponse('Consignee addresses (fallback last)', $fallback);
    }





    // public function addresses(Consignee $consignee, Request $request)
    // {
    //     $query = $consignee->addresses()
    //         ->with([
    //             'country:id,name',
    //             'governorate:id,en_name,ar_name',
    //             'state:id,en_name,ar_name',
    //             'place:id,en_name,ar_name',
    //             'city:id,name',
    //         ])
    //         ->orderByDesc('approved')
    //         ->orderByDesc('last_used_at')
    //         ->orderByDesc('id');

    //     if ($request->has('approved')) {
    //         $query->where('approved', (bool) $request->boolean('approved'));
    //     }

    //     return sendResponse('Consignee addresses', $query->get());
    // }
}
