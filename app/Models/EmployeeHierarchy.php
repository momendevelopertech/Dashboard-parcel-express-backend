<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmployeeHierarchy extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'approver_id',
        'hierarchy_level_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function hierarchyLevel()
    {
        return $this->belongsTo(HierarchyLevel::class);
    }
}
