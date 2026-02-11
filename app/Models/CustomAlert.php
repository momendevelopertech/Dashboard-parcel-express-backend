<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomAlert extends Model
{
    protected $fillable = [
        'alert_name',
        'condition',
        'triggered_at',
        'status',
        'recipients',
        'notification_methods',
    ];

    protected $casts = [
        'triggered_at'         => 'datetime',
        'recipients'           => 'array',
        'notification_methods' => 'array',
    ];
}
