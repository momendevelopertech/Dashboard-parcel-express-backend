<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'period_start_date' => $this->period_start_date,
            'period_end_date' => $this->period_end_date,
            'regular_hours' => $this->regular_hours,
            'overtime_hours' => $this->overtime_hours,
            'regular_pay' => $this->regular_pay,
            'overtime_pay' => $this->overtime_pay,
            'tax_deduction' => $this->tax_deduction,
            'insurance_deduction' => $this->insurance_deduction,
            'penalty_deductions' => $this->penalty_deductions,
            'gross_pay' => $this->gross_pay,
            'net_pay' => $this->net_pay,
            'payment_date' => $this->payment_date,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            // 'employee' => new SampleEmployeeResource($this->whenLoaded('employee')),
            'employee' => [
                'id' => $this->employee->id,
                'name' => $this->employee->user->name,
            ],
        ];
    }
}
