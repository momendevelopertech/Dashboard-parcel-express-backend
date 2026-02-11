<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPickupTaskDetailResource extends JsonResource
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
            'pickup_request_ref' => $this->pickup_request_ref ?? null,
            'merchant_id' => $this->merchant_id,
            'driver_id' => $this->driver_id,
            'no_of_shipments' => $this->no_of_shipments,
            'total_shipments_no' => (int) $this->no_of_shipments,
            'picked_shipments_no' => $this->picked_shipments_no,
            'registered_shipments_no' => $this->registered_shipments_no,
            'cached_shipment_step' => $this->cached_shipment_step,
            'note' => $this->note,
            'status' => $this->status,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'scheduled_at' => $this->scheduled_at ? $this->scheduled_at->format('Y-m-d H:i:s') : null,
            'merchant' => $this->formatMerchant($this->merchant),
            'shipments' => $this->formatShipments($this->shipments),
        ];
    }

    /**
     * Format merchant data
     */
    private function formatMerchant($merchant): ?array
    {
        if (!$merchant) {
            return null;
        }

        $merchantDetail = $merchant->merchant ?? null;

        $commission = $merchantDetail?->commissions?->first();

        return [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'phone' => $merchant->phone,
            'delivery_fee_before_discount' => $commission->base_delivery_fee ?? null,
            'delivery_fee_after_discount' => $commission->delivery_fee ?? null,
            'merchant' => $merchantDetail ? [
                'id' => $merchantDetail->id,
                'address' => $merchantDetail->address,
                'images' => $merchantDetail->user->merchant_images,
                'lat' => $merchantDetail->lat,
                'lng' => $merchantDetail->lng,
                'governorate' => $this->formatLocation($merchantDetail->governorate ?? null),
                'state' => $this->formatLocation($merchantDetail->state ?? null),
                'place' => $this->formatLocation($merchantDetail->place ?? null),
            ] : null,
        ];
    }

    /**
     * Format location (governorate/state/place)
     */
    private function formatLocation($location): ?array
    {
        if (!$location) {
            return null;
        }

        return [
            "id" => $location->id,
            'en_name' => $location->en_name ?? null,
            'ar_name' => $location->ar_name ?? null,
        ];
    }

    /**
     * Format shipments
     */
    private function formatShipments($shipments): array
    {
        if (!$shipments) {
            return [];
        }

        return $shipments->map(function ($shipment) {
            return [
                'id' => $shipment->id,
                'pickup_task_id' => $shipment->pickup_task_id,
                'shipment_tracking_no' => $shipment->shipment_tracking_no,
                'pre_id' => $shipment->pre_id,
                'status' => $shipment->status,
                'pickup_proof' => $shipment->pickup_proof,
                'created_at' => $shipment->created_at,
            ];
        })->toArray();
    }
}
