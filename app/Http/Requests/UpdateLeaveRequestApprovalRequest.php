<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequestApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leave_request_id' => 'required|exists:leave_requests,id',
            'approver_id' => 'required|exists:employees,id',
            'hierarchy_level_id' => 'required|exists:hierarchy_levels,id',
            'status' => 'nullable|in:pending,approved,rejected',
            'comment' => 'nullable|string',
            'approved_at' => 'nullable|date',
        ];
    }
}
