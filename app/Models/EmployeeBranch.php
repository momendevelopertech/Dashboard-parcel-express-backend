<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'morphable_id',
        'morphable_type',
    ];

    public function getMorphableTypeAttribute()
    {
        return class_basename($this->attributes['morphable_type'] ?? '');
    }

    public function morphable()
    {
        return $this->morphTo();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
