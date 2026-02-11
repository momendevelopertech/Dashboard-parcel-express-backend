<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStateRequest extends FormRequest
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
            'en_name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'governorate_id' => 'nullable',
            'lat' => 'required',
            'lng' => 'required',
            'isActive' => 'nullable|boolean',
        ];
    }
}
