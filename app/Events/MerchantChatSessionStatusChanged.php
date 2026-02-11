<?php

namespace App\Events;

use App\Models\MerchantChatSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantChatSessionStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;
    public $action;

    public function __construct(MerchantChatSession $session, $action)
    {
        $this->session = $session;
        $this->action = $action;
    }

    public function broadcastOn()
    {
        // Broadcast to both public sessions channel and admin channel
        return [
            new Channel('merchant-chat-sessions'),
            new PrivateChannel('chat-admin')
        ];
    }

    public function broadcastAs()
    {
        return 'merchant.session.status.changed';
    }
    public function broadcastWith()
    {
        return [
            'session_id' => $this->session->id,
            'merchant_id' => $this->session->merchant_id,
            'session_status' => $this->session->status,
            'action' => $this->action,
            'unread_count' => $this->session->unreadMessages()->count(),
            'updated_at' => $this->session->updated_at->toISOString()
        ];
    }
}
