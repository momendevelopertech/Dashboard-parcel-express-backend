<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'allowed_scopes' => 'nullable|array',
            'allowed_scopes.*' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'rate_limit' => 'nullable|array',
            'rate_limit.requests_per_minute' => 'nullable|integer|min:0',
            'rate_limit.requests_per_hour' => 'nullable|integer|min:0',
        ];
    }
}

