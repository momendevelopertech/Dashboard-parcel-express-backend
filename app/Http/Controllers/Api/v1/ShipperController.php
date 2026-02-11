<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreShipperRequest;
use App\Http\Requests\UpdateShipperRequest;
use App\Http\Resources\ShipperResource;
use App\Models\Account;
use App\Models\Scopes\ShipperScope;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Models\Shipper;
use App\Models\ShipperCommission;
use App\Models\ShipperSetting;
use App\Models\State;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System related endpoints"
 * )
 */
class ShipperController extends Controller
{
    /**
     * List shippers
     *
     * @OA\Get(
     *   path="/shippers",
     *   tags={"WMS"},
     *   summary="Get paginated list of shippers with search",
     *   description="Get a list of shippers with optional search query",
     *   operationId="getShippersList",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="query",
     *     in="query",
     *     description="Search term for shipper name",
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
     *       @OA\Property(property="message", type="string", example="Shippers retrieved successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="setting", type="object"),
     *             @OA\Property(property="country", type="object"),
     *             @OA\Property(property="state", type="object"),
     *             @OA\Property(property="governorate", type="object"),
     *             @OA\Property(property="place", type="object"),
     *             @OA\Property(property="owner", type="object"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function index()
    {
        $perPage = request()->query('per_page', 8);
        $shippers = Shipper::query();
        if (request()->has(key: 'query')) {
            $query = request()->input('query');
            $shippers = $shippers
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                ->with("setting", "country", "state", "governorate", "place", "owner")
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $shippers = $shippers->with("setting", "country", "state", "governorate", "place", "owner")->orderBy('id', 'desc')->paginate($perPage);
        }
        return sendResponse("Shippers reterived successfully.", new ShipperResource($shippers), []);
    }

    /**
     * Create shipper
     *
     * @OA\Post(
     *   path="/shippers/store",
     *   tags={"WMS"},
     *   summary="Create a new shipper",
     *   description="Create a new shipper with specified details",
     *   operationId="createShipper",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Shipper creation data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "name",
     *         "contact",
     *         "country_id",
     *         "state_id",
     *         "failed_ofd_count",
     *         "rto_days"
     *       },
     *       @OA\Property(property="name", type="string", example="Shipper Name", maxLength=255),
     *       @OA\Property(property="email", type="string", format="email", example="shipper@example.com"),
     *       @OA\Property(property="contact", type="string", example="+1234567890", maxLength=20),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="address", type="string", maxLength=255),
     *       @OA\Property(property="zip_code", type="string", maxLength=10),
     *       @OA\Property(property="website", type="string", format="uri", example="https://example.com"),
     *       @OA\Property(property="notes", type="string", maxLength=1000),
     *       @OA\Property(property="is_active", type="boolean", example=true),
     *       @OA\Property(property="failed_ofd_count", type="integer", example=3),
     *       @OA\Property(property="rto_days", type="integer", example=7)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipper created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipper created successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string", format="email"),
     *         @OA\Property(property="contact", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="address", type="string"),
     *         @OA\Property(property="zip_code", type="string"),
     *         @OA\Property(property="website", type="string", example="https://example.com"),
     *         @OA\Property(property="notes", type="string"),
     *         @OA\Property(property="is_active", type="boolean"),
     *         @OA\Property(property="failed_ofd_count", type="integer"),
     *         @OA\Property(property="rto_days", type="integer"),
     *         @OA\Property(property="setting", type="object"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error occurred while creating shipper."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function store(StoreShipperRequest $request)
    {
        $request->validated();
        DB::beginTransaction();
        try {
            $data = $request->all();

            // Split contact numbers
            if ($data['contact']) {
                $contactSplit = splitPhoneNumber($data['contact']);
                $data['country_key_contact'] = $contactSplit['country_code'];
                $data['contact'] = $contactSplit['national_number'];
            }
            if ($data['alternative_contact']) {
                $alternativeSplit = splitPhoneNumber($data['alternative_contact']);
                $data['alternative_country_key_contact'] = $alternativeSplit['country_code'];
                $data['alternative_contact'] = $alternativeSplit['national_number'];
            }

            $shipper = Shipper::create($data);
            ShipperSetting::create([
                'shipper_id' => $shipper->id,
                'rto_days' => $request->rto_days,
                'failed_ofd_count' => $request->failed_ofd_count,
            ]);

            Account::create([
                "accountable_id" => $shipper->id,
                "accountable_type" => Shipper::class,
            ]);
            $stateIds = State::pluck('id');
            $defaultShipperCommission = Setting::where('key', 'default_shipper_commission')->first()->value ?? 1;
            foreach ($stateIds as $stateId) {
                ShipperCommission::updateOrCreate(
                    [
                        'shipper_id' => $shipper->id,
                        'state_id'   => $stateId,
                    ],
                    [
                        'delivery_fee' => $defaultShipperCommission,
                    ]
                );
            }
            activityLog('Shipper created',"new shipper created called {$shipper->name}");
            DB::commit();
            return sendResponse("Shipper created successfully.", new ShipperResource($shipper));
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error occurred while creating shipper.", [], false, [$e->getMessage()], 422);
        }
    }


    /**
     * Update shipper
     *
     * @OA\Post(
     *   path="/shippers/update",
     *   tags={"WMS"},
     *   summary="Update shipper details",
     *   description="Update an existing shipper's details",
     *   operationId="updateShipper",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Shipper update data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={
     *         "id",
     *         "name",
     *         "contact"
     *       },
     *       @OA\Property(property="id", type="integer", format="int64", example=1),
     *       @OA\Property(property="name", type="string", example="Updated Shipper Name", maxLength=255),
     *       @OA\Property(property="email", type="string", format="email", example="shipper@example.com"),
     *       @OA\Property(property="contact", type="string", example="+1234567890", maxLength=20),
     *       @OA\Property(property="country_id", type="integer", example=1),
     *       @OA\Property(property="state_id", type="integer", example=1),
     *       @OA\Property(property="city_id", type="integer", example=1),
     *       @OA\Property(property="address", type="string", maxLength=255),
     *       @OA\Property(property="zip_code", type="string", maxLength=10),
     *       @OA\Property(property="website", type="string", format="uri", example="https://example.com"),
     *       @OA\Property(property="notes", type="string", maxLength=1000),
     *       @OA\Property(property="is_active", type="boolean", example=true)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipper updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipper updated successfully."),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", format="int64"),
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="email", type="string", format="email"),
     *         @OA\Property(property="contact", type="string"),
     *         @OA\Property(property="country_id", type="integer"),
     *         @OA\Property(property="state_id", type="integer"),
     *         @OA\Property(property="city_id", type="integer"),
     *         @OA\Property(property="address", type="string"),
     *         @OA\Property(property="zip_code", type="string"),
     *         @OA\Property(property="website", type="string", example="https://example.com"),
     *         @OA\Property(property="notes", type="string"),
     *         @OA\Property(property="is_active", type="boolean"),
     *         @OA\Property(property="setting", type="object"),
     *         @OA\Property(property="created_at", type="string", format="date-time"),
     *         @OA\Property(property="updated_at", type="string", format="date-time")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Error occurred while updating shipper."),
     *       @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *     )
     *   )
     * )
     */
    public function update(UpdateShipperRequest $request)
    {
        try {
            $shipper = Shipper::findOrFail($request->id);
            $data = $request->all();
            // Split contact numbers
            if ($data['contact']) {
                $contactSplit = splitPhoneNumber($data['contact']);
                $data['country_key_contact'] = $contactSplit['country_code'];
                $data['contact'] = $contactSplit['national_number'];
            }
            if ($data['alternative_contact']) {
                $alternativeSplit = splitPhoneNumber($data['alternative_contact']);
                $data['alternative_country_key_contact'] = $alternativeSplit['country_code'];
                $data['alternative_contact'] = $alternativeSplit['national_number'];
            }

            $shipper->update($data);
            $data['shipper_id'] = $shipper->id;
            $shipper->setting->update($data);
            activityLog('Shipper updated',"shipper updated called {$shipper->name}");
            return sendResponse("Shipper updated successfully.", new ShipperResource($shipper));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while updating shipper.", [], [$e->getMessage()], 422);
        }
    }


    /**
     * Delete shipper
     *
     * @OA\Post(
     *   path="/shippers/delete",
     *   tags={"WMS"},
     *   summary="Delete a shipper",
     *   description="Delete a shipper by its ID",
     *   operationId="deleteShipper",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\RequestBody(
     *     description="Shipper deletion data",
     *     required=true,
     *     @OA\JsonContent(
     *       required={"id"},
     *       @OA\Property(property="id", type="integer", format="int64")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipper deleted successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipper deleted successfully."),
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
     *     description="Validation error",
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
           $shipper = Shipper::findOrFail($request->id);
           $shipper->delete();
           activityLog('Shipper deleted',"shipper deleted called {$shipper->name}");
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Shipper deleted successfully.", []);
    }

    /**
     * Get all shippers
     *
     * @OA\Get(
     *   path="/shippers/all",
     *   tags={"WMS"},
     *   summary="Get all shippers",
     *   description="Retrieve all shippers without pagination",
     *   operationId="getAllShippers",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Response(
     *     response=200,
     *     description="Shippers retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shippers"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *             @OA\Property(property="id", type="integer", format="int64"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="setting", type="object"),
     *             @OA\Property(property="country", type="object"),
     *             @OA\Property(property="state", type="object"),
     *             @OA\Property(property="governorate", type="object"),
     *             @OA\Property(property="place", type="object"),
     *             @OA\Property(property="owner", type="object"),
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
        $shippers = Shipper::query();
        return sendResponse("Shippers", new ShipperResource($shippers->get()));
    }

    /**
     * Get single shipper details
     *
     * @OA\Get(
     *   path="/shippers/getSingle",
     *   tags={"WMS"},
     *   summary="Get shipper details with states",
     *   description="Get detailed information about a specific shipper including available states",
     *   operationId="getShipperDetails",
     *   security={
     *     {"bearerAuth": {}}
     *   },
     *   @OA\Parameter(
     *     name="shipper_id",
     *     in="query",
     *     description="Shipper ID to fetch",
     *     required=true,
     *     @OA\Schema(
     *         type="integer"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Shipper retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Shipper retrieved successfully."),
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
     *     description="Shipper not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Shipper not found."),
     *       @OA\Property(property="success", type="boolean", example=false)
     *     )
     *   )
     * )
     */
    public function getSingle(Request $request)
    {
        $request->validate([
            'shipper_id' => 'required',
        ]);

        $shipperId = $request->input('shipper_id');

        $shipper = Shipper::find($shipperId);

        if (!$shipper) {
            return sendResponse("Shipper not found.", [], 404);
        }

        $shipper['states'] = State::where('country_id', 165)->get();

        return sendResponse("Shipper retrieved successfully.", $shipper, []);
    }

    public function updateDefaultShipperCommission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'value'       => 'required',
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
        $setting = Setting::where('key', 'default_shipper_commission')->first();
        if (!$setting) {
            return sendResponse("No Setting Found.", null, [], 404);
        }
        $setting->value = $request->value;
        $setting->save();
        return sendResponse("Default shipper commission updated successfully.", $setting);
    }

    public function getDefaultShipperCommission()
    {
        $setting = Setting::where('key', 'default_shipper_commission')->first();
        if (!$setting) {
            return sendResponse("No Setting Found.", null, [], 404);
        }
        return sendResponse("Default shipper commission retrieved successfully.", $setting);
    }
}
