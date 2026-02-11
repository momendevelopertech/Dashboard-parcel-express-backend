<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_name',
        'action_type',
        'schedule_display',
        'cron_expression',
        'action_display',
        'action_payload',
        'last_run_at',
        'status'
    ];

    protected $casts = [
        'action_payload' => 'array',
        'last_run_at'    => 'datetime',
    ];
}
