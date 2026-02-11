<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\GeneralResource;
use App\Models\Account;
use App\Models\Shipment;
use App\Models\ShipmentFine;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Other", description="Shipment Fine Management")
 * @OA\Controller(description="Manage shipment fines.")
 */
class ShipmentFineController extends Controller
{
    /**
     * @OA\Get(
     *     path="/fines",
     *     summary="Get all shipment fines",
     *     description="Retrieve a list of shipment fines.  Optionally search by name using the 'query' parameter.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search query for fine name",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expenses retrieved successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $fines = ShipmentFine::query();
        $fines->with(["shipment", "driver"]);

        if (request()->has('query') && request()->input('query')) {
            $query = request()->input('query');
            $fines->whereHas('shipment', function ($q) use ($query) {
                $q->where('tracking_no', 'like', '%' . $query . '%');
            })->orWhereHas('driver', function ($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%');
            });
        }

        $fines = $fines->orderBy('id', 'desc')->paginate(8);
        return sendResponse("Expenses reterived successfully.", new GeneralResource($fines), []);
    }

    /**
     * @OA\Post(
     *     path="/fines/store",
     *     summary="Create a new shipment fine",
     *     description="Impose a fine on a driver.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipment_tracking_no", type="string", description="Shipment tracking number", example="12345"),
     *             @OA\Property(property="driver_id", type="integer", description="Driver ID", example=1),
     *             @OA\Property(property="amount", type="number", format="float", description="Fine amount", example=10.00),
     *             @OA\Property(property="notes", type="string", description="Notes (optional)", example="Speeding ticket")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fine imposed."
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validate([
                "shipment_tracking_no" => "required",
                "driver_id" => "required",
                "amount" => "required",
                "notes" => "nullable"
            ]);
            $data['created_by'] = Auth::id();
            $shipment = Shipment::where('tracking_no', $data['shipment_tracking_no'])->first();
            ShipmentFine::create($data);
            $driverAccount = Account::where('accountable_id', $data['driver_id'])
                ->where('accountable_type', User::class)
                ->first();
            if (!$driverAccount) {
                throw new Exception('Driver account not found.');
            }
            $facilityAccount = Account::where('accountable_id', Auth::user()->owner_id)
                ->where('accountable_type', Auth::user()->owner_type)
                ->first();
            $driverAccount->cash_balance -= $data['amount'];
            $facilityAccount->cash_balance += $data['amount'];
            $driverAccount->save();
            $facilityAccount->save();
            Transaction::create([
                'to_id'   => $data['driver_id'],
                'to_type' => User::class,
                'from_id'     => Auth::user()->owner_id,
                'from_type'   => Auth::user()->owner_type,
                'shipment_id'  => $shipment->id,
                'amount'    => $data['amount'],
                'type'      => 'fine',
            ]);
            activityLog('shipment fine created',"add fine for shipment with tracking number : {$shipment->tracking_no}");
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error.", [], false, [$e->getMessage()], 500);
        }
        return sendResponse("Fine imposed.", []);
    }

    /**
     * @OA\Post(
     *     path="/fines/delete",
     *     summary="Delete an shipment fine",
     *     description="Delete an existing shipment fine and refund the driver.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="ID of the fine to delete", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Fine deleted successfully."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error occurred."
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $fine = ShipmentFine::findOrFail($request->id);
            $driverAccount = Account::where('accountable_id', $fine->driver_id)
                ->where('accountable_type', User::class)
                ->first();
            if (!$driverAccount) {
                throw new Exception('Driver account not found.');
            }
            $facilityAccount = Account::where('accountable_id', Auth::user()->owner_id)
                ->where('accountable_type', Auth::user()->owner_type)
                ->first();
            if (!$facilityAccount) {
                throw new Exception('Facility account not found.');
            }
            $driverAccount->cash_balance += $fine->amount;
            $facilityAccount->cash_balance -= $fine->amount;
            $driverAccount->save();
            $facilityAccount->save();
            Transaction::create([
                'from_id'   => Auth::user()->owner_id,
                'from_type' => Auth::user()->owner_type,
                'to_id'     => $fine->driver_id,
                'to_type'   => User::class,
                'amount'    => $fine->amount,
                'type'      => 'reverse_fine',
            ]);
            $fine->delete();
            activityLog('shipment fine deleted',"delete fine for shipment with tracking number : {$fine->shipment->tracking_no}");
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return sendResponse("Error Occurred.", [], false, [$e->getMessage()], 422);
        }
        return sendResponse("Fine deleted successfully.", []);
    }
}
