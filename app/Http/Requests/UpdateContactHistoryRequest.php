<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactHistoryRequest extends FormRequest
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
            'id' => 'required|integer|exists:contact_histories,id',
            'contact_id' => 'sometimes|required|string|max:255',
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'interaction_type' => 'sometimes|required|string|in:CHAT,TICKET,EMAIL,PHONE,OTHER',
            'method' => 'sometimes|required|string|in:CHAT,EMAIL,PHONE,IN_PERSON,OTHER',
            'channel' => 'nullable|string|in:WEBSITE,MOBILE_APP,PHONE,EMAIL,IN_PERSON,OTHER',
            'summary' => 'sometimes|required|string',
            'details' => 'nullable|string',
            'status' => 'nullable|string|in:RESOLVED,PENDING,FOLLOW_UP_REQUIRED,LOGGED',
            'related_ticket_id' => 'nullable|integer|exists:tickets,id',
            'related_chat_session_id' => 'nullable|integer|exists:chat_sessions,id',
            'contacted_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'outcome' => 'nullable|string',
            'follow_up_required' => 'boolean',
            'follow_up_date' => 'nullable|date',
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
            'id.required' => 'Contact history ID is required.',
            'id.exists' => 'Contact history not found.',
            'customer_name.required' => 'Customer name is required.',
            'customer_email.email' => 'Please provide a valid email address.',
            'interaction_type.required' => 'Interaction type is required.',
            'interaction_type.in' => 'Interaction type must be one of: CHAT, TICKET, EMAIL, PHONE, OTHER.',
            'method.required' => 'Method is required.',
            'method.in' => 'Method must be one of: CHAT, EMAIL, PHONE, IN_PERSON, OTHER.',
            'summary.required' => 'Summary is required.',
            'status.in' => 'Status must be one of: RESOLVED, PENDING, FOLLOW_UP_REQUIRED, LOGGED.',
            'related_ticket_id.exists' => 'Selected ticket does not exist.',
            'related_chat_session_id.exists' => 'Selected chat session does not exist.',
        ];
    }
}
