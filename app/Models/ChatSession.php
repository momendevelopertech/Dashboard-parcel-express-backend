<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    /** @use HasFactory<\Database\Factories\ChatSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'session_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'assigned_agent_id',
        'status',
        'priority',
        'initial_message',
        'tags',
        'ticket_id',
        'started_at',
        'ended_at',
        'notes',
        'rating',
        'feedback'
    ];

    protected $casts = [
        'tags' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    // Relationships
    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function contactHistories()
    {
        return $this->hasMany(ContactHistory::class, 'related_chat_session_id');
    }
    public function unreadMessages()
    {
        return $this->hasMany(ChatMessage::class)->where('is_read', false);
    }
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Accessors
    public function getDurationAttribute()
    {
        if ($this->started_at && $this->ended_at) {
            return $this->ended_at->diffInMinutes($this->started_at);
        }
        return null;
    }
}
