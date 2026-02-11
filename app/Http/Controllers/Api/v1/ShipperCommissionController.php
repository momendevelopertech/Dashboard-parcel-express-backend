<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\ShipperCommission;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="WMS", description="Shipper Commission Management")
 * @OA\Server(url="/api")
 */
class ShipperCommissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/shipper_commissions",
     *     summary="Get commissions for a shipper",
     *     description="Retrieves all commissions for a given shipper ID.",
     *     tags={"WMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="shipper_id",
     *         in="query",
     *         description="ID of the shipper",
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
     *         description="Shipper not found",
     *     )
     * )
     */
    public function index(Request $request)
    {
        $shipper_id = $request->shipper_id;

        $commissions = ShipperCommission::where('shipper_id', $shipper_id)
            ->with(['shipper', 'state.governorate'])
            ->join('states', 'shipper_commissions.state_id', '=', 'states.id')
            ->join('governorates', 'states.governorate_id', '=', 'governorates.id')
            ->orderBy('governorates.en_name')
            ->orderBy('states.en_name')
            ->select('shipper_commissions.*')
            ->get();

        return sendResponse("Commissions retrieved successfully.", $commissions->values(), []);
    }



    /**
     * @OA\Post(
     *     path="/shipper_commissions/store",
     *     summary="Create or update shipper commissions",
     *     description="Creates or updates commissions for a shipper.  Requires an array of commissions with state_id and delivery_fee.",
     *     tags={"WMS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shipper_id", type="integer", description="ID of the shipper", example=1),
     *             @OA\Property(
     *                 property="commissions",
     *                 type="array",
     *                 description="Array of commissions",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="state_id", type="integer", description="ID of the state"),
     *                     @OA\Property(property="delivery_fee", type="number", format="float", description="Delivery fee")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Commission saved successfully",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or database error",
     *     )
     * )
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validate([
                'shipper_id' => 'required|exists:shippers,id',
                'commissions' => 'required|array',
                'commissions.*.state_id' => 'required|exists:states,id',
                'commissions.*.delivery_fee' => 'required|numeric|min:0',
            ]);
            foreach ($data['commissions'] as $commissionData) {
                ShipperCommission::updateOrCreate(
                    [
                        'shipper_id' => $data['shipper_id'],
                        'state_id' => $commissionData['state_id'],
                    ],
                    [
                        'delivery_fee' => $commissionData['delivery_fee'],
                    ]
                );
            }
            DB::commit();
            return response()->json(["message" => "Commission saved successfully."], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(["error" => "Error occurred while saving commission.", "details" => $e->getMessage()], 422);
        }
    }

    public function byState($shipper_id, $state_id)
    {
        $fee = ShipperCommission::where('shipper_id', $shipper_id)
            ->where('state_id', $state_id)
            ->value('delivery_fee');
        return response()->json([
            'data' => ['delivery_fee' => $fee]
        ]);
    }
}
