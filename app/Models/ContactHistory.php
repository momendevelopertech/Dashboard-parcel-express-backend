<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactHistory extends Model
{
    /** @use HasFactory<\Database\Factories\ContactHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'interaction_type',
        'method',
        'channel',
        'summary',
        'details',
        'status',
        'handled_by',
        'related_ticket_id',
        'related_chat_session_id',
        'contacted_at',
        'tags',
        'outcome',
        'follow_up_required',
        'follow_up_date'
    ];

    protected $casts = [
        'tags' => 'array',
        'follow_up_required' => 'boolean',
        'contacted_at' => 'datetime',
        'follow_up_date' => 'datetime'
    ];

    // Relationships
    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function relatedTicket()
    {
        return $this->belongsTo(Ticket::class, 'related_ticket_id');
    }

    public function relatedChatSession()
    {
        return $this->belongsTo(ChatSession::class, 'related_chat_session_id');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('method', $method);
    }

    public function scopeNeedingFollowUp($query)
    {
        return $query->where('follow_up_required', true)
                     ->where('follow_up_date', '<=', now());
    }

    public function scopeByCustomer($query, $email = null, $phone = null)
    {
        return $query->where(function($q) use ($email, $phone) {
            if ($email) {
                $q->orWhere('customer_email', $email);
            }
            if ($phone) {
                $q->orWhere('customer_phone', $phone);
            }
        });
    }
}
