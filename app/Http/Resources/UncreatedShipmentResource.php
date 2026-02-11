<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UncreatedShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this->id,
                'driver_id' => $this->driver_id,
                'merchant' => $this->merchant ? [
                    'id' => $this->merchant->id,
                    'name' => $this->merchant->name,
                ] : null,
                'driver' => $this->driver ? [
                    'id' => $this->driver->id,
                    'name' => $this->driver->name,
                ] : null,
                'merchant_id' => $this->merchant_id,
                'tracking_no' => $this->shipment_tracking_no,
                'pickup_task_id' => $this->pickup_task_id,
                'pickup_ref' => $this->pickup_task->ref,
                'shipment_id' => $this->shipment_id,
                'shipment' => $this->shipment ? [
                    'tracking_no' => $this->shipment->tracking_no,
                    'status' => $this->shipment->status,
                ] : null,
                'proof_url' => $this->proofUrl,
                'status' => $this->status,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ];;
    }
}
