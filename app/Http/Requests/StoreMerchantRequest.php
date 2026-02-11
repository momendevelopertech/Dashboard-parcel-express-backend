<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        // If phone is missing but contact_no exists, use contact_no as phone
        if (!$this->has('phone') && $this->has('contact_no')) {
            $this->merge(['phone' => $this->contact_no]);
        }

        // Format phone numbers by removing any non-numeric characters except +
        $formatPhone = function ($number) {
            // Remove all non-numeric characters except +
            $formatted = preg_replace('/[^0-9+]/', '', $number);
            // Ensure the + is at the start if it exists
            if (str_contains($formatted, '+')) {
                $formatted = '+' . str_replace('+', '', $formatted);
            }
            return $formatted;
        };

        // Helper to prepend country code if missing
        $ensureCountryCode = function ($number) {
            // If number already has +, assume it has country code
            if (str_starts_with($number, '+')) {
                return $number;
            }

            // If country_id is present, try to get phone code
            if ($this->has('country_id')) {
                $country = \App\Models\Country::find($this->country_id);
                if ($country && $country->phonecode) {
                    // Prepend + and phonecode
                    // Remove leading zeros from local number if needed?
                    // Usually safer to just concat: +20 + 010... -> +20010...
                    // But splitPhoneNumber handles leading zeros for some countries.
                    return '+' . $country->phonecode . $number;
                }
            }

            // Fallback (existing behavior will default to +968 in splitPhoneNumber if no +)
            return $number;
        };

        if ($this->has('phone')) {
            $phone = $ensureCountryCode($this->phone);
            $this->merge(['phone' => $formatPhone($phone)]);
        }

        if ($this->has('contact_no')) {
            $contact = $ensureCountryCode($this->contact_no);
            $this->merge(['contact_no' => $formatPhone($contact)]);
        }
    }

    public function rules()
    {
        return [
            'country_id' => 'required|exists:countries,id',
            'governorate_id' => 'required|exists:governorates,id',
            'state_id' => 'required|exists:states,id',
            'address' => 'required|string|max:255',
            'contact_no' => [
                'required',
                'string',
                'regex:/^\+?[0-9]{5,20}$/'
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^\+?[0-9]{5,20}$/',
                function ($attribute, $value, $fail) {
                    $phoneSplit = splitPhoneNumber($value);
                    $countryCode = $phoneSplit['country_code'] ?? null;
                    $phoneNumber = $phoneSplit['national_number'] ?? null;

                    if (!$countryCode || !$phoneNumber) {
                        return;
                    }

                    // Check if a user with the same phone number and Merchant role exists
                    $exists = User::where('country_code', $countryCode)
                        ->where('phone', $phoneNumber)
                        ->whereHas('roles', function ($query) {
                            $query->where('name', 'Merchant');
                        })
                        ->exists();

                    if ($exists) {
                        $fail('The phone number has already been taken for a merchant account.');
                    }
                }
            ],
            'email' => 'nullable|email|unique:users,email',
            'username' => 'nullable|unique:users,username',
            'facility_to_facility_fees' => 'nullable|numeric|min:0|max:999999',
            'password' => 'required|string|min:8',
            'image' => 'nullable|array',
            'image.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];
    }

    public function messages()
    {
        return [
            'contact_no.regex' => 'The contact number must be a valid phone number (5-20 digits, + optional)',
            'phone.regex' => 'The phone number must be a valid phone number (5-20 digits, + optional)',
            'phone.unique' => 'The phone number has already been taken for a merchant account.',
            // Removed phone.* to allow specific errors to show
            'email.unique' => 'This email is already registered.',
            'password.min' => 'The password must be at least 8 characters.',
        ];
    }
}
