<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Address;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Shipment;
use App\Models\Consignee;
use App\Models\OldAddress;
use App\Models\Country;
use App\Models\State;
use App\Models\Governorate;
use App\Models\ShipmentAddressRevision;
use App\Models\Place;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Other", description="Address update management")
 * @OA\Controller(description="Manages address update requests.")
 */
class AddressController extends Controller
{
    /**
     * @OA\Get(
     *     path="/address-updates",
     *     summary="Get all address update requests",
     *     description="Retrieves a list of address update requests with pagination and filtering options.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending, approved, rejected)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter by creation date (from)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter by creation date (to)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by consignee name, cellphone, or tracking number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address update requests retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving address updates."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/address-updates",
     *     summary="Get address update requests with filters",
     *     tags={"Address Updates"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address updates retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->integer('per_page', 10);

            $query = ShipmentAddressRevision::with([
                'shipment:id,tracking_no,status,delivery_address_id,consignee_id,created_at',
                // العميل على مستوى الأوردر
                'shipment.consignee:id,name,country_key_cellphone,cellphone',
                // العنوان الحالي للأوردر (لو عندك relation)
                'shipment.deliveryAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,streetAddress,latitude,longitude,location_url',
                'shipment.deliveryAddress.country:id,name',
                'shipment.deliveryAddress.governorate:id,en_name,ar_name',
                'shipment.deliveryAddress.state:id,en_name,ar_name',
                'shipment.deliveryAddress.place:id,en_name,ar_name',
                'shipment.deliveryAddress.city:id,name',
                'oldAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,streetAddress,latitude,longitude,location_url',
                'oldAddress.country:id,name',
                'oldAddress.governorate:id,en_name,ar_name',
                'oldAddress.state:id,en_name,ar_name',
                'oldAddress.place:id,en_name,ar_name',
                'oldAddress.city:id,name',
                'oldAddress.consignee:id,name,country_key_cellphone,cellphone',

                'newAddress:id,consignee_id,country_id,governorate_id,state_id,place_id,city_id,streetAddress,latitude,longitude,location_url',
                'newAddress.country:id,name',
                'newAddress.governorate:id,en_name,ar_name',
                'newAddress.state:id,en_name,ar_name',
                'newAddress.place:id,en_name,ar_name',
                'newAddress.city:id,name',
                'newAddress.consignee:id,name,country_key_cellphone,cellphone',

                'changer:id,name',
                'approver:id,name',
            ])
                ->orderByDesc('id');

            if ($request->filled('status')) {
                if ($request->status === 'pending') {
                    $query->where('approved', false)->where('rejected', false);
                } elseif ($request->status === 'approved') {
                    $query->where('approved', true);
                } elseif ($request->status === 'rejected') {
                    $query->where('rejected', true);
                }
            } else {
                $query->where('approved', false)->where('rejected', false);
            }

            // بحث
            if ($request->filled('search')) {
                $term = '%' . $request->search . '%';
                $query->where(function ($q) use ($term) {
                    $q->whereHas('shipment', fn($oq) => $oq->where('tracking_no', 'LIKE', $term))
                        ->orWhereHas('newAddress', fn($aq) => $aq->where('streetAddress', 'LIKE', $term));
                });
            }

            $rows = $query->paginate($perPage);

            return sendResponse("Address revisions retrieved successfully.", $rows);

        } catch (\Throwable $e) {
            return sendResponse("Failed to fetch revisions.", [], false, [$e->getMessage()], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/address-updates/{id}/approve",
     *     summary="Approve address update request",
     *     description="Approves an address update request and updates the consignee's address.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the address update request",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="comments",
     *                     type="string",
     *                     description="Optional comments",
     *                     maxLength=500
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address update approved successfully."
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Address update already approved or invalid request."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error approving address update."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function approve(Request $request, $revisionId)
    {
        $data = $request->validate([
            'deactivate_previous' => 'sometimes|boolean', // default: true
            'comments' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            /** @var ShipmentAddressRevision $rev */
            $rev = ShipmentAddressRevision::with(['shipment', 'oldAddress', 'newAddress'])->findOrFail($revisionId);

            if ($rev->approved) {
                return sendResponse("Already approved.", [], false, ["Revision already approved."], 400);
            }
            if ($rev->rejected) {
                return sendResponse("Already rejected.", [], false, ["Revision already rejected."], 400);
            }

            $new = $rev->newAddress;
            $old = $rev->oldAddress;
            if (!$new) {
                return sendResponse("New address record not found.", [], false, ["Missing new address."], 422);
            }

            // فعّل العنوان الجديد
            $new->approved = true;
            $new->is_active = true;
            $new->save();

            // عطّل العنوان القديم (اختياري)
            $deactivatePrev = $request->boolean('deactivate_previous', true);
            if ($deactivatePrev && $old && $old->consignee_id === $new->consignee_id) {
                $old->is_active = false;
                $old->save();
            }

            // اربط الأوردر بالعنوان الجديد (لو فيه أوردر)
            if ($rev->shipment) {
                $rev->shipment->delivery_address_id = $new->id;
                $rev->shipment->save();

                shipmentHistory([
                    "shipment_id" => $rev->shipment->id,
                    "status" => "ADDRESS_UPDATE_APPROVED",
                    "description" => $request->input('comments', 'Consignee address update approved.'),
                    "time" => now(),
                ]);
            }

            // حدّث حالة الـrevision
            $rev->approved = true;
            $rev->approved_at = now();
            $rev->approved_by = Auth::id();
            $rev->reason = $rev->reason ?: $request->input('comments');
            $rev->save();
            activityLog("address_update_approved", "Address update approved for shipment #{$rev->shipment?->id} with tracking#{$rev->shipment?->tracking_no}");

            DB::commit();

            return sendResponse("Address revision approved successfully.", $rev->load([
                'shipment:id,tracking_no,status,delivery_address_id',
                'newAddress.country:id,name',
                'newAddress.governorate:id,en_name,ar_name',
                'newAddress.state:id,en_name,ar_name',
                'newAddress.place:id,en_name,ar_name',
                'newAddress.city:id,name',
            ]));

        } catch (\Throwable $e) {
            DB::rollBack();
            return sendResponse("Approval failed.", [], false, [$e->getMessage()], 500);
        }
    }



    /**
     * @OA\Post(
     *     path="/address-updates/{id}/reject",
     *     summary="Reject address update request",
     *     description="Rejects an address update request.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the address update request",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="comments",
     *                     type="string",
     *                     description="Rejection comments",
     *                     required={"comments"},
     *                     maxLength=500
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address update rejected successfully."
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Address update already approved or rejected."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error rejecting address update."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'comments' => 'required|string|max:500'
        ]);
        try {
            $pendingAddress = Address::with('consignee')->findOrFail($id);
            if ($pendingAddress->approved) {
                return sendResponse("Address update already approved.", [], false, ["This address update has already been approved."], 400);
            }
            if ($pendingAddress->rejected) {
                return sendResponse("Address update already rejected.", [], false, ["This address update has already been rejected."], 400);
            }
            $pendingAddress->update([
                'approved' => false,
                'rejected' => true,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'comments' => $request->comments
            ]);
            activityLog("address_update_rejected", "Address update rejected for consignee #{$pendingAddress->consignee->id}");
            return sendResponse("Address update rejected successfully.", $pendingAddress->load('consignee'));
        } catch (\Exception $e) {
            return sendResponse("Error rejecting address update.", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/address-updates/{id}",
     *     summary="Get address update request details",
     *     description="Retrieves details for a specific address update request.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the address update request",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address update details retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving address update details."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        try {
            $addressUpdate = OldAddress::with([
                'consignee' => function ($q) {
                    $q->with([
                        'country',
                        'governorate',
                        'state',
                        'place',
                        'shipments' => function ($shipmentQ) {
                            $shipmentQ->latest()->limit(1);
                        }
                    ]);
                },
                'country',
                'governorate',
                'state',
                'place'
            ])->findOrFail($id);

            return sendResponse("Address update details retrieved successfully.", $addressUpdate);
        } catch (\Exception $e) {
            return sendResponse("Error retrieving address update details.", [], false, [$e->getMessage()], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/address-updates/stats",
     *     summary="Get address update statistics",
     *     description="Retrieves statistics on address update requests.",
     *     tags={"Other"},
     *     @OA\Response(
     *         response=200,
     *         description="Address update statistics retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error retrieving statistics."
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function stats()
    {
        try {
            $stats = [
                'total_requests' => OldAddress::count(),
                'pending_requests' => OldAddress::where('approved', false)->whereNull('rejected')->count(),
                'approved_requests' => OldAddress::where('approved', true)->count(),
                'rejected_requests' => OldAddress::where('rejected', true)->count(),
                'today_requests' => OldAddress::whereDate('created_at', today())->count(),
                'this_week_requests' => OldAddress::whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count(),
            ];
            return sendResponse("Address update statistics retrieved successfully.", $stats);
        } catch (\Exception $e) {
            return sendResponse("Error retrieving statistics.", [], false, [$e->getMessage()], 500);
        }
    }
    private function validateToken($tracking_no, $token)
    {
        try {
            $shipment = Shipment::where('tracking_no', $tracking_no)->first();
            if (!$shipment) {
                return response()->json(["message" => "Shipment not found"], 404);
            }
            $consignee = $shipment->consignee;
            if (!$consignee) {
                return response()->json(["message" => "Consignee not found"], 404);
            }
            if (!Hash::check($token, $consignee->update_token)) {
                return response()->json(["message" => "Invalid token. You cannot update the address."], 403);
            }
            if (now()->greaterThan($consignee->token_expires_at)) {
                return response()->json(["message" => "Token expired. You cannot update the address."], 403);
            }
            return true;
        } catch (QueryException $e) {
            return response()->json(["message" => "Error occurred while validating token"], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/address-updates",
     *     summary="Update consignee address",
     *     description="Submits a request to update the consignee's address. Requires a valid tracking number and token.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="path",
     *         description="Shipment tracking number",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Address update token",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="latitude",
     *                     type="string",
     *                     description="Latitude",
     *                     required={"latitude"}
     *                 ),
     *                 @OA\Property(
     *                     property="longitude",
     *                     type="string",
     *                     description="Longitude",
     *                     required={"longitude"}
     *                 ),
     *                 @OA\Property(
     *                     property="country_id",
     *                     type="integer",
     *                     description="Country ID",
     *                     required={"country_id"}
     *                 ),
     *                 @OA\Property(
     *                     property="state_id",
     *                     type="integer",
     *                     description="State ID",
     *                     required={"state_id"}
     *                 ),
     *                 @OA\Property(
     *                     property="governorate_id",
     *                     type="integer",
     *                     description="Governorate ID"
     *                 ),
     *                 @OA\Property(
     *                     property="place_id",
     *                     type="integer",
     *                     description="Place ID"
     *                 ),
     *                 @OA\Property(
     *                     property="street_address",
     *                     type="string",
     *                     description="Street address",
     *                     required={"street_address"}
     *                 ),
     *                 @OA\Property(
     *                     property="location",
     *                     type="string",
     *                     description="Location"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address update request submitted successfully."
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid token or token expired."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred while submitting address update request."
     *     )
     * )
     */
    public function update_address($tracking_no, $token, Request $request)
    {
        $request->merge(['tracking_no' => $tracking_no]);
        $request->merge(['token' => $token]);
        $request->validate([
            "tracking_no" => "required|exists:shipments,tracking_no",
            "token" => "required|string",
            "latitude" => "required",
            "longitude" => "required",
            "country_id" => "required|exists:countries,id",
            "state_id" => "required|exists:states,id",
            "governorate_id" => "nullable|exists:governorates,id",
            "place_id" => "nullable|exists:places,id",
            "street_address" => "required|string",
            "location" => "nullable|string"
        ]);
        DB::beginTransaction();
        try {
            $shipment = Shipment::where('tracking_no', $request->tracking_no)->first();
            if (!$shipment) {
                return response()->json(["message" => "Shipment not found"], 404);
            }
            $consignee = $shipment->consignee;
            if (!Hash::check($request->token, $consignee->update_token)) {
                return response()->json(["message" => "Invalid token"], 403);
            }
            if (now()->greaterThan($consignee->token_expires_at)) {
                return response()->json(["message" => "Token expired"], 403);
            }
            OldAddress::create([
                'consignee_id' => $consignee->id,
                'country_id' => $request->country_id,
                'state_id' => $request->state_id,
                'governorate_id' => $request->governorate_id,
                'place_id' => $request->place_id,
                'streetAddress' => $request->street_address,
                'longitude' => $request->longitude,
                'latitude' => $request->latitude,
                'location' => $request->location,
                'approved' => false, // Pending approval
            ]);
            $consignee->update([
                'update_token' => null,
                'token_expires_at' => null,
            ]);
            DB::commit();
            return response()->json(["message" => "Address update request submitted successfully. Your address will be updated after admin approval."]);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(["message" => "Error Occurred"], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/address-updates/governorates/{tracking_no}/{token}",
     *     summary="Get governorates",
     *     description="Retrieves a list of governorates. Requires a valid tracking number and token.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="path",
     *         description="Shipment tracking number",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Address update token",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Governorates retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid token or token expired."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred while retrieving governorates."
     *     )
     * )
     */
    public function governorates($tracking_no, $token)
    {
        $tokenValidation = $this->validateToken($tracking_no, $token);
        if ($tokenValidation !== true) {
            return $tokenValidation;
        }
        $governorates = Governorate::select('id', 'country_id', 'en_name', 'ar_name')->get();
        return response()->json($governorates);
    }
    /**
     * @OA\Get(
     *     path="/address-updates/states/{tracking_no}/{token}",
     *     summary="Get states",
     *     description="Retrieves a list of states. Requires a valid tracking number and token.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="path",
     *         description="Shipment tracking number",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Address update token",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="States retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid token or token expired."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred while retrieving states."
     *     )
     * )
     */
    public function states($tracking_no, $token)
    {
        $tokenValidation = $this->validateToken($tracking_no, $token);
        if ($tokenValidation !== true) {
            return $tokenValidation;
        }
        $states = State::select('id', 'country_id', 'governorate_id', 'en_name', 'ar_name')->get();
        return response()->json($states);
    }
    /**
     * @OA\Get(
     *     path="/address-updates/places/{tracking_no}/{token}",
     *     summary="Get places",
     *     description="Retrieves a list of places. Requires a valid tracking number and token.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="path",
     *         description="Shipment tracking number",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Address update token",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Places retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid token or token expired."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred while retrieving places."
     *     )
     * )
     */
    public function places($tracking_no, $token)
    {
        $tokenValidation = $this->validateToken($tracking_no, $token);
        if ($tokenValidation !== true) {
            return $tokenValidation;
        }
        $places = Place::select('id', 'state_id', 'en_name', 'ar_name')->get();
        return response()->json($places);
    }
    /**
     * @OA\Get(
     *     path="/address-updates/countries/{tracking_no}/{token}",
     *     summary="Get countries",
     *     description="Retrieves a list of countries. Requires a valid tracking number and token.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="path",
     *         description="Shipment tracking number",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Address update token",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Countries retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid token or token expired."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred while retrieving countries."
     *     )
     * )
     */
    public function countries($tracking_no, $token)
    {
        $tokenValidation = $this->validateToken($tracking_no, $token);
        if ($tokenValidation !== true) {
            return $tokenValidation;
        }
        $countries = Country::select('id', 'name')->get();
        return response()->json($countries);
    }
    /**
     * @OA\Get(
     *     path="/address-updates/shipment/{tracking_no}/{token}",
     *     summary="Get shipment details",
     *     description="Retrieves details for a specific shipment. Requires a valid tracking number and token.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="tracking_no",
     *         in="path",
     *         description="Shipment tracking number",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Address update token",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment details retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid token or token expired."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Shipment not found."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred while retrieving shipment details."
     *     )
     * )
     */
    public function shipment($tracking_no, $token)
    {
        $tokenValidation = $this->validateToken($tracking_no, $token);
        if ($tokenValidation !== true) {
            return $tokenValidation;
        }
        $shipment = Shipment::with([
            'consignee',
            'consignee.country',
            'consignee.state',
            'consignee.governorate',
            'consignee.place',
            'shipment_delivery'
        ])->where('tracking_no', $tracking_no)->first();
        return response()->json($shipment);
    }
}
