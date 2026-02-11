<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreMerchantAddressBookRequest;
use App\Http\Requests\UpdateMerchantAddressBookRequest;
use App\Http\Resources\MerchantAddressBookResource;
use App\Models\MerchantAddressBook;
use App\Traits\CustomeTrait;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MerchantAddressBookController extends Controller
{
    use CustomeTrait;
    /**
     * Get Merchant Address Book Entries
     *
     * @OA\Get(
     *     path="/merchant/address_book",
     *     summary="Get merchant address book entries with search support",
     *     description="
     * Retrieve address book entries for authenticated merchant with search and pagination support.
     * 
     * **Features:**
     * - Search by name, email, or phone
     * - Pagination support
     * - Related location data included
     * - Shipment creation optimization
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getMerchantAddressBook",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for name, email, or phone",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address book entries retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address book entries retrieved successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = $request->input('query');
        $merchantId = Auth::id();

        $addressBooks = MerchantAddressBook::where('merchant_id', $merchantId);

        if ($query) {
            $addressBooks = $addressBooks->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', '%' . $query . '%')
                    ->orWhere('email', 'LIKE', '%' . $query . '%')
                    ->orWhere('cellphone', 'LIKE', '%' . $query . '%');
            })
                ->with(['country:id,name', 'governorate:id,en_name,ar_name', 'state:id,en_name,ar_name', 'place:id,en_name,ar_name'])
                ->orderBy('name', 'asc')
                ->get();
        } else {
            $addressBooks = $addressBooks
                ->with(['country:id,name', 'governorate:id,en_name,ar_name', 'state:id,en_name,ar_name', 'place:id,en_name,ar_name'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }

        return sendResponse(
            "Address book entries retrieved successfully.",
            new MerchantAddressBookResource($addressBooks)
        );
    }

    /**
     * Create Address Book Entry
     *
     * @OA\Post(
     *     path="/merchant/address_book/store",
     *     summary="Create new address book entry",
     *     description="
     * Create a new address book entry for the authenticated merchant.
     * 
     * **Features:**
     * - Complete address validation
     * - Location data linking
     * - Duplicate prevention
     * - Auto merchant association
     * 
     * **Security:**
     * - Merchant authentication required
     * - Request validation applied
     * ",
     *     operationId="createAddressBookEntry",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Address book entry data",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address book entry created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address book entry created successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function store(StoreMerchantAddressBookRequest $request)
    {


        try {
            $addressBook = DB::transaction(function () use ($request) {

                // 1. Create AddressBook
                $data = $request->all();
                $data['merchant_id'] = Auth::id();

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
                $addressBook = MerchantAddressBook::create($data);

                // 2. Create Consignee (لو فيه مشكلة هنا rollback هيشتغل)
                $this->storeConsignee($request->all());

                // 3. Load relations
                $addressBook->load([
                    'country:id,name',
                    'governorate:id,en_name,ar_name',
                    'state:id,en_name,ar_name',
                    'place:id,en_name,ar_name'
                ]);

                return $addressBook;
            });

            return sendResponse(
                "Address book entry created successfully.",
                new MerchantAddressBookResource($addressBook)
            );

        } catch (\Exception $e) {
            return sendResponse(
                "Error occurred while creating address book entry.",
                [],
                $e->getMessage(),
                422
            );
        }
    }

    /**
     * Get Address Book Entry
     *
     * @OA\Get(
     *     path="/merchant/address_book/{id}",
     *     summary="Get specific address book entry",
     *     description="Get detailed information for a specific address book entry.",
     *     operationId="getAddressBookEntry",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Address book entry ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address book entry retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address book entry retrieved successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Address book entry not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $addressBook = MerchantAddressBook::where('merchant_id', Auth::id())
                ->where('id', $id)
                ->with(['country:id,name', 'governorate:id,en_name,ar_name', 'state:id,en_name,ar_name', 'place:id,en_name,ar_name'])
                ->firstOrFail();

            return sendResponse(
                "Address book entry retrieved successfully.",
                new MerchantAddressBookResource($addressBook)
            );
        } catch (\Exception $e) {
            return sendResponse("Address book entry not found.", [], ["Address book entry not found"], 404);
        }
    }

    /**
     * Update Address Book Entry
     *
     * @OA\Post(
     *     path="/merchant/address_book/{id}/update",
     *     summary="Update address book entry",
     *     description="Update an existing address book entry for the authenticated merchant.",
     *     operationId="updateAddressBookEntry",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Address book entry ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Updated address book entry data",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address book entry updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address book entry updated successfully."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Address book entry not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function update(UpdateMerchantAddressBookRequest $request, $id)
    {
        try {
            $addressBook = MerchantAddressBook::where('merchant_id', Auth::id())
                ->where('id', $id)
                ->firstOrFail();

            $addressBook->update($request->validated());

            $addressBook->load(['country:id,name', 'governorate:id,en_name,ar_name', 'state:id,en_name,ar_name', 'place:id,en_name,ar_name']);

            return sendResponse(
                "Address book entry updated successfully.",
                new MerchantAddressBookResource($addressBook)
            );
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating address book entry.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Address book entry not found.", [], ["Address book entry not found"], 404);
        }
    }

    /**
     * Delete Address Book Entry
     *
     * @OA\Post(
     *     path="/merchant/address_book/{id}/delete",
     *     summary="Delete address book entry",
     *     description="Delete an address book entry for the authenticated merchant.",
     *     operationId="deleteAddressBookEntry",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Address book entry ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address book entry deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address book entry deleted successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items()),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Address book entry not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $addressBook = MerchantAddressBook::where('merchant_id', Auth::id())
                ->where('id', $id)
                ->firstOrFail();

            $addressBook->delete();

            return sendResponse("Address book entry deleted successfully.", []);
        } catch (QueryException $e) {
            return sendResponse("Error occurred while deleting address book entry.", [], [$e->getMessage()], 422);
        } catch (\Exception $e) {
            return sendResponse("Address book entry not found.", [], ["Address book entry not found"], 404);
        }
    }

    /**
     * Search Address Book Entries
     *
     * @OA\Get(
     *     path="/merchant/address_book/search",
     *     summary="Search address book entries for shipment creation",
     *     description="
     * Search address book entries optimized for shipment creation form prefilling.
     * 
     * **Features:**
     * - Fast search functionality
     * - Limited results for performance
     * - Shipment creation optimization
     * - Name priority sorting
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="searchAddressBookEntries",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query (required)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address book search results retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address book search results retrieved successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Query parameter is required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function search(Request $request)
    {
        $query = $request->input('query');
        $merchantId = Auth::id();

        if (!$query) {
            return sendResponse("Query parameter is required.", [], ["Query parameter is required"], 400);
        }

        $addressBooks = MerchantAddressBook::where('merchant_id', $merchantId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', '%' . $query . '%')
                    ->orWhere('email', 'LIKE', '%' . $query . '%')
                    ->orWhere('cellphone', 'LIKE', '%' . $query . '%');
            })
            ->with(['country:id,name', 'governorate:id,en_name,ar_name', 'state:id,en_name,ar_name', 'place:id,en_name,ar_name'])
            ->orderBy('name', 'asc')
            ->limit(10)
            ->get();

        return sendResponse(
            "Address book search results retrieved successfully.",
            MerchantAddressBookResource::collection($addressBooks)
        );
    }

    /**
     * Get All Address Book Entries
     *
     * @OA\Get(
     *     path="/merchant/address_book/all",
     *     summary="Get all address book entries",
     *     description="
     * Retrieve all address book entries for the authenticated merchant sorted by name.
     * 
     * **Features:**
     * - Complete address book
     * - Name alphabetical sorting
     * - Location data included
     * - No pagination applied
     * 
     * **Security:**
     * - Merchant authentication required
     * - Merchant-scoped data only
     * ",
     *     operationId="getAllAddressBookEntries",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="All address book entries retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All address book entries retrieved successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function all()
    {
        $merchantId = Auth::id();

        $addressBooks = MerchantAddressBook::where('merchant_id', $merchantId)
            ->with(['country:id,name', 'governorate:id,en_name,ar_name', 'state:id,en_name,ar_name', 'place:id,en_name,ar_name'])
            ->orderBy('name', 'asc')
            ->get();

        return sendResponse(
            "All address book entries retrieved successfully.",
            MerchantAddressBookResource::collection($addressBooks)
        );
    }
}
