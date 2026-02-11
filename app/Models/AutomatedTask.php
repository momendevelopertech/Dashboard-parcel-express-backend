<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomatedTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_name',
        'trigger_type',
        'trigger_display',
        'trigger_data',
        'action_type',
        'action_display',
        'action_data',
        'status'
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'action_data' => 'array',
        'status' => 'string'
    ];
}