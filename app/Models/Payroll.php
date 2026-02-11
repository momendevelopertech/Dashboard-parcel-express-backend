<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'period_start_date',
        'period_end_date',
        'regular_hours',
        'overtime_hours',
        'regular_pay',
        'overtime_pay',
        'tax_deduction',
        'insurance_deduction',
        'penalty_deductions',
        'gross_pay',
        'net_pay',
        'payment_date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
