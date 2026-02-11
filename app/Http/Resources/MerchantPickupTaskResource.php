<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPickupTaskResource extends JsonResource
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
            'ref' => $this->ref,
            'merchant_id' => $this->merchant_id,
            'driver_id' => $this->driver_id,
            'driver' => $this->driver ? [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'phone' => $this->driver->phone ?? $this->driver->cellphone,
                'country_code' => $this->driver->country_code ?? null,
            ] : null,
            'no_of_shipments' => (int) $this->no_of_shipments, // total_shipments_no
            'registered_shipments_no' => $this->registered_shipments_no, // Accessor
            'picked_shipments_no' => $this->picked_shipments_no,
            'shipments_count' => $this->shipments_count ?? $this->shipments()->count(),
            'extra_shipments_no'=> $this->extra_shipments_no,
            'cached_shipment_step' => $this->cached_shipment_step,
            'note' => $this->note,
            'status' => $this->status,
            'pickup_request_id' => $this->pickup_request_id,
            'pickup_request_ref' => null, // ref is now on MerchantPickupTask, not PickupRequest
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'merchant' => $this->merchant,
            'shipments' => $this->shipments,
            'merchant_pickup_shipment' => MerchantPickupShipmentResource::collection($this->shipments),
        ];
    }
}
