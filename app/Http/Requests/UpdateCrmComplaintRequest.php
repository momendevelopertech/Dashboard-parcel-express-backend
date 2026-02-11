<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCrmComplaintRequest extends FormRequest
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
            'crm_scenario_id' => 'nullable|integer',
            'tracking_no' => 'nullable|exists:shipments,tracking_no',
            'complaint_details' => 'nullable|string',
            'status' => 'string|in:pending,in_progress,completed,escalated',
        ];
    }
}
