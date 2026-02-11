<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\CalculationLogicService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API Controller for frontend to call calculation functions.
 * 
 * This allows the frontend to use the same calculation logic as the backend
 * without duplicating calculation code.
 */
class CalculationController extends Controller
{
    protected $calculationService;

    public function __construct(CalculationLogicService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * Calculate driver collectible amount for a shipment.
     * 
     * POST /api/v1/calculations/driver-collectible
     * 
     * Body: {
     *   "shipment_id": 123,
     *   // OR
     *   "shipment": {
     *     "payment_type": "COD",
     *     "fee_payer": "customer",
     *     "value": 50.000,
     *     "delivery_fee": 5.000,
     *     "base_delivery_fee": 5.000,
     *     "delivery_fee_before_discount": 5.000
     *   }
     * }
     */
    public function driverCollectible(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipment_id' => 'nullable|integer|exists:shipments,id',
            'shipment' => 'nullable|array',
            'shipment.payment_type' => 'nullable|string',
            'shipment.fee_payer' => 'nullable|string',
            'shipment.value' => 'nullable|numeric',
            'shipment.delivery_fee' => 'nullable|numeric',
            'shipment.base_delivery_fee' => 'nullable|numeric',
            'shipment.delivery_fee_before_discount' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $shipment = null;

        if ($request->has('shipment_id')) {
            $shipment = Shipment::find($request->shipment_id);
            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment not found'
                ], 404);
            }
        } elseif ($request->has('shipment')) {
            // Convert array to object for calculation service
            $shipment = (object) $request->shipment;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either shipment_id or shipment data is required'
            ], 422);
        }

        $amount = $this->calculationService->getDriverCollectibleAmount($shipment);

        return response()->json([
            'success' => true,
            'data' => [
                'amount' => $amount
            ]
        ]);
    }

    /**
     * Calculate merchant COD for a shipment.
     * 
     * POST /api/v1/calculations/merchant-cod
     */
    public function merchantCOD(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipment_id' => 'nullable|integer|exists:shipments,id',
            'shipment' => 'nullable|array',
            'shipment.payment_type' => 'nullable|string',
            'shipment.value' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $shipment = null;

        if ($request->has('shipment_id')) {
            $shipment = Shipment::find($request->shipment_id);
            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment not found'
                ], 404);
            }
        } elseif ($request->has('shipment')) {
            $shipment = (object) $request->shipment;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either shipment_id or shipment data is required'
            ], 422);
        }

        $amount = $this->calculationService->getMerchantCOD($shipment);

        return response()->json([
            'success' => true,
            'data' => [
                'amount' => $amount
            ]
        ]);
    }

    /**
     * Calculate total COD for a shipment.
     * 
     * POST /api/v1/calculations/total-cod
     */
    public function totalCOD(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipment_id' => 'nullable|integer|exists:shipments,id',
            'shipment' => 'nullable|array',
            'shipment.payment_type' => 'nullable|string',
            'shipment.fee_payer' => 'nullable|string',
            'shipment.value' => 'nullable|numeric',
            'shipment.delivery_fee' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $shipment = null;

        if ($request->has('shipment_id')) {
            $shipment = Shipment::find($request->shipment_id);
            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment not found'
                ], 404);
            }
        } elseif ($request->has('shipment')) {
            $shipment = (object) $request->shipment;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either shipment_id or shipment data is required'
            ], 422);
        }

        $amount = $this->calculationService->getTotalCOD($shipment);

        return response()->json([
            'success' => true,
            'data' => [
                'amount' => $amount
            ]
        ]);
    }

    /**
     * Calculate amount field for a shipment.
     * 
     * POST /api/v1/calculations/amount
     */
    public function amount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipment_id' => 'nullable|integer|exists:shipments,id',
            'shipment' => 'nullable|array',
            'shipment.payment_type' => 'nullable|string',
            'shipment.fee_payer' => 'nullable|string',
            'shipment.value' => 'nullable|numeric',
            'shipment.delivery_fee' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $shipment = null;

        if ($request->has('shipment_id')) {
            $shipment = Shipment::find($request->shipment_id);
            if (!$shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment not found'
                ], 404);
            }
        } elseif ($request->has('shipment')) {
            $shipment = (object) $request->shipment;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either shipment_id or shipment data is required'
            ], 422);
        }

        $amount = $this->calculationService->getAmount($shipment);

        return response()->json([
            'success' => true,
            'data' => [
                'amount' => $amount
            ]
        ]);
    }

    /**
     * Batch calculate for multiple shipments.
     * 
     * POST /api/v1/calculations/batch
     * 
     * Body: {
     *   "shipments": [
     *     { "payment_type": "COD", "fee_payer": "customer", "value": 50, "delivery_fee": 5 },
     *     { "payment_type": "COD", "fee_payer": "merchant", "value": 30, "delivery_fee": 3 }
     *   ],
     *   "calculation_type": "driver_collectible" // or "merchant_cod", "total_cod", "amount"
     * }
     */
    public function batch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipments' => 'required|array|min:1',
            'shipments.*.payment_type' => 'nullable|string',
            'shipments.*.fee_payer' => 'nullable|string',
            'shipments.*.value' => 'nullable|numeric',
            'shipments.*.delivery_fee' => 'nullable|numeric',
            'shipments.*.base_delivery_fee' => 'nullable|numeric',
            'shipments.*.delivery_fee_before_discount' => 'nullable|numeric',
            'calculation_type' => 'required|string|in:driver_collectible,merchant_cod,total_cod,amount',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $shipments = collect($request->shipments)->map(fn($s) => (object) $s);
        $calculationType = $request->calculation_type;

        $results = [];
        $total = 0.0;

        foreach ($shipments as $index => $shipment) {
            $amount = match ($calculationType) {
                'driver_collectible' => $this->calculationService->getDriverCollectibleAmount($shipment),
                'merchant_cod' => $this->calculationService->getMerchantCOD($shipment),
                'total_cod' => $this->calculationService->getTotalCOD($shipment),
                'amount' => $this->calculationService->getAmount($shipment),
                default => 0.0,
            };

            $results[] = [
                'index' => $index,
                'amount' => $amount
            ];

            $total += $amount;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $results,
                'total' => round($total, 3)
            ]
        ]);
    }
}

