<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action_type',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
        'device',
        'is_suspicious'
    ];

    protected $casts = [
        'is_suspicious' => 'boolean',
        'details' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entity()
    {
        if ($this->entity_type && $this->entity_id) {
            return $this->morphTo(null, 'entity_type', 'entity_id');
        }
        return null;
    }
} 