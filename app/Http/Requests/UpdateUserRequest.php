<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation error messages.
     */
    public function messages()
    {
        return [
            // 'phone.phone' => 'The phone number must be a valid Omani phone number.',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "id" => "required|exists:users,id",
            "name" => "required",
            "email" => "nullable|email",
            "phone" => [
                "required",
                "phone:AUTO",
                function ($attribute, $value, $fail) {
                    $phoneSplit = splitPhoneNumber($value);
                    $countryCode = $phoneSplit['country_code'] ?? null;
                    $phoneNumber = $phoneSplit['national_number'] ?? null;
                    $roleId = $this->input('role');
                    $userId = $this->input('id');

                    if (!$countryCode || !$phoneNumber || !$userId) {
                        return;
                    }

                    // If role is provided, check uniqueness for that role
                    if ($roleId) {
                        $exists = User::where('country_code', $countryCode)
                            ->where('phone', $phoneNumber)
                            ->where('id', '!=', $userId)
                            ->whereHas('roles', function ($query) use ($roleId) {
                                $query->where('id', $roleId);
                            })
                            ->exists();

                        if ($exists) {
                            $fail(__('validation.phone_taken_for_role'));
                        }
                    } else {
                        // If no role is provided, check if the phone conflicts with any of the current user's roles
                        $user = User::find($userId);
                        if ($user) {
                            $userRoleIds = $user->roles->pluck('id')->toArray();

                            if (!empty($userRoleIds)) {
                                $exists = User::where('country_code', $countryCode)
                                    ->where('phone', $phoneNumber)
                                    ->where('id', '!=', $userId)
                                    ->whereHas('roles', function ($query) use ($userRoleIds) {
                                        $query->whereIn('id', $userRoleIds);
                                    })
                                    ->exists();

                                if ($exists) {
                                    $fail(__('validation.phone_taken_for_current_roles'));
                                }
                            }
                        }
                    }
                }
            ],
        ];
    }
}
