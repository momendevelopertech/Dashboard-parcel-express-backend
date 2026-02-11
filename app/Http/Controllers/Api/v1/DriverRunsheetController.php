<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\GeneralResource;
use App\Models\DriverRunsheet;
use App\Models\ShipmentHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Fleet & Driver Management", description="API endpoints for managing fleet and driver data")
 * @OA\Server(url="http://localhost:8000/api")
 */
class DriverRunsheetController extends Controller
{
    /**
     * @OA\Post(
     *     path="/driver_runsheet",
     *     summary="Get Driver Runsheets",
     *     description="Retrieves a list of driver runsheets based on provided filters.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="driver",
     *         in="query",
     *         description="Filter by driver ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Filter by date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Runsheets retrieved successfully",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {
        $runsheets = DriverRunsheet::query();
        $isActuallyFilled = function ($val) {
            return !in_array($val, [null, '', 'undefined'], true);
        };
        $driver_id = $request->input('driver');
        if ($isActuallyFilled($driver_id)) {
            $runsheets->where('driver_id', $driver_id);
        }
        $createdAtFrom = $request->input('created_at_from');
        $createdAtTo = $request->input('created_at_to');

        if ($isActuallyFilled($createdAtFrom)) {
            $runsheets->where('created_at', '>=', Carbon::parse($createdAtFrom)->startOfSecond());
        }

        if ($isActuallyFilled($createdAtTo)) {
            $runsheets->where('created_at', '<=', Carbon::parse($createdAtTo)->endOfSecond());
        }

        if (!$isActuallyFilled($createdAtFrom) && !$isActuallyFilled($createdAtTo)) {
            $date = $request->input('date');
            if ($isActuallyFilled($date)) {
                $runsheets->whereDate('created_at', Carbon::parse($date));
            }
        }

        $perPage = $request->query('per_page', 10);

        $runsheets = $runsheets->with(
            'invoice.invoice_shipments.shipment_finance',
            'driver',
            'assigned_shipments.shipment.consignee.governorate',
            'assigned_shipments.shipment.consignee.state',
            'assigned_shipments.shipment.consignee.place',
            'delivered_shipments.shipment',
            'not_delivered_shipments.shipment',
            'returned_shipments.shipment',
            'holding_shipments.shipment'
        )->withCount('assigned_shipments')->paginate($perPage);

        // Override amount with driver collectible to avoid frontend changes
        $runsheets->getCollection()->each(function ($rs) {
            $relations = ['assigned_shipments', 'delivered_shipments', 'not_delivered_shipments', 'returned_shipments', 'holding_shipments'];
            foreach ($relations as $rel) {
                $rs->$rel->each(function ($runsheetShipment) {
                    if ($runsheetShipment->shipment) {
                        $collectible = (float) ($runsheetShipment->shipment->getDriverCollectibleAmount() ?? 0);
                        $runsheetShipment->shipment->setAttribute('total_cod', $collectible);
                    }
                });
            }
        });

        // Send FCM notification for unconfirmed shipments if driver_id is provided
        // if ($isActuallyFilled($driver_id)) {
        //     try {
        //         $runsheetService = resolve(\App\Services\DriverRunsheetNotificationService::class);
        //         $runsheetService->notifyDriverUnconfirmedShipments($driver_id);
        //     } catch (\Throwable $e) {
        //         \Log::error('Runsheet FCM notification failed: ' . $e->getMessage());
        //     }
        // }

        return sendResponse("Runsheets retrieved successfully.", new GeneralResource($runsheets));
    }

    /**
     * @OA\Post(
     *     path="/driver_runsheet/hold/{id}",
     *     summary="Hold Driver Runsheet",
     *     description="Changes the status of a driver runsheet to 'holding'.",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the driver runsheet",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver runsheet status is holding",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Driver runsheet not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function hold(Request $request)
    {
        try {
            $driver_runsheet = DriverRunsheet::findOrFail($request->id);
            $driver_runsheet->status = "holding";
            $driver_runsheet->save();
            $data = "Driver runsheet status is holding";
            return sendResponse("success", $data, true, [], 200);
        } catch (\Exception $exception) {
            return sendResponse("error", $exception->getMessage(), false, [], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/driver_runsheet/{runsheetId}/shipments/{type}",
     *     summary="Get shipments by type for a runsheet",
     *     description="Retrieves shipments of a specific type for a given runsheet",
     *     tags={"Fleet & Driver Management"},
     *     @OA\Parameter(
     *         name="runsheetId",
     *         in="path",
     *         description="ID of the driver runsheet",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         description="Type of shipments (assigned, delivered, not_delivered, returned, holding, to_sign)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Shipments retrieved successfully"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getShipmentsByType($runsheetId, $type)
    {
        $runsheet = DriverRunsheet::findOrFail($runsheetId);
        $relationship = match ($type) {
            'assigned' => 'assigned_shipments',
            'delivered' => 'delivered_shipments',
            'not_delivered' => 'not_delivered_shipments',
            'returned' => 'returned_shipments',
            'holding' => 'holding_shipments',
            'to_sign' => 'assigned_shipments',
            default => 'assigned_shipments'
        };
        $shipments = $runsheet->$relationship()->with('shipment')->get();
        if ($type === 'to_sign') {
            $deliveredShipments = $runsheet->delivered_shipments()->pluck('shipment_tracking_no');
            $shipments = $shipments->filter(function ($shipment) use ($deliveredShipments) {
                return !$deliveredShipments->contains($shipment->shipment_tracking_no);
            });
        }
        $formattedShipments = $shipments->map(function ($runsheetShipment) {
            $amount = $runsheetShipment->shipment ? (float) ($runsheetShipment->shipment->getDriverCollectibleAmount() ?? 0) : 0;

            // Get delivery proof from shipment_histories where name == 'DELIVERED'
            $deliveryProof = null;
            if ($runsheetShipment->shipment) {
                $deliveryHistory = ShipmentHistory::where('shipment_id', $runsheetShipment->shipment->id)
                    ->where('name', 'DELIVERED')
                    ->orderBy('created_at', 'desc')
                    ->first();
                $deliveryProof = isset($deliveryHistory->proof) &&$runsheetShipment->shipment->payment_type=="paid" ? $deliveryHistory->proof : null;
            }

            $deliveryFee = $runsheetShipment->shipment ? (float) ($runsheetShipment->shipment->delivery_fee ?? 0) : 0;

            return [
                'tracking_no' => $runsheetShipment->shipment_tracking_no,
                'status' => $runsheetShipment->shipment->status,
                'total_cod' => $amount,
                'delivery_fee' => $deliveryFee,
                'payment_type' => $runsheetShipment->shipment->payment_type,
                'payment_method' => $runsheetShipment->payment_method ?? $runsheetShipment->shipment->payment_type,
                'proof' => $deliveryProof,
                'shipment_data' => $runsheetShipment->shipment
            ];
        });

        return sendResponse("Shipments retrieved successfully.", $formattedShipments);
    }
}
