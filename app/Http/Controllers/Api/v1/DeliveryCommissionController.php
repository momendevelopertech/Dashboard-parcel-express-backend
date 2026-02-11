<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Requests\StoreDeliveryCommissionRequest;
use App\Http\Resources\DeliveryCommissionResource;
use App\Models\DeliveryCommission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Controller managing delivery commission configurations
 * 
 * Handles driver compensation rules based on geographic locations. Features:
 * - Driver-specific commission rates
 * - State-based fee calculations
 * - Upsert operations for commission updates
 * - Bulk commission listing
 */
class DeliveryCommissionController extends Controller
{
    protected $driver_id = null;

    public function __construct()
    {
        $this->driver_id = request()->driver_id;
    }

    /**
     * Retrieve paginated commissions for current driver
     * 
     * @return \Illuminate\Http\JsonResponse
     *   - Paginated results (8/page)
     *   - Includes driver and state relationships
     *   - Scoped to request's driver_id parameter
     */
    public function index()
    {
        $commissions = DeliveryCommission::where('driver_id', $this->driver_id)->with('driver', 'state')->paginate(8);
        return sendResponse("Commissions reterived successfully.", new DeliveryCommissionResource($commissions), []);
    }

    /**
     * Create or update commission rate
     * 
     * @param StoreDeliveryCommissionRequest $request Validated commission data
     * @return \Illuminate\Http\JsonResponse
     *   - 200: Updated/created CommissionResource
     *   - 422: Validation/database errors
     * Unique Constraint: driver_id + state_id pairing
     */
    public function store(StoreDeliveryCommissionRequest $request)
    {
        try {
            $request->validated();
            $data = $request->all();

            $commission = DeliveryCommission::where('driver_id', $data['driver_id'])
                ->where('state_id', $data['state_id'])
                ->first();

            if ($commission) {
                $commission->update(['amount' => $data['amount']]);
            } else {
                $commission = DeliveryCommission::create($data);
            }

            return sendResponse("Commission saved successfully.", new DeliveryCommissionResource($commission));
        } catch (QueryException $e) {
            return sendResponse("Error occurred while saving Commission.", [], [$e->getMessage()], 422);
        }
    }

    /**
     * Delete commission configuration
     * 
     * @param Request $request Requires 'id' parameter
     * @return \Illuminate\Http\JsonResponse
     *   - 200: Empty success response
     *   - 422: Database constraint errors
     * Security: No ownership verification
     */
    public function delete(Request $request)
    {
        try {
            DeliveryCommission::where('id', $request->id)->delete();
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Commission deleted successfully.", []);
    }

    /**
     * Retrieve all commission presets
     * 
     * @return \Illuminate\Http\JsonResponse
     *   - Lightweight commission list (id, country_id, name)
     *   - Unpaginated results
     * Usage: Suitable for configuration selectors
     */
    public function all()
    {
        return sendResponse("Commissions", new DeliveryCommissionResource(DeliveryCommission::select("id", "country_id", "name")->get()));
    }
}
