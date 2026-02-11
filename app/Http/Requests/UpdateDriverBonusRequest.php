<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverBonusRequest extends FormRequest
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
            'id' => 'required|exists:driver_bonuses,id',
            'delivery_bonus' => 'required|numeric|min:0',
            'pickup_bonus' => 'required|numeric|min:0',
            'return_bonus' => 'required|numeric|min:0',
            'return_pickup_bonus' => 'required|numeric|min:0',
        ];
    }
}
