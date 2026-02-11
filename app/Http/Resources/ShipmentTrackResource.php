<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentTrackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'shipmentId' => $this->tracking_no,
            'shipmentDate' => $this->created_at->format('Y-m-d'),
            'currentStatus' => $this->status,
            'estimatedDelivery' => $this->delivery_date ? $this->delivery_date->format('Y-m-d') : null,
            'driver' => $this->whenLoaded('current_assignment.driver', function () {
                return [
                    'name' => $this->current_assignment->driver->name,
                    'phone' => $this->current_assignment->driver->phone,
                    'location' => [
                        'lat' => $this->current_assignment->driver->latitude,
                        'lng' => $this->current_assignment->driver->longitude
                    ]
                ];
            }),
            'timeline' => $this->whenLoaded('shipmentHistories', function () {
                return $this->shipmentHistories->map(function ($history) {
                    return [
                        'type' => $history->type,
                        'time' => $history->created_at->format('Y-m-d H:i A'),
                        'description' => $history->description
                    ];
                })->toArray();
            })
        ];
    }
}
