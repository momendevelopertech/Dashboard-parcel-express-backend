<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeBranchResource extends JsonResource
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
            'employee' => new SampleEmployeeResource($this->employee),
            'morphable_type' => $this->morphable_type,
            'morphable_id' => $this->morphable_id,
            'name' => $this->getEmployableName(),
            'is_manager' => $this->is_manager,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function getEmployableName(): string
    {
        if ($this->morphable_type == 'Hub') {
            return $this->morphable ? $this->morphable->name : '';
        }
        if ($this->morphable_type == 'Station') {
            return $this->morphable ? $this->morphable->name : '';
        }
        if ($this->morphable_type == 'Branch') {
            return $this->morphable ? $this->morphable->name : '';
        }
        return $this->morphable_type;
    }
}
