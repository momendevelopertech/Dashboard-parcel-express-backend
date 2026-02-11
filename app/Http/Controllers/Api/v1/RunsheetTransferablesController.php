<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\DriverRunsheet;
use App\Models\ShipmentFeeAllocation;
use App\Models\TransferTaskShipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RunsheetTransferablesController extends Controller
{
    public function show($id)
    {
        $rs = DriverRunsheet::with(['delivered_shipments.shipment.shipment_delivery', 'delivered_shipments.shipment'])
            ->findOrFail($id);

        $senderWarehouseId = (int) (Auth::user()->owner_id ?? 0);

        $items = [];
        $totalAmountAndFeeRest = 0.0;

        foreach ($rs->delivered_shipments as $ro) {
            $ord = $ro->shipment;
            if (!$ord)
                continue;

            $od = $ord->shipment_delivery;
            $collected = (float) (($od->payment_cash ?? 0) + ($od->payment_bank_transfer ?? 0));
            if ($collected <= 0)
                continue;

            $F = (float) ($ord->delivery_fee ?? 0.0);

            $alloc = ShipmentFeeAllocation::with('others')
                ->where('shipment_tracking_no', $ord->tracking_no)
                ->first();

            $keptFee = $alloc ? $this->keptFeeForSender($alloc, $senderWarehouseId) : 0.0;

            $feeRest = max(0.0, $F - $keptFee);
            $transferAmount = round($collected, 3);
            $totalAmountAndFeeRest += $transferAmount + $feeRest;

            if ($transferAmount <= 0)
                continue;

            [$toType, $toId] = $this->resolveShipmentDestination($ord);

            $items[] = [
                'tracking_no' => $ord->tracking_no ?? null,
                'shipment_id' => $ord->id ?? null,
                'shipment_code' => $ord->code ?? null,
                'amount' => round($totalAmountAndFeeRest, 3),
                'cod' => round($collected, 3),
                'kept_fee' => round($keptFee, 3),
                'fee_rest' => round($feeRest, 3),
                'to_type' => $toType,
                'to_id' => $toId,
                'already_transferred' => false,
                'transfer_attached' => false,
            ];

        }

        return response()->json([
            'data' => $items,
        ]);
    }

    private function keptFeeForSender(\App\Models\ShipmentFeeAllocation $alloc, int $senderWarehouseId): float
    {
        if ($senderWarehouseId <= 0)
            return 0.0;

        if ((int) $alloc->first_warehouse_id === $senderWarehouseId) {
            return (float) ($alloc->first_warehouse_amount ?? 0.0);
        }

        $sum = 0.0;
        foreach ($alloc->others as $o) {
            if ((int) $o->warehouse_id === $senderWarehouseId) {
                $sum += (float) ($o->amount ?? 0.0);
            }
        }

        if ((int) ($alloc->other_warehouse_id ?? 0) === $senderWarehouseId) {
            $sum = max($sum, (float) ($alloc->other_warehouse_amount ?? 0.0));
        }

        return $sum;
    }

    private function resolveShipmentDestination(\App\Models\Shipment $shipment): array
    {
        $taskShipment = \DB::table('transfer_task_shipments as tto')
            ->join('transfer_tasks as tt', 'tto.transfer_task_id', '=', 'tt.id')
            ->join('transfer_destinations as td', 'tt.id', '=', 'td.transfer_task_id')
            ->where('tto.shipment_tracking_no', $shipment->tracking_no)
            ->select('td.destination_type', 'td.destination_id')
            ->latest('tto.id')
            ->first();

        if ($taskShipment) {
            return [$taskShipment->destination_type, (int) $taskShipment->destination_id];
        }

        return [null, null];
    }
}
