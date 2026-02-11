<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'user_id' => $this->employee_id,
            'user' => new UserResource($this->employee),
            'current_approver' => new UserResource($this->currentApprover),
            'reason_id' => $this->reason_id,
            'leave_reason' => new LeaveReasonResource($this->reason),
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'proof_file' => $this->proof_file,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
