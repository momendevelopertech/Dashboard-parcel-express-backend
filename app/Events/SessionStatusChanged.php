<?php

namespace App\Events;

use App\Models\ChatSession;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;
    public $statusType;
    public $ticket;
    public $assignedAgent;

    /**
     * Create a new event instance.
     */
    public function __construct(ChatSession $session, string $statusType, Ticket $ticket = null, User $assignedAgent = null)
    {
        $this->session = $session;
        $this->statusType = $statusType;
        $this->ticket = $ticket;
        $this->assignedAgent = $assignedAgent;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chat-admin-public'),
            new Channel('chat-session.' . $this->session->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ChatSessionStatusChanged';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'session' => $this->session,
            'status_type' => $this->statusType,
            'ticket' => $this->ticket,
            'assigned_agent' => $this->assignedAgent ?: $this->session->assignedAgent,
        ];
    }
} 