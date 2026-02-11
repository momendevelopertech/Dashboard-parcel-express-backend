<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
            'message' => 'required|string|max:2000',
            'sender_type' => 'required|in:CUSTOMER,AGENT,SYSTEM',
            'sender_name' => 'required|string|max:255',
            'message_type' => 'nullable|in:TEXT,IMAGE,FILE,SYSTEM',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // 10MB max per file
            'metadata' => 'nullable|array',
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
            'message.required' => 'Message content is required.',
            'message.max' => 'Message cannot exceed 2000 characters.',
            'sender_type.required' => 'Sender type is required.',
            'sender_type.in' => 'Sender type must be one of: CUSTOMER, AGENT, SYSTEM.',
            'sender_name.required' => 'Sender name is required.',
            'message_type.in' => 'Message type must be one of: TEXT, IMAGE, FILE, SYSTEM.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.max' => 'Each attachment cannot exceed 10MB.',
        ];
    }
} 