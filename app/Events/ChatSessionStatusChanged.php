<?php

namespace App\Events;

use App\Models\ChatSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatSessionStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chatSession;
    public $statusType;

    /**
     * Create a new event instance.
     */
    public function __construct(ChatSession $chatSession, string $statusType = 'updated')
    {
        $this->chatSession = $chatSession->load(['assignedAgent:id,name,email', 'ticket:id,ticket_number', 'messages' => function($query) {
            $query->latest()->limit(1);
        }]);
        $this->statusType = $statusType; // 'created', 'updated', 'closed', 'escalated', 'agent_assigned'
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chat-session.' . $this->chatSession->id),
            new PrivateChannel('chat-admin'), // For admin panel to show session updates
            new PrivateChannel('notifications'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'session.status.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'session' => [
                'id' => $this->chatSession->id,
                'session_id' => $this->chatSession->session_id,
                'customer_name' => $this->chatSession->customer_name,
                'customer_email' => $this->chatSession->customer_email,
                'customer_phone' => $this->chatSession->customer_phone,
                'status' => $this->chatSession->status,
                'priority' => $this->chatSession->priority,
                'unread_messages_count' => $this->chatSession->unread_messages_count ?? 0,
                'assigned_agent_id' => $this->chatSession->assigned_agent_id,
                'ticket_id' => $this->chatSession->ticket_id,
                'started_at' => $this->chatSession->started_at?->toISOString(),
                'ended_at' => $this->chatSession->ended_at?->toISOString(),
                'rating' => $this->chatSession->rating,
                'feedback' => $this->chatSession->feedback,
                'created_at' => $this->chatSession->created_at->toISOString(),
                'updated_at' => $this->chatSession->updated_at->toISOString(),
            ],
            'assigned_agent' => $this->chatSession->assignedAgent ? [
                'id' => $this->chatSession->assignedAgent->id,
                'name' => $this->chatSession->assignedAgent->name,
                'email' => $this->chatSession->assignedAgent->email,
            ] : null,
            'ticket' => $this->chatSession->ticket ? [
                'id' => $this->chatSession->ticket->id,
                'ticket_number' => $this->chatSession->ticket->ticket_number,
            ] : null,
            'status_type' => $this->statusType,
            'last_message' => $this->chatSession->messages->first() ? [
                'id' => $this->chatSession->messages->first()->id,
                'message' => $this->chatSession->messages->first()->message,
                'sender_type' => $this->chatSession->messages->first()->sender_type,
                'created_at' => $this->chatSession->messages->first()->created_at->toISOString(),
            ] : null,
        ];
    }
}
