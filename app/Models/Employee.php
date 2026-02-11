<?php

namespace App\Models;

use App\Observers\EmployeeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


#[ObservedBy(EmployeeObserver::class)]
class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'position_id',
        'level_id',
        'direct_manager_id',
        'basic_salary',
        'country_id',
        'date_of_joining',
        'base_hours',
        'overtime_hour_salary',
        'basic_salary',
    ];

    public function department()
    {
        return $this->belongsTo(EmployeeDepartment::class);
    }

    public function position()
    {
        return $this->belongsTo(EmployeePosition::class);
    }

    public function level()
    {
        return $this->belongsTo(HierarchyLevel::class);
    }

    public function directManager()
    {
        return $this->belongsTo(User::class, 'direct_manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'direct_manager_id');
    }

    public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

}
