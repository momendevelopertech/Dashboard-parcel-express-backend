<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'user_id' => 'required|exists:users,id',
            'department_id' => 'required|exists:employee_departments,id',
            'position_id' => 'required|exists:employee_positions,id',
            'level_id' => 'required|exists:hierarchy_levels,id',
            'direct_manager_id' => 'nullable|exists:users,id',
            'basic_salary' => 'required|numeric|min:0',
            'date_of_joining' => 'required|date',
            'base_hours' => 'required|numeric|min:0',
            'overtime_hour_salary' => 'required|numeric|min:0',
            'country_id' => 'nullable|exists:countries,id',
        ];
    }
}
