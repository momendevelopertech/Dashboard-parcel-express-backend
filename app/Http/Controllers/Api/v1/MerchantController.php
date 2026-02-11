<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;


use App\Http\Requests\StoreMerchantRequest;
use App\Http\Requests\UpdateMerchantRequest;
use App\Http\Resources\AllMerchantsResource;
use App\Http\Resources\MerchantResource;
use App\Models\Account;
use App\Models\MerchantChatMessage;
use App\Models\MerchantChatSession;
use App\Models\MerchantWaybill;
use App\Models\State;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Merchant;
use App\Models\MerchantAccount;

use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\CommissionTemplate;
use App\Models\MerchantCommission;
use App\Models\MerchantImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
/**
 * Controller handling merchant lifecycle management and geographic associations
 *
 * Manages merchant accounts with complex ownership structures. Features:
 * - Multi-tier geographic hierarchy (country > governorate > state > place)
 * - Ownership-scoped data access
 * - Transactional account creation
 * - Merchant role enforcement
 * - Permanent audit trail for financial compliance
 */
class MerchantController extends Controller
{
    /**
     * Get paginated list of merchants with search
     *
     * @OA\Get(
     *   path="/merchants",
     *   tags={"Merchants"},
     *   summary="Get paginated list of merchants with search",
     *   description="Get a list of merchants with optional search query",
     *   operationId="getMerchantsList",
     *   security={{ "sanctum": { }}},
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for merchant name or email",
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
     *       @OA\Property(property="message", type="string", example="Merchants retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="country", type="object"),
     *             @OA\Property(property="governorate", type="object"),
     *             @OA\Property(property="state", type="object"),
     *             @OA\Property(property="place", type="object"),
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
        $merchants = User::merchantOwner()->whereHas('merchant');
        $perPage = request()->input('per_page', 8);

        $query = request()->input('query');
        $merchants = $merchants->with("merchant.country", "merchant.governorate", "merchant.state", "merchant.place", "merchant.settings")
            ->orderBy('id', 'desc');

        // Filter by is_guest based on showGuest parameter
        $showGuest = request()->input('showGuest');
        if ($showGuest === 'true') {
            $merchants = $merchants->whereHas('merchant', function ($q) {
                $q->where('is_guest', true);
            });
        } else {
            $merchants = $merchants->whereHas('merchant', function ($q) {
                $q->where('is_guest', false);
            });
        }
        if ($query) {
            $merchants = $merchants->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($query) . '%']);
            });
        }
        $merchants = $merchants->paginate($perPage);

        return sendResponse("Merchants retrieved successfully.", new MerchantResource($merchants), []);
    }

    public function deleted_merchants_index()
    {
        $perPage = request()->input('per_page', 8);
        $query   = request()->input('query');
        $showGuest = request()->input('showGuest');

        // Query deleted merchants directly
        $merchants = Merchant::onlyTrashed()
            ->with([
                'user' => function ($q) {
                    $q->withTrashed()  // include soft-deleted users
                    ->with('deletedBy'); // eager load who deleted the user
                },
                'country',
                'governorate',
                'state',
                'place',
                'settings'
            ])
            ->orderBy('id', 'desc');

        // Filter by is_guest
        if ($showGuest === 'true') {
            $merchants = $merchants->where('is_guest', true);
        } else {
            $merchants = $merchants->where('is_guest', false);
        }

        // Search by name or email (merchant's user info)
        if ($query) {
            $merchants = $merchants->whereHas('user', function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($query) . '%']);
            });
        }

        $merchants = $merchants->paginate($perPage);

        return sendResponse(
            "Deleted merchants retrieved successfully.",
            new MerchantResource($merchants),
            []
        );
    }



    public function restoreMerchant(Request $request)
    {
        $userId = $request->id;

        // Fetch the soft-deleted user who owns the merchant
        $user = User::onlyTrashed()
            ->where('id', $userId)
            ->with(['merchant' => function ($q) {
                $q->withTrashed(); // Include soft-deleted merchant
            }])
            ->first();

        if (!$user) {
            return sendResponse(
                'User not found.',
                [],
                false,
                ['User not found']
            );
        }

        if (!$user->trashed()) {
            return sendResponse(
                'User is already active.',
                [],
                false
            );
        }

        // Restore the user
        $user->restore();

        // Clear deleted_by on user
        $user->deleted_by = null;
        $user->saveQuietly();

        // Restore related merchant if it exists and is soft-deleted
        if ($user->merchant && $user->merchant->trashed()) {
            $user->merchant->restore();
        }

        return sendResponse(
            'Merchant restored successfully.',
            new MerchantResource($user->fresh()),
            true
        );
    }






    /**
     * Create a new merchant
     *
     * @OA\Post(
     *   path="/merchants/store",
     *   tags={"WMS"},
     *   summary="Create a new merchant",
     *   description="Create a new merchant with associated user account",
     *   operationId="createMerchant",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Merchant creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "email",
     *         "password",
     *         "country_id",
     *         "governorate_id",
     *         "state_id",
     *         "address",
     *         "contact_no"
     *       },
     *       @OA\Property(property="name", type="string", example="Merchant Name", maxLength=255),
     *       @OA\Property(property="email", type="string", format="email", example="merchant@example.com"),
     *       @OA\Property(property="password", type="string", format="password", example="password123"),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="governorate_id", type="integer", example=1),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="address", type="string", maxLength=255),
     *       @OA\Property(property="contact_no", type="string", maxLength=20)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Merchant created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="governorate_id", type="integer"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="place_id", type="integer"),
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
    public function store(StoreMerchantRequest $request)
    {
        $request->validated();
        DB::beginTransaction();
        try {
            $data = $request->all();
            if ($request->phone) {
                $phoneSplit = splitPhoneNumber($request->phone);
                $userData['country_code'] = $phoneSplit['country_code'];
                $userData['phone'] = $phoneSplit['national_number'];
            } else {
                $userData['country_code'] = null;
                $userData['phone'] = null;
            }
            if ($request->contact_no) {
                $contactNoSplit = splitPhoneNumber($request->contact_no);
                $data['country_code'] = $contactNoSplit['country_code'];
                $data['contact_no'] = $contactNoSplit['national_number'];
            } else {
                $data['country_code'] = null;
                $data['contact_no'] = null;
            }
            $phoneSplit = splitPhoneNumber($request->phone);
            // $last4 = substr($phoneSplit['national_number'], -4);
            // $nameWithPhone = "{$last4} {$request->name}";
            $user = User::create([
                "owner_id" => facility("id"),
                "owner_type" => facility("type"),
                "email" => $request->email,
                "name" => $request->name,
                "username" => $request->username,
                'password' => Hash::make($request->password),
                ...$userData
            ]);
            $user->assignRole("Merchant");
            $data['user_id'] = $user->id;
            $data['owner_id'] = facility("id");
            $data['owner_type'] = facility("type");
            if ($request->hasFile('image')) {
                foreach ($request->file('image') as $image) {
                    $path= uploadFile($image, 'public/merchant_images');
                    MerchantImage::create([
                        'merchant_id' => $user->id,
                        'image_path' => $path
                    ]);
                }
            }
            $merchant = Merchant::create($data);
            Account::create([
                "accountable_id" => $user->id,
                "accountable_type" => User::class,
            ]);
            // Default commissions are now created automatically via MerchantObserver
            // However, we want to ensure specific facility templates are applied from the Controller as per request.

            // Check for Commission Templates matching the facility (owner)
            $facilityId = facility('id');
            $facilityType = facility('type');

            if ($facilityId && $facilityType) {
                // Fetch generic templates (no specific state) for this facility
                $globalTemplates = CommissionTemplate::where('owner_id', $facilityId)
                    ->where('owner_type', $facilityType)
                    ->whereNull('state_id')
                    ->get();

                foreach ($globalTemplates as $tpl) {
                    MerchantCommission::updateOrCreate(
                        [
                            'merchant_id' => $user->id, // MerchantCommission uses user_id as merchant_id usually
                            'country_id' => $tpl->country_id,
                            'state_id' => null
                        ],
                        [
                            'base_delivery_fee' => $tpl->base_delivery_fee,
                            'base_return_fee' => $tpl->base_return_fee,
                            'delivery_discount_amount' => $tpl->delivery_discount_amount,
                            'return_discount_amount' => $tpl->return_discount_amount,
                            'delivery_fee' => $tpl->delivery_fee,
                            'return_fee' => $tpl->return_fee,
                        ]
                    );
                }

                // Fetch state-specific templates for this facility
                $stateTemplates = CommissionTemplate::where('owner_id', $facilityId)
                    ->where('owner_type', $facilityType)
                    ->whereNotNull('state_id')
                    ->get();

                foreach ($stateTemplates as $tpl) {
                    MerchantCommission::updateOrCreate(
                        [
                            'merchant_id' => $user->id,
                            'country_id' => $tpl->country_id,
                            'state_id' => $tpl->state_id
                        ],
                        [
                            'base_delivery_fee' => $tpl->base_delivery_fee,
                            'base_return_fee' => $tpl->base_return_fee,
                            'delivery_discount_amount' => $tpl->delivery_discount_amount,
                            'return_discount_amount' => $tpl->return_discount_amount,
                            'delivery_fee' => $tpl->delivery_fee,
                            'return_fee' => $tpl->return_fee,
                        ]
                    );
                }
            }

            // Create wallet for the merchant
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0
            ]);
            activityLog('Merchant created', "new merchant created with username : {$user->username}");
            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred.", [], 4022, [$e->getMessage()], 422);
        }
        return sendResponse("Merchant created successfully.", new MerchantResource($merchant));
    }

    /**
     * Update merchant details
     *
     * @OA\Post(
     *   path="/merchants/update",
     *   tags={"WMS"},
     *   summary="Update merchant details",
     *   description="Update an existing merchant's details",
     *   operationId="updateMerchant",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Merchant update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="user_id", type="integer", example=1),
     *       @OA\Property(property="address", type="string", maxLength=255),
     *       @OA\Property(property="contact_no", type="string", maxLength=20)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Merchant updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="governorate_id", type="integer"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="place_id", type="integer"),
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
    public function update(UpdateMerchantRequest $request)
    {
        $request->validated();
        try {
            $data = $request->all();

            // Split phone for User model
            if ($request->phone) {
                $phoneSplit = splitPhoneNumber($request->phone);
                $userData['country_code'] = $phoneSplit['country_code'];
                $userData['phone'] = $phoneSplit['national_number'];
            } else {
                $userData['country_code'] = null;
                $userData['phone'] = null;
            }

            // Split contact_no for Merchant model
            if ($request->contact_no) {
                $contactNoSplit = splitPhoneNumber($request->contact_no);
                $data['country_code'] = $contactNoSplit['country_code'];
                $data['contact_no'] = $contactNoSplit['national_number'];
            } else {
                $data['country_code'] = null;
                $data['contact_no'] = null;
            }
            if ($request->hasFile('image')) {
                $uploadedImages = [];
                foreach ($request->file('image') as $image) {
                    $uploadedImages[] = uploadFile($image, 'public/merchant_images');
                }
                $data['image'] = $uploadedImages;
            } else {
                // Keep existing image if no new file is uploaded
                unset($data['image']);
            }

            if ($request->has('username'))
                $userData["username"] = $request->username;

            $user = User::find($request->id);
            $user->update(array_merge($request->only("name", "email"), $userData));
            $user->merchant->update($data);
            activityLog('Merchant updated', "merchant updated with username : {$user->username}");
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }

        return sendResponse("Merchant updated successfully.", new MerchantResource($user));
    }

    /**
     * Manage merchant images
     *
     * Add new images or remove specific images from a merchant
     *
     * @OA\Post(
     *   path="/merchants/{merchantId}/add-images",
     *   tags={"WMS"},
     *   summary="Manage merchant images",
     *   description="Add new images or remove specific images from a merchant",
     *   operationId="manageMerchantImages",
     *   security={{ "bearerAuth": { }}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="image", type="array", @OA\Items(type="file"), description="New images to add"),
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Images updated successfully"
     *   )
     * )
     */
    public function addImage(Request $request,$merchantId)
    {
        $request->validate([
            'image' => 'required|array',
            'image.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        try {
            $user = User::findOrFail($merchantId);
            if (!$user) {
                return sendResponse("Merchant not found.", [], false, [], 422);
            }
            if ($request->has('image') || $request->hasFile('image')) {
                if ($request->hasFile('image')) {
                    $files = $request->file('image');
                    foreach ($files as $file) {
                        if ($file && $file->isValid()) {
                            $path = uploadFile($file, 'public/merchant_images');
                            MerchantImage::create([
                                'merchant_id' => $user->id,
                                'image_path' => $path
                            ]);
                        }
                    }
                }
            } 
            activityLog('Merchant images updated', "merchant images updated for merchant ID: {$user->id}");

            return sendResponse("Images updated successfully.",[]);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }
/**
     * Delete image
     *
     * @OA\Post(
     *   path="/merchants/{imageId}/delete-image",
     *   tags={"WMS"},
     *   summary="Delete image",
     *   description="Delete an image from a merchant",
     *   operationId="deleteImage",
     *   security={{ "bearerAuth": { }}},
     *   @OA\Response(
     *     response=200,
     *     description="Image deleted successfully"
     *   )
     * )
     */
    public function deleteImage($imageId)
    {
      
        try {
            $merchantImage = MerchantImage::findOrFail($imageId);
            $merchantImage->delete();
            return sendResponse("Image deleted successfully.", []);
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], false, [$e->getMessage()], 422);
        }
    }

    /**
     * Get merchant images
     * @OA\Get(
     *   path="/merchants/{merchantId}/get-images",
     *   tags={"WMS"},
     *   summary="Get merchant images",
     *   description="Retrieve all images for a specific merchant",
     *   operationId="getMerchantImages",
     *   security={{ "bearerAuth": { }}},
     *   @OA\Response(
     *     response=200,
     *     description="Merchant images retrieved successfully"
     *   )
     * )
     */
    public function getImages($merchant_id)
    {
        $user = User::findOrFail($merchant_id);
        if (!$user) {
            return sendResponse("Merchant not found.", [], false, [], 422);
        }
        $images = MerchantImage::where('merchant_id', $user->id)->select(['id', 'image_path'])->get();
        return sendResponse("Merchant images retrieved successfully.", [
            'merchant_id' => $user->id,
            'images' => $images
        ]);
    }

    /**
     * Get merchant details
     *
     * @OA\Post(
     *   path="/merchants/edit/{id}",
     *   tags={"WMS"},
     *   summary="Get merchant details",
     *   description="Retrieve details of a specific merchant",
     *   operationId="getMerchantDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="Merchant ID",
     *     required=true,
     *     @OA\Schema(
     *         type="integer",
     *         format="int64"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Merchant retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string"),
     *         @OA\Property(property="merchant", type="object"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Merchant not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Merchant not found.")
     *     )
     *   )
     * )
     */
    public function edit($id)
    {
        $user = User::with("merchant")->find($id);
        return sendResponse("Merchant", new MerchantResource($user));
    }

    /**
     * Delete merchant
     *
     * @OA\Post(
     *   path="/merchants/delete",
     *   tags={"WMS"},
     *   summary="Delete a merchant",
     *   description="Delete a merchant and their associated records",
     *   operationId="deleteMerchant",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Merchant deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Merchant deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchant deleted successfully."),
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
            DB::transaction(function () use ($request) {

                $merchant = User::find($request->id);

                if (!$merchant) {
                    abort(404, 'Merchant not found.');
                }

                // Track who deleted the merchant
                $merchant->deleted_by = auth()->id();
                $merchant->saveQuietly();

                // Soft delete merchant model if exists
                $merchant->merchant?->delete();

                // Soft delete user
                $merchant->delete();

                activityLog(
                    'Merchant deleted',
                    "Merchant with username {$merchant->username} deleted by user ID " . auth()->id()
                );
            });

            return sendResponse("Merchant deleted successfully.", []);

        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
    }


    /**
     * Get all merchants
     *
     * @OA\Get(
     *   path="/merchants/all",
     *   tags={"WMS"},
     *   summary="Get all merchants",
     *   description="Retrieve all merchants without pagination",
     *   operationId="getAllMerchants",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Merchants retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Merchants"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="merchant", type="object"),
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
        $merchants = User::merchantOwner()->merchants();
        if ($request->timestamp) {
            $merchants = $merchants->where('updated_at', '<', $request->timestamp);
            if ($merchants->count() < 0) {
                return sendResponse("Merchants", []);
            }
        }
        return sendResponse("Merchants", AllMerchantsResource::collection($merchants->get()));
    }

    public function profile($id)
    {
        try {
            $user = User::with([
                'merchant.country:id,name',
                'merchant.governorate:id,en_name,ar_name,country_id',
                'merchant.state:id,en_name,ar_name,governorate_id',
                'merchant.place:id,en_name,ar_name,state_id',
                'merchant.settings'
            ])->find($id);

            if (!$user || !$user->merchant) {
                return sendResponse("Merchant not found.", [], [], 404);
            }

            return sendResponse("Merchant profile retrieved successfully.", new MerchantResource($user));
        } catch (QueryException $e) {
            return sendResponse("Error occurred.", [], [$e->getMessage()], 422);
        }
    }

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

        $merchant['states'] = State::where('country_id', $merchant->merchant->country_id)->get();

        return sendResponse("Merchant retrieved successfully.", $merchant, []);
    }

    public function updateDefaultMerchantCommission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required',
        ]);
        if ($validator->fails()) {
            return sendResponse(
                'Validation error',
                [],
                false,
                $validator->errors(),
                422
            );
        }
        $setting = Setting::where('key', 'default_merchant_commission')->first();
        if (!$setting) {
            return sendResponse("No Setting Found.", null, [], 404);
        }
        $setting->value = $request->value;
        $setting->save();
        return sendResponse("Default merchant commission updated successfully.", $setting);
    }

    public function getDefaultMerchantCommission()
    {
        $setting = Setting::where('key', 'default_merchant_commission')->first();
        if (!$setting) {
            return sendResponse("No Setting Found.", null, [], 404);
        }
        return sendResponse("Default merchant commission retrieved successfully.", $setting);
    }
    /**
     * @OA\Get(
     *     path="/api/merchants/with-chat-info",
     *     summary="Get merchants with chat information",
     *     description="Retrieves all merchants with their chat session information and unread message counts",
     *     tags={"Merchants"},
     *     security={{ "bearerAuth":{ }}},
     *     @OA\Response(
     *         response=200,
     *         description="Merchants retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Merchants retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+96812345678"),
     *                 @OA\Property(property="current_session_id", type="integer", example=123),
     *                 @OA\Property(property="status", type="string", example="ACTIVE"),
     *                 @OA\Property(property="unread_messages_count", type="integer", example=5),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="waybills_used", type="integer", example=10),
     *                 @OA\Property(property="waybills_unused", type="integer", example=5)
     *             ))
     *         )
     *     )
     * )
     */
    public function getMerchantsWithChatInfo()
    {
        try {
            $merchants = Merchant::with([
                'user' => function ($query) {
                    $query->select('id', 'name', 'email', 'phone');
                }
            ])
                ->get();
            $merchantsWithChatInfo = [];

            foreach ($merchants as $merchant) {
                $waybillsUsed = MerchantWaybill::where('merchant_id', $merchant->user_id)
                    ->where('used', true)
                    ->count();

                $waybillsUnused = MerchantWaybill::where('merchant_id', $merchant->user_id)
                    ->where('used', false)
                    ->count();

                $activeSession = MerchantChatSession::where('merchant_id', $merchant->id)
                    ->where('status', 'ACTIVE')
                    ->first();

                $unreadCount = 0;
                if ($activeSession) {
                    $unreadCount = MerchantChatMessage::where('merchant_chat_session_id', $activeSession->id)
                        ->where('is_read', false)
                        ->where('sender_type', 'MERCHANT')
                        ->count();
                }

                $merchantsWithChatInfo[] = [
                    'id' => $merchant->id,
                    'user_id' => $merchant->user_id,
                    'name' => $merchant->user->name,
                    'email' => $merchant->user->email,
                    'phone' => $merchant->user->phone,
                    'current_session_id' => $activeSession ? $activeSession->id : null,
                    'status' => $activeSession ? $activeSession->status : 'INACTIVE',
                    'unread_messages_count' => $unreadCount,
                    'updated_at' => $activeSession ? $activeSession->updated_at : $merchant->updated_at,
                    'waybills_used' => $waybillsUsed,
                    'waybills_unused' => $waybillsUnused
                ];
            }

            usort($merchantsWithChatInfo, function ($a, $b) {
                return strtotime($b['updated_at']) - strtotime($a['updated_at']);
            });

            return sendResponse("Merchants retrieved successfully.", $merchantsWithChatInfo);
        } catch (\Exception $e) {
            return sendResponse("Error Occurred.", [], [$e->getMessage()], 422);
        }
    }

    public function toggleMerchantStatus($id)
    {
        $merchant = User::find($id);
        $currentStatus = $merchant->status;
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        $actionEn = ($newStatus === 'inactive') ? 'Deactivated' : 'Activated';
        DB::transaction(function () use ($merchant, $newStatus) {
            $merchant->update([
                'status' => $newStatus,
                'verification_code' => null,
                'verification_code_expires_at' => null,
            ]);
            if ($newStatus === 'inactive') {
                $merchant->tokens()->delete();
            }
        });
        $messageEn = "Merchant account has been {$actionEn} successfully.";
        activityLog('merchant status changed', "merchant with username : {$merchant->username} his status changed to {$newStatus}");
        return sendResponse($messageEn, [
            'merchant_id' => $merchant->id,
            'new_status' => $newStatus,
        ], true, [], 200);
    }

    public function findByPhone(Request $request)
    {
        // $this->authorize('viewAny', \App\Models\Merchant::class);

        $data = $request->validate([
            'phone' => ['required', 'string'],
            'country_code' => ['nullable', 'string'],
            // 'with' => ['nullable', 'string'],
        ]);

        $rawPhone = preg_replace('/\D+/', '', $data['phone']); // أرقام فقط
        $ccInput = $data['country_code'] ?? null;
        if ($ccInput !== null) {
            $ccInput = ltrim($ccInput, '+'); // شيل +
        }

        // لو عندك splitPhoneNumber خليه يساعد لو الرقم فيه +CC
        if (function_exists('splitPhoneNumber') && (str_starts_with($data['phone'], '+') || !$ccInput)) {
            try {
                $p = splitPhoneNumber($data['phone']);
                $ccInput = $ccInput ?: ltrim($p['country_code'] ?? '', '+');
                $rawPhone = $p['national_number'] ?? $rawPhone;
            } catch (\Throwable $e) {
                // تجاهل وأكمل بالتطبيع البسيط
            }
        }

        $with = collect(explode(',', (string) ($data['with'] ?? '')))
            ->filter()->values()->all();

        $query = User::query()
            ->merchants()

            ->with(array_merge(['merchant', 'shipments:id,merchant_id,tracking_no,status,created_at'], $with))
            // طَبّع country_code في الـ DB بإزالة +
            ->when($ccInput, function ($q) use ($ccInput) {
                $q->whereRaw("REPLACE(country_code, '+','') = ?", [$ccInput]);
            })
            // طَبّع الـ phone في المدخلات (هو أصلاً بدون +)
            ->where('phone', $rawPhone);

        $user = $query->first();

        // dd($user);

        if (!$user) {
            return sendResponse('Merchant not found', [], false, ['No merchant with this phone.'], 404);
        }

        return sendResponse('OK', [
            'user' => $user->only(['id', 'name', 'username', 'email', 'country_code', 'phone', 'status', 'created_at']),
            'merchant' => optional($user->merchant)->only([
                'id',
                'address',
                'country_id',
                'governorate_id',
                'state_id',
                'place_id',
                'currency',
                'lat',
                'lng',
                'is_guest'
            ]),
            // 'relations_loaded' => $with,
        ]);
    }

    /**
     * Custom KPI endpoint for merchant shipments analytics
     */
    public function merchantKpi(Request $request)
    {
        $merchantId = $request->get('merchant_id') ?? $request->get('id');
        if (!$merchantId) {
            return sendResponse('merchant_id required', [], false, [], 400);
        }
        $merchant = \App\Models\Merchant::where('user_id', $merchantId)->first();
        if (!$merchant) {
            return sendResponse('Merchant not found', [], false, [], 404);
        }
        // Pickup tasks aggregates
        $picked = (int) $merchant->merchantPickupTasks()->sum('picked_shipments_no');
        $extra = (int) $merchant->merchantPickupTasks()->sum('extra_shipments_no');
        $totalPickedAndExtra = $picked + $extra;

        // total registered shipments via task accessor on Merchant (sums getRegisteredShipmentsNoAttribute on tasks)
        $totalRegistered = (int) ($merchant->total_registered_shipments_no ?? 0);

        // total extra shipments (accessor on Merchant)
        $totalExtra = (int) ($merchant->total_extra_shipments_no ?? $extra);

        // percentage of registered -> extra (registered divided by extra)
        $percentage = $totalExtra > 0 ? round(($totalRegistered / $totalExtra) * 100, 2) : 0;

        // unique states count (accessor on Merchant)
        $uniqueStates = (int) ($merchant->total_states_count ?? 0);

        return sendResponse("Merchant KPI data", [
            'total_picked_and_extra_shipments' => $totalPickedAndExtra,
            'total_registered_shipments' => $totalRegistered,
            'total_extra_shipments_no' => $totalExtra,
            'registered_to_extra_percentage' => $percentage,
            'total_unique_states' => $uniqueStates,
        ]);
    }
}
