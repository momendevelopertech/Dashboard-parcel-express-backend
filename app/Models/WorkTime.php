<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class WorkTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'check_in',
        'check_out',
        'total_hours',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'total_hours' => 'float',
    ];
    protected $appends = [
        'start_from',
    ];


    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getStartFromAttribute()
    {
        return $this->check_out === null ? now()->diff($this->check_in)->format('%H:%I:%S') : null;
    }
}
