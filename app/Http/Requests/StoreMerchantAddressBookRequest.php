<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantAddressBookRequest extends FormRequest
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
            'email' => 'nullable|email|max:255',
            'cellphone' => 'required|string|max:20',
            'alternatePhone' => 'nullable|string|max:20',
            'country_id' => 'required|exists:countries,id',
            'governorate_id' => 'required|exists:governorates,id',
            'state_id' => 'required|exists:states,id',
            'place_id' => 'nullable|exists:places,id',
            'zipcode' => 'nullable|string|max:20',
            'streetAddress' => 'nullable|string|max:500',
            'location_url' => 'nullable|url|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'name.string' => 'Name must be a string',
            'name.max' => 'Name may not be greater than 255 characters',
            
            'email.email' => 'Email must be a valid email address',
            'email.max' => 'Email may not be greater than 255 characters',
            
            'cellphone.required' => 'Cell phone is required',
            'cellphone.string' => 'Cell phone must be a string',
            'cellphone.max' => 'Cell phone may not be greater than 20 characters',
            
            'alternatePhone.string' => 'Alternate phone must be a string',
            'alternatePhone.max' => 'Alternate phone may not be greater than 20 characters',
            
            'country_id.required' => 'Country is required',
            'country_id.exists' => 'Selected country is invalid',
            
            'governorate_id.required' => 'Governorate is required',
            'governorate_id.exists' => 'Selected governorate is invalid',
            
            'state_id.required' => 'State is required',
            'state_id.exists' => 'Selected state is invalid',
            
            'place_id.required' => 'Place is required',
            'place_id.exists' => 'Selected place is invalid',
            
            'zipcode.string' => 'Zipcode must be a string',
            'zipcode.max' => 'Zipcode may not be greater than 20 characters',
            
            // 'streetAddress.required' => 'Street address is required',
            'streetAddress.string' => 'Street address must be a string',
            'streetAddress.max' => 'Street address may not be greater than 500 characters',
            
            'location_url.url' => 'Location URL must be a valid URL',
            'location_url.max' => 'Location URL may not be greater than 500 characters',
        ];
    }
}
