<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
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
            'employee_id' => 'required|exists:users,id',
            'reason_id' => 'required|exists:leave_reasons,id',
            'status' => 'nullable|in:pending,approved,rejected', // Default should be 'pending'
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'proof_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ];
    }

    /**
     * Customize error messages.
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be the same or after the start date.',
            'start_date.after_or_equal' => 'The start date cannot be in the past.',
        ];
    }
}
