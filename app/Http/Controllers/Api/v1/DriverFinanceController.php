<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\DriverShipmentAssignment;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Driver App
 * 
 * Driver finance management for mobile app
 */
class DriverFinanceController extends Controller
{
    /**
     * Get Unsettled Shipments
     *
     * Retrieve shipments with outstanding financial settlements for the authenticated driver.
     * Shows delivered shipments that haven't been financially settled yet.
     *
     * @OA\Post(
     *     path="/driver/finance/not_settled_shipments",
     *     summary="Get unsettled shipments",
     *     description="Retrieve shipments with outstanding financial settlements including commission calculations",
     *     operationId="getUnseettledShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="from_date", type="string", format="date", example="2024-12-01", description="Start date filter"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2024-12-31", description="End date filter")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Unsettled shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivered shipments with commissions"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_shipments", type="integer", example=15),
     *                 @OA\Property(property="total_commission", type="number", format="float", example=450.75),
     *                 @OA\Property(property="shipments", type="array", @OA\Items(ref="#/components/schemas/DriverShipment"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function not_settled_shipments(Request $request)
    {
        try {
            $driverId = Auth::id();
            $query = DriverShipmentAssignment::with(['shipment.shipment_finance', 'shipment.invoice_shipment'])
                ->where('driver_id', $driverId)
                ->whereNotNull('delivered_at')
                ->whereHas('shipment', function ($q) {
                    $q->whereHas('invoice_shipment', function ($q2) {
                        $q2->where('status', 'pending');
                    });
                })
                ->whereHas('shipment', function ($q) {
                    $q->whereHas('shipment_finance', function ($financeQuery) {
                        $financeQuery->whereNotNull('driver_delivery_fee')
                            ->where('driver_delivery_fee', '>', 0);
                    });
                });

            // Date filtering
            if ($request->has('from_date') || $request->has('to_date')) {
                $dates = $request->validate([
                    'from_date' => 'nullable|date',
                    'to_date' => 'nullable|date|after_or_equal:from_date'
                ]);
                $query->when($request->from_date, function ($q) use ($request) {
                    $q->whereDate('delivered_at', '>=', Carbon::parse($request->from_date));
                })
                    ->when($request->to_date, function ($q) use ($request) {
                        $q->whereDate('delivered_at', '<=', Carbon::parse($request->to_date));
                    });
            } else {
                $query->whereDate('delivered_at', Carbon::today());
            }

            $shipments = $query->get()->map(function ($assignment) {
                $shipment = $assignment->shipment;

                return [
                    'assignment' => $assignment,
                ];
            });

            return sendResponse(
                'Delivered shipments with commissions',
                [
                    'total_shipments' => $shipments->count(),
                    'shipments' => $shipments,
                    'total_commission' => $shipments->sum('assignment.shipment.shipment_finance.driver_delivery_fee')
                ],
                true
            );
        } catch (Exception $e) {
            return sendResponse(
                'Error retrieving delivered shipments',
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }

    /**
     * Get Settled Shipments
     *
     * Retrieve shipments with completed financial settlements for the authenticated driver.
     * Shows delivered shipments that have been financially processed and settled.
     *
     * @OA\Post(
     *     path="/driver/finance/settled_shipments",
     *     summary="Get settled shipments",
     *     description="Retrieve shipments with completed financial settlements including commission details",
     *     operationId="getSettledShipments",
     *     tags={"Driver App"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="from_date", type="string", format="date", example="2024-12-01", description="Start date filter"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2024-12-31", description="End date filter")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settled shipments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivered shipments with commissions"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_shipments", type="integer", example=25),
     *                 @OA\Property(property="total_commission", type="number", format="float", example=750.25),
     *                 @OA\Property(property="shipments", type="array", @OA\Items(ref="#/components/schemas/DriverShipment"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function settled_shipments(Request $request)
    {
        try {
            $driverId = Auth::id();
            $query = DriverShipmentAssignment::with(['shipment.shipment_finance', 'shipment.invoice_shipment', 'shipment.shipment_fine'])
                ->where('driver_id', $driverId)
                ->whereNotNull('delivered_at')
                ->whereHas('shipment', function ($q) {
                    $q->whereHas('invoice_shipment', function ($q2) {
                        $q2->where('status', 'settled');
                    });
                })
                ->whereHas('shipment', function ($q) {
                    $q->whereHas('shipment_finance', function ($financeQuery) {
                        $financeQuery->whereNotNull('driver_delivery_fee')
                            ->where('driver_delivery_fee', '>', 0);
                    });
                });

            // Date filtering
            if ($request->has('from_date') || $request->has('to_date')) {
                $dates = $request->validate([
                    'from_date' => 'nullable|date',
                    'to_date' => 'nullable|date|after_or_equal:from_date'
                ]);
                $query->when($request->from_date, function ($q) use ($request) {
                    $q->whereDate('delivered_at', '>=', Carbon::parse($request->from_date));
                })
                    ->when($request->to_date, function ($q) use ($request) {
                        $q->whereDate('delivered_at', '<=', Carbon::parse($request->to_date));
                    });
            } else {
                $query->whereDate('delivered_at', Carbon::today());
            }

            $shipments = $query->paginate(8)->map(function ($assignment) {
                $shipment = $assignment->shipment;

                return [
                    'assignment' => $assignment,
                ];
            });

            return sendResponse(
                'Delivered shipments with commissions',
                [
                    'total_shipments' => $shipments->count(),
                    'shipments' => $shipments,
                    'total_commission' => $shipments->sum('assignment.shipment.shipment_finance.driver_delivery_fee')
                ],
                true
            );
        } catch (Exception $e) {
            return sendResponse(
                'Error retrieving delivered shipments',
                [],
                false,
                [$e->getMessage()],
                500
            );
        }
    }
}

