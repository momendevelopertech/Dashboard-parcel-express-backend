<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreSafetyIncidentRequest extends FormRequest
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
            'occurred_at' => 'required|date',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'severity' => 'required|in:Low,Medium,High',
            'status' => 'nullable|in:Open,Under Investigation,Resolved',
            'reported_by' => 'nullable|exists:users,id',
            'assigned_to' => 'nullable|exists:users,id',
            'investigation_notes' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
        ];
    }

    public function prepareForValidation()
    {
        // Set reported_by to current user if not provided
        if (!$this->has('reported_by')) {
            $this->merge([
                'reported_by' => Auth::id(),
            ]);
        }
        
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'Open',
            ]);
        }
    }
}
