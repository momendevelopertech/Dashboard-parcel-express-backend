<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            "name" => "required",
            // Username is unique on the users table, so we must validate it to avoid DB query exceptions
            "username" => "nullable|unique:users,username",
            "email" => "nullable|email|unique:users,email",
            "phone" => [
                "required",
                "phone:AUTO",
                function ($attribute, $value, $fail) {
                    $phoneSplit = splitPhoneNumber($value);
                    $countryCode = $phoneSplit['country_code'] ?? null;
                    $phoneNumber = $phoneSplit['national_number'] ?? null;
                    $roleId = $this->input('role');

                    if (!$countryCode || !$phoneNumber || !$roleId) {
                        return;
                    }

                    // Check if a user with the same phone number and role exists
                    $exists = User::where('country_code', $countryCode)
                        ->where('phone', $phoneNumber)
                        ->whereHas('roles', function ($query) use ($roleId) {
                            $query->where('id', $roleId);
                        })
                        ->exists();

                    if ($exists) {
                        $fail(__('validation.phone_taken_for_role'));
                    }
                }
            ],
            'password' => [
                'required',
                Password::defaults()
            ],
            "role" => "required",
        ];
    }

    public function messages()
    {
        return [
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'phone.*' => __('validation.phone_taken_for_role'),
            // 'phone.phone' => 'The phone number must be a valid Omani phone number.',
        ];
    }
}
