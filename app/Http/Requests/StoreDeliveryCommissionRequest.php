<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryCommissionRequest extends FormRequest
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
            'driver_id' => "required",
            'zone_id' => "required",
            'amount' => "required|numeric",
        ];
    }

    public function messages()
    {
        return [
            'amount' => [
                'required' => "please declare an amount",
                'numeric' => "please don't use words only enter numbers",
            ],
        ];
    }
}
