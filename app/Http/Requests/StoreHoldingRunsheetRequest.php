<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHoldingRunsheetRequest extends FormRequest
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
            'driver_id'          => ['required', 'exists:users,id'],
            'remaining_amount'          => ['required', 'numeric', 'min:0'],
            'total_amount'       => ['required', 'numeric', 'min:0'],
            'notes'              => ['sometimes'],

            'paid_by_cash' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn() => $this->input('remaining_amount') > 0),
                'required_without:paid_by_bank',
            ],

            'paid_by_bank' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn() => $this->input('remaining_amount') > 0),
                'required_without:paid_by_cash',
            ],
        ];
    }
}
