<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:employees,email,' . $this->id,
            'phone' => 'sometimes|string|max:20',
            'department_id' => 'sometimes|exists:employee_departments,id',
            'position_id' => 'sometimes|exists:employee_positions,id',
            'level_id' => 'sometimes|exists:hierarchy_levels,id',
            'direct_manager_id' => 'nullable|exists:users,id',
            'salary' => 'sometimes|numeric|min:0',
            'basic_salary' => 'required|numeric|min:0',
            'date_of_joining' => 'required|date',
            'base_hours' => 'required|numeric|min:0',
            'overtime_hour_salary' => 'required|numeric|min:0',
            'country_id' => 'nullable|exists:countries,id',
            'hire_date' => 'sometimes|date',
            'status' => 'sometimes|in:active,inactive,terminated,on_leave',
        ];
    }
}
