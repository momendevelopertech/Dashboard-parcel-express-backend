<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'driver_id' => $this->driver_id,
            'assigned_by' => $this->assigned_by,
            'assigned_at' => $this->assigned_at,
            'confirmed_at' => $this->confirmed_at,
            'delivered_at' => $this->delivered_at,
            'returned_at' => $this->returned_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            "shipment" => $this->shipment,
            // 'shipment' => $this->whenLoaded('shipment', function () {
            //     return [
            //         'id' => $this->shipment->id,
            //         'consignee_id' => $this->shipment->consignee_id,
            //         'shipper_id' => $this->shipment->shipper_id,
            //         'merchant_id' => $this->shipment->merchant_id,
            //         'tracking_no' => $this->shipment->tracking_no,
            //         'amount' => $this->shipment->amount,
            //         'delivery_fee' => $this->shipment->delivery_fee,
            //         'payment_type' => $this->shipment->payment_type,
            //         'is_return' => $this->shipment->is_return,
            //         'is_delivered' => $this->shipment->is_delivered,
            //         'status' => $this->shipment->status,
            //         'owner_type' => $this->shipment->owner_type,
            //         'owner_id' => $this->shipment->owner_id,
            //         'created_at' => $this->shipment->created_at,
            //         'updated_at' => $this->shipment->updated_at,
            //     ];
            // }),
        ];
    }
}
