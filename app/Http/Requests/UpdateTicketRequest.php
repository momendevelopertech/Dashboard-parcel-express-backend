<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
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
            'id' => 'required|integer|exists:tickets,id',
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_email' => 'sometimes|required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'subject' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'category' => 'nullable|string|in:Technical,Billing,General,Account,Product,Other',
            'priority' => 'nullable|string|in:LOW,MEDIUM,HIGH,URGENT',
            'status' => 'nullable|string|in:OPEN,IN_PROGRESS,PENDING,RESOLVED,CLOSED',
            'due_date' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt,zip', // 10MB max per file
            'assigned_agent_id' => 'nullable|integer|exists:users,id',
            'resolution' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback' => 'nullable|string',
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
            'id.required' => 'Ticket ID is required.',
            'id.exists' => 'Ticket not found.',
            'customer_name.required' => 'Customer name is required.',
            'customer_email.required' => 'Customer email is required.',
            'customer_email.email' => 'Please provide a valid email address.',
            'subject.required' => 'Ticket subject is required.',
            'description.required' => 'Ticket description is required.',
            'priority.in' => 'Priority must be one of: LOW, MEDIUM, HIGH, URGENT.',
            'status.in' => 'Status must be one of: OPEN, IN_PROGRESS, PENDING, RESOLVED, CLOSED.',
            'attachments.*.max' => 'Each file must not exceed 10MB.',
            'attachments.*.mimes' => 'File type not supported. Allowed: jpg, jpeg, png, pdf, doc, docx, txt, zip.',
            'assigned_agent_id.exists' => 'Selected agent does not exist.',
            'rating.min' => 'Rating must be between 1 and 5.',
            'rating.max' => 'Rating must be between 1 and 5.',
        ];
    }
}
