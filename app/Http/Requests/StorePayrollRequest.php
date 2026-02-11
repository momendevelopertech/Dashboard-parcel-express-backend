<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
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
            'employee_id' => 'required|exists:employees,id',
            'period_start_date' => 'required|date',
            'period_end_date' => 'required|date',
            'regular_hours' => 'nullable|numeric',
            'overtime_hours' => 'nullable|numeric',
            'regular_pay' => 'nullable|numeric',
            'overtime_pay' => 'nullable|numeric',
            'tax_deduction' => 'nullable|numeric',
            'insurance_deduction' => 'nullable|numeric',
            'penalty_deductions' => 'nullable|numeric',
            'gross_pay' => 'nullable|numeric',
            'net_pay' => 'nullable|numeric',
            'payment_date' => 'nullable|date',
        ];
    }
}