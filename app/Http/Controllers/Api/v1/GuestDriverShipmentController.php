<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\DriverResource;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\ShipmentResource;
use App\Models\GuestShipment;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\AddressService;

/**
 * @OA\Tag(name="Other", description="Guest Driver Shipment Management")
 */
class GuestDriverShipmentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/guest-drivers-shipments",
     *     summary="Get a list of guest driver shipments",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for tracking number, customer name, or phone",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Start date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="End date for filtering (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="driver",
     *         in="query",
     *         description="Filter by driver ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Guest shipments retrieved successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = $request->input('query');
        $from = $request->input('from');
        $to = $request->input('to');
        $driver = $request->input('driver');
        $perPage = request()->query('per_page', 8);

        $shipments = GuestShipment::with(['driver.driver', 'governorate:id,en_name,ar_name,country_id', 'state:id,en_name,ar_name', 'place:id,en_name,ar_name', 'city:id,en_name,ar_name'])->whereHas('driver', function ($q) {
            $q->whereHas('driver', function ($q) {
                $q->withoutGlobalScopes()->where('is_guest', true);
            });
        });

        if (Auth::user()->hasRole('Guest Driver')) {
            $shipments = $shipments->where('driver_id', Auth::id());
        }

        // Filter by driver
        if ($driver) {
            $shipments = $shipments->where('driver_id', $driver);
        }

        // Filter by date range
        if ($from && $to) {
            $shipments = $shipments->whereBetween('created_at', [
                Carbon::parse($from),
                Carbon::parse($to)
            ]);
        }

        // Search functionality
        if ($query) {
            $shipments = $shipments->where(function ($q) use ($query) {
                $q->where('tracking_no', 'LIKE', '%' . $query . '%')
                    ->orWhere('customer_name', 'LIKE', '%' . $query . '%')
                    ->orWhere('customer_phone', 'LIKE', '%' . $query . '%')
                    ->orWhereHas('driver', function ($q) use ($query) {
                        $q->where('name', 'LIKE', '%' . $query . '%');
                    });
            })
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $shipments = $shipments
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        return sendResponse(
            "Guest shipments retrieved successfully.",
            new ShipmentResource($shipments)
        );
    }

    /**
     * @OA\Post(
     *     path="/guest-drivers-shipments",
     *     summary="Create a new guest driver shipment",
     *     tags={"Other"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_name", type="string", description="Customer's name"),
     *             @OA\Property(property="customer_phone", type="string", description="Customer's phone number"),
     *             @OA\Property(property="governorate_id", type="integer", description="Governorate ID"),
     *             @OA\Property(property="state_id", type="integer", description="State ID"),
     *             @OA\Property(property="place_id", type="integer", description="Place ID"),
     *             @OA\Property(property="city_id", type="integer", description="City ID"),
     *             @OA\Property(property="zipcode", type="string", description="Zip code"),
     *             @OA\Property(property="streetAddress", type="string", description="Street address"),
     *             @OA\Property(property="notes", type="string", description="Shipment notes"),
     *             @OA\Property(property="latitude", type="number", description="Latitude"),
     *             @OA\Property(property="longitude", type="number", description="Longitude"),
     *             @OA\Property(property="location_url", type="string", format="url", description="Location URL"),
     *             @OA\Property(property="payment_type", type="string", description="Payment type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipment created successfully",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            "customer_name" => "required|string",
            "customer_phone" => "required|string",
            "governorate_id" => "required|exists:governorates,id",
            "state_id" => "required|exists:states,id",
            "place_id" => "nullable|exists:places,id",
            "city_id" => "nullable|exists:cities,id",
            "zipcode" => "required|string",
            "streetAddress" => "required|string",
            "notes" => "nullable|string",
            "latitude" => "nullable|numeric",
            "longitude" => "nullable|numeric",
            "location_url" => "nullable|url",
            "payment_type" => "nullable|string",
        ]);

        DB::beginTransaction();

        try {
            $data = $request->all();
            // Parse location_url for coordinates if lat/lng not provided
            $addressService = new AddressService();
            $data['driver_id'] = Auth::id();
            $data['tracking_no'] = generate_tracking_no();

            if (empty($data['latitude']) || empty($data['longitude'])) {
                if (!empty($data['location_url'])) {
                    $parsed = $addressService->parseInputAddress($data['location_url']);
                    // Override streetAddress and set coordinates
                    $data['streetAddress'] = $parsed['streetAddress'];
                    $data['latitude'] = $parsed['latitude'];
                    $data['longitude'] = $parsed['longitude'];
                }
            }

            $shipment = GuestShipment::create($data);

            DB::commit();

            activityLog("guest_shipment_created", "Guest Shipment #{$shipment->tracking_no} created by guest driver");

            return sendResponse("Shipment created successfully.", new GeneralResource($shipment->load("driver")));
        } catch (QueryException $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Unexpected Error Occurred.", [], false, [$e->getMessage()], 500);
        }
    }
}
