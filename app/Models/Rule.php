<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'condition_type',
        'condition_value',
        'action_type',
        'action_payload',
        'status',
        'triggered_at'
    ];

    protected $casts = [
        'action_payload' => 'array',
        'triggered_at' => 'datetime'
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}
