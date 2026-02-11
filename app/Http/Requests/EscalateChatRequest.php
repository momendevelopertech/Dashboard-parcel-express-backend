<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EscalateChatRequest extends FormRequest
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
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority' => 'nullable|in:LOW,MEDIUM,HIGH,URGENT',
            'category' => 'nullable|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
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
            'subject.required' => 'Ticket subject is required.',
            'subject.max' => 'Subject cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'priority.in' => 'Priority must be one of: LOW, MEDIUM, HIGH, URGENT.',
            'assigned_to.exists' => 'The selected user does not exist.',
        ];
    }
} 