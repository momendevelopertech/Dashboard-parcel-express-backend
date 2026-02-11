<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeHierarchyResource extends JsonResource
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
            'employee' => new SampleEmployeeResource($this->employee),
            'approver_id' => $this->approver_id,
            'approver' => new SampleEmployeeResource($this->approver),
            'hierarchy_level_id' => $this->hierarchy_level_id,
            'hierarchy_level' => new HierarchyLevelResource($this->hierarchyLevel),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
