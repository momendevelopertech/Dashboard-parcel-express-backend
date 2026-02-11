<?php

namespace App\Events;

use App\Models\MerchantChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(MerchantChatMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        // Broadcast to both private session channel and admin channel
        return [
            new PrivateChannel('merchant-chat.' . $this->message->merchant_chat_session_id),
            new PrivateChannel('chat-admin')
        ];
    }

    public function broadcastAs()
    {
        return 'merchant.message.sent';
    }

    public function broadcastWith()
    {
        info($this->message);
        return [
            'id' => $this->message->id,
            'merchant_chat_session_id' => $this->message->merchant_chat_session_id,
            'merchant_id' => $this->message->chatSession->merchant_id, // ✨ هذا هو التعديل
            'sender_type' => $this->message->sender_type,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender_name,
            'message' => $this->message->message,
            'message_type' => $this->message->message_type,
            'attachments' => $this->message->attachments ?? [],
            'is_read' => $this->message->is_read,
            'read_at' => $this->message->read_at,
            'created_at' => $this->message->created_at->toISOString(),
            'updated_at' => $this->message->updated_at->toISOString(),
        ];
    }
}
