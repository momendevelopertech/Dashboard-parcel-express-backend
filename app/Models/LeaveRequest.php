<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'reason_id',
        'status',
        'start_date',
        'end_date',
        'proof_file',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reason()
    {
        return $this->belongsTo(LeaveReason::class);
    }

    public function approvals()
    {
        return $this->hasMany(LeaveRequestApproval::class);
    }
}
