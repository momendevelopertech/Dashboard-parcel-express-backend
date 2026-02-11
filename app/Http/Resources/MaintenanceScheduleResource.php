<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle collections
        if ($this->resource instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return [
                'data' => $this->resource->items(),
                'links' => $this->resource->linkCollection(),
                'meta' => [
                    'current_page' => $this->resource->currentPage(),
                    'total' => $this->resource->total(),
                    'per_page' => $this->resource->perPage(),
                ]
            ];
        }

        // Handle collections without pagination
        if ($this->resource instanceof \Illuminate\Support\Collection) {
            return $this->resource->map(function ($schedule) {
                return $this->formatSchedule($schedule);
            })->toArray();
        }

        // Handle single resource
        return $this->formatSchedule($this->resource);
    }

    private function formatSchedule($schedule)
    {
        return [
            'id' => $schedule->id,
            'truck_id' => $schedule->truck_id,
            'maintenance_type' => $schedule->maintenance_type,
            'scheduled_at' => $schedule->scheduled_at,
            'status' => $schedule->status,
            'notes' => $schedule->notes,
            'created_at' => $schedule->created_at,
            'updated_at' => $schedule->updated_at,
            'truck' => $schedule->truck ? [
                'id' => $schedule->truck->id,
                'barcode' => $schedule->truck->barcode,
                'number_plate' => $schedule->truck->number_plate,
                'company' => $schedule->truck->company,
            ] : null,
        ];
    }
}
