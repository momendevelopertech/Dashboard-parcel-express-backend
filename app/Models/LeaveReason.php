<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaveReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_en',
        'name_ar',
        'description',
        'is_active',
    ];

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
