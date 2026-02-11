<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCODCollectionRequest extends FormRequest
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
            'driver_runsheet_id' => ['required', 'exists:driver_runsheets,id'],
            'paid_by_cash' => ['required_without:paid_by_bank', 'nullable', 'numeric'],
            'paid_by_bank' => ['required_without:paid_by_cash', 'nullable', 'numeric'],
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ];
    }
}
