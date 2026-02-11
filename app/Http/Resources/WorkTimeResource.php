<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkTimeResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee' => new SampleEmployeeResource($this->whenLoaded('employee')),
            'check_in' => $this->check_in ? $this->check_in->format('Y-m-d H:i:s') : null,
            'start_from' => $this->start_from,
            'check_out' => $this->check_out ? $this->check_out->format('Y-m-d H:i:s') : null,
            'total_hours' => $this->total_hours,
        ];
    }
}
