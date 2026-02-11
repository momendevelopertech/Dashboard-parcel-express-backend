<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateZoneRequest extends FormRequest
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
            "zone_id" => "required|integer|exists:zones,id",
            "name" => "required|string|max:255",
            "coordinates" => "required|array",
            "coordinates.type" => "required|string|in:Polygon,MultiPolygon",
            "coordinates.coordinates" => "required|array",
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'zone_id.required' => 'Zone ID is required.',
            'zone_id.exists' => 'Zone not found.',
            'name.required' => 'Zone name is required.',
            'coordinates.required' => 'Coordinates are required.',
            'coordinates.type.in' => 'Coordinates type must be Polygon or MultiPolygon.',
            'coordinates.coordinates.required' => 'Coordinates array is required.',
        ];
    }
}
