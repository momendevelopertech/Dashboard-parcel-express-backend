<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'session_id',
        'subject',
        'status',
        'priority',
        'initial_message',
        'assigned_agent_id',
        'started_at',
        'ended_at',
        'tags',
        'notes',
        'rating',
        'feedback'
    ];

    protected $casts = [
        'tags' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function messages()
    {
        return $this->hasMany(MerchantChatMessage::class);
    }

    public function unreadMessages()
    {
        return $this->hasMany(MerchantChatMessage::class)->where('is_read', false);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function getDurationAttribute()
    {
        if ($this->started_at && $this->ended_at) {
            return $this->ended_at->diffInMinutes($this->started_at);
        }
        return null;
    }
}
