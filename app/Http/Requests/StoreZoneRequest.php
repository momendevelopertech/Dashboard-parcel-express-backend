<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
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
            "name" => "required|unique:zones,name",
            "coordinates" => "required",
            "place_ids" => "nullable|array",
            "place_ids.*" => "exists:places,id",
            'owner_type' => 'nullable|string|in:hub,station,branch|required_with:owner_id',
            'owner_id' => 'nullable|integer|required_with:owner_type',
        ];
    }
}
