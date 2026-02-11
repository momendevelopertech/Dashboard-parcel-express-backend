<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferTaskRequest extends FormRequest
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
            "truck_id" => "required|exists:trucks,id",
            "truck_driver_id" => "required|exists:truck_drivers,id",
            "destinations" => "required"
        ];
    }

    public function messages(): array
    {
        return [
            "destinations.required" => "The destinations field is required.",
            "destinations.array" => "Please provide valid destinations.",
        ];
    }
}
