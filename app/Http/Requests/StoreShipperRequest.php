<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:shippers,email',
            'contact' => 'required|phone:OM',
            'alternative_contact' => 'nullable|phone:OM',
            'country_id' => 'required|exists:countries,id',
            'state_id' => 'required|exists:states,id',
            'address' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'website' => 'nullable|url',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'failed_ofd_count' => 'integer|required',
            'rto_days' => 'integer|required',
        ];
    }

    public function messages()
    {
        return [
            'contact.phone' => 'The contact number must be a valid Omani phone number.',
            'alternative_contact.phone' => 'The alternative contact number must be a valid Omani phone number.',
        ];
    }
}
