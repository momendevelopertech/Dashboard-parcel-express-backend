<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'assigned_agent_id',
        'chat_session_id',
        'resolution',
        'attachments',
        'due_date',
        'resolved_at',
        'closed_at',
        'tags',
        'rating',
        'feedback',
        'internal_notes',
        'created_by'
    ];

    protected $casts = [
        'attachments' => 'array',
        'tags' => 'array',
        'due_date' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime'
    ];

    // Relationships
    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class);
    }

    public function contactHistories()
    {
        return $this->hasMany(ContactHistory::class, 'related_ticket_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'OPEN');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                     ->whereNotIn('status', ['RESOLVED', 'CLOSED']);
    }

    // Accessors
    public function getIsOverdueAttribute()
    {
        return $this->due_date && $this->due_date->isPast() && !in_array($this->status, ['RESOLVED', 'CLOSED']);
    }

    public function getResolutionTimeAttribute()
    {
        if ($this->resolved_at) {
            return $this->resolved_at->diffInHours($this->created_at);
        }
        return null;
    }
}
