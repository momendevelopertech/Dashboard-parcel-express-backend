<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation()
    {
        // Auto-generate contact_id for grouping related interactions
        if (!$this->contact_id) {
            $this->merge([
                'contact_id' => uniqid('contact_'),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|in:Technical,Billing,General,Account,Product,Other',
            'priority' => 'nullable|string|in:LOW,MEDIUM,HIGH,URGENT',
            'due_date' => 'nullable|date|after:now',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt,zip', // 10MB max per file
            'assigned_agent_id' => 'nullable|integer|exists:users,id',
            'internal_notes' => 'nullable|string',
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
            'customer_name.required' => 'Customer name is required.',
            'customer_email.required' => 'Customer email is required.',
            'customer_email.email' => 'Please provide a valid email address.',
            'subject.required' => 'Ticket subject is required.',
            'description.required' => 'Ticket description is required.',
            'due_date.after' => 'Due date must be in the future.',
            'attachments.*.max' => 'Each file must not exceed 10MB.',
            'attachments.*.mimes' => 'File type not supported. Allowed: jpg, jpeg, png, pdf, doc, docx, txt, zip.',
            'assigned_agent_id.exists' => 'Selected agent does not exist.',
        ];
    }
}
