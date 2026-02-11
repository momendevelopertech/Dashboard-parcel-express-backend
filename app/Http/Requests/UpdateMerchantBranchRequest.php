<?php

namespace App\Http\Requests;

use App\Models\Country;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMerchantBranchRequest extends FormRequest
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
        $oman = Country::where('name', 'Oman')->value('id');
        
        return [
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:20',
            'country_id' => 'required|integer|exists:countries,id',
            'governorate_id' => [
                Rule::requiredIf($this->input('country_id') == $oman),
                'nullable',
                'exists:governorates,id'
            ],
            'state_id' => 'required|integer|exists:states,id',
            'place_id' => [
                Rule::requiredIf($this->input('country_id') == $oman),
                'nullable',
                'exists:places,id'
            ],
            'city_id' => [
                Rule::requiredIf($this->input('country_id') != $oman),
                'nullable',
                'exists:cities,id'
            ],
            'location' => 'required|string|max:500',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'status' => 'required|in:active,inactive',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Branch name is required.',
            'contact.required' => 'Contact number is required.',
            'country_id.required' => 'Country is required.',
            'state_id.required' => 'State is required.',
            'location.required' => 'Location address is required.',
            'lat.required' => 'Latitude coordinate is required.',
            'lng.required' => 'Longitude coordinate is required.',
            'status.required' => 'Branch status is required.',
        ];
    }
}
