<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestApprovalResource extends JsonResource
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
            'leave_request_id' => $this->leave_request_id,
            'approver_id' => $this->approver_id,
            'hierarchy_level_id' => $this->hierarchy_level_id,
            'status' => $this->status,
            'comment' => $this->comment,
            'approved_at' => $this->approved_at ? $this->approved_at : null,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'approver' => new SampleEmployeeResource($this->whenLoaded('approver')),
            'leave_request' => new LeaveRequestResource($this->whenLoaded('leaveRequest')),
            'hierarchy_level' => new HierarchyLevelResource($this->whenLoaded('hierarchyLevel')),
        ];
    }
}
