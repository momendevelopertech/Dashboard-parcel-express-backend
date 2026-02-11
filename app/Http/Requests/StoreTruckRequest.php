<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTruckRequest extends FormRequest
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
            "number_plate" => "required",
            "type" => "required|in:truck,van,mini_van,pickup",
            "truck_driver_id" => "nullable|exists:users,id",
            "status" => "required|in:active,inactive,maintenance"
        ];
    }
}
