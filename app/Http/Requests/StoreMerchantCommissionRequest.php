<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantCommissionRequest extends FormRequest
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
    protected function prepareForValidation(): void
    {
        $commissions = $this->input('commissions', []);
        if (is_array($commissions)) {
            foreach ($commissions as $i => $row) {
                foreach ([
                    'base_delivery_fee',
                    'base_return_fee',
                    'delivery_discount_amount',
                    'return_discount_amount',
                    'delivery_fee',
                    'return_fee',
                ] as $key) {
                    if (isset($row[$key]) && $row[$key] !== '') {
                        $commissions[$i][$key] = (float) $row[$key];
                    }
                }
            }
            $this->merge(['commissions' => $commissions]);
        }
    }

    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'exists:users,id'], 
            'commissions' => ['required', 'array', 'min:1'],

            'commissions.*.country_id' => ['nullable', 'exists:countries,id'],
            'commissions.*.state_id' => ['nullable', 'exists:states,id'], // <-- allow global default

            'commissions.*.base_delivery_fee' => ['required', 'numeric', 'min:0'],
            'commissions.*.delivery_discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:commissions.*.base_delivery_fee'],

            'commissions.*.base_return_fee' => ['required', 'numeric', 'min:0'],
            'commissions.*.return_discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:commissions.*.base_return_fee'],

            'commissions.*.delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'commissions.*.return_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
