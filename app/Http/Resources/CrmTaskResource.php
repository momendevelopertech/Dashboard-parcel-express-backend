<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\ShipmentResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'task_name'  => $this->task_name,
            'title'      => $this->title,
            'note'       => $this->note,
            'status'     => $this->status,
            'updated_by' => $this->updated_by,
            'shipment'      => $this->whenLoaded('shipment', fn() => new ShipmentResource($this->shipment)),
            'station_name' => $this->shipment && $this->shipment->assigned_to_shelf && $this->shipment->assigned_to_shelf->shelf && $this->shipment->assigned_to_shelf->shelf->owner
                ? $this->shipment->assigned_to_shelf->shelf->owner->name
                :  null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
        // return parent::toArray($request);
    }
}
