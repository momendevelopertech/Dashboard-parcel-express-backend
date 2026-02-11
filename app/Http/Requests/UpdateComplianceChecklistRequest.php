<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplianceChecklistRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'last_completed' => 'sometimes|date',
            'status' => 'sometimes|in:Compliant,Non-Compliant',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.string' => 'The checklist name must be a string.',
            'category.string' => 'The category must be a string.',
            'last_completed.date' => 'The last completed date must be a valid date.',
            'status.in' => 'The status must be either Compliant or Non-Compliant.',
        ];
    }
}
