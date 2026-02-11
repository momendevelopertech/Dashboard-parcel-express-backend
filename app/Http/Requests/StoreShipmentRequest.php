<?php

namespace App\Http\Requests;

use App\Models\Country;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
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
        // keep your PE prefix logic, but don't add PE for ME (merchant) or DR (driver) waybills
        if ($this->tracking_no) {
            $prefix = substr($this->tracking_no, 0, 2);
            // Only add PE prefix if it doesn't already have PE, ME, or DR prefix
            if ($prefix !== "PE" && $prefix !== "ME" && $prefix !== "DR") {
                $this->merge(['tracking_no' => 'PE' . $this->tracking_no]);
            }
        }

        // If neither provided, inject a PRE-ID BEFORE validation runs
        if (!$this->filled('tracking_no') && !$this->filled('pre_id')) {
            $this->merge(['pre_id' => generate_pre_id()]);
        }
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
            'email' => [
                'nullable',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Convert "null" string to actual null
                    if ($value === "null") {
                        $this->merge([$attribute => null]);
                        return;
                    }

                    if (!is_null($value) && $value !== '') {
                        $validator = validator(
                            [$attribute => $value],
                            [$attribute => 'email']
                        );
                        if ($validator->fails()) {
                            $fail('The ' . $attribute . ' must be a valid email address.');
                        }
                    }
                },
            ],
            'tracking_no' => ['nullable', 'string', 'required_without:pre_id'],
            'pre_id' => ['nullable', 'string', 'required_without:tracking_no'],
            'cellphone' => 'required|string|max:20',
            'alternatePhone' => 'nullable|string|max:20',
            'district' => 'nullable|string|max:255',
            'country_id' => 'required|integer|exists:countries,id',
            'governorate_id' => [
                Rule::requiredIf($this->input('country_id') == $oman),
            ],
            'value' => [
                Rule::requiredIf($this->input('payment_type') == 'COD'),
                'nullable',
                'numeric',
                'min:0',
            ],
            'state_id' => 'required|integer|exists:states,id',
            'zipcode' => 'nullable|string|max:20',
            'streetAddress' => 'nullable|string|max:255',
            'identify' => 'nullable|string|max:255',
            'taxNumber' => 'nullable|string|max:255',
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => 'nullable|string',
            'is_outsourced' => 'nullable',
            'payment_type' => 'required|string',
            'item_name' => 'nullable|array',
            'item_name.*' => 'nullable|string|max:255',
            'quantity' => 'nullable|array',
            'quantity.*' => 'nullable|integer|min:1',
            'category' => 'nullable|array',
            'category.*' => 'nullable|string|max:255',
            'merchant_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'merchant_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'merchant_phone' => [
                'nullable',
                'string',
                'max:20',
            ],
            'sender_country_id' => [
                'nullable',
                'integer',
                'exists:countries,id',
            ],
        ];
    }

    public function messages()
    {
        return [
            'cellphone.phone' => 'The cellphone number must be a valid Omani phone number.',
            'alternatePhone.phone' => 'The alternate phone number must be a valid Omani phone number.',
        ];
    }
}
