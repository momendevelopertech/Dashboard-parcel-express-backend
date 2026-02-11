<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Branch;
use App\Models\Station;
use App\Models\Hub;


class TransferShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $shipment = $this->whenLoaded('shipment', function () {
            return $this->shipment;
        });
        $ownership = $this->whenLoaded('ownership', function () {
            return $this->ownership;
        });
        $destinationOwner = $this->whenLoaded('owner', function () {
            return $this->owner;
        });

        $consignee = optional(optional($shipment)->consignee);

        $fullPhoneNumber = 'N/A';

        if ($consignee->cellphone) {
            $countryCode = $consignee->country_key_cellphone ? '+' . ltrim($consignee->country_key_cellphone, '+') : '';
            if (empty(trim($countryCode))) {
                $fullPhoneNumber = $consignee->cellphone;
            } else {
                $fullPhoneNumber = trim($countryCode . $consignee->cellphone);
            }
        }

        return [
            'id' => $this->id,
            'tracking_no' => $this->shipment_tracking_no,
            'status' => ucfirst(strtolower($this->status)), // 'Pending'
            'shipment_details' => $this->when($shipment, [
                'shipment_id' => optional($shipment)->id,
                'consignee_name' => $consignee->name ?? 'N/A',
                'consignee_phone' => $fullPhoneNumber,
                'core_status' => optional($shipment)->core_status?->name ?? 'N/A', // Assuming core_status is loaded/available
            ]),
            'source' => [
                'entity_name' => optional($ownership)->name ?? 'N/A',
                'entity_type' => $this->when($ownership, fn() => getAccountableFriendlyName($this->ownership_type)),
                'ownership_id' => $this->ownership_id,
            ],
            'destination' => [
                'entity_name' => optional($destinationOwner)->name ?? 'N/A',
                'entity_type' => $this->owner_type ? getAccountableFriendlyName($this->owner_type) : 'N/A',
                'owner_id' => $this->owner_id,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
