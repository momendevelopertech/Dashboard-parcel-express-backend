<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_chat_session_id',
        'sender_type',
        'sender_id',
        'sender_name',
        'message',
        'message_type',
        'attachments',
        'is_read',
        'read_at',
        'metadata'
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime'
    ];

    public function chatSession()
    {
        return $this->belongsTo(MerchantChatSession::class, 'merchant_chat_session_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
