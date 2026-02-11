<?php

namespace App\Events;

use App\Models\MerchantTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantTicketChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticket;
    public $changeType;

    /**
     * Create a new event instance.
     */
    public function __construct(MerchantTicket $ticket, string $changeType = 'updated')
    {
        info('🎫 MerchantTicketChanged event triggered', [
            'ticket_id' => $ticket->id, 
            'change_type' => $changeType,
            'ticket_status' => $ticket->status,
            'will_broadcast_to' => 'merchant-tickets'
        ]);
        $this->ticket = $ticket->load(['merchant:id,name,email']);
        $this->changeType = $changeType; // 'created', 'closed', 'updated'
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('merchant-tickets'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket' => [
                'id' => $this->ticket->id,
                'merchant_id' => $this->ticket->merchant_id,
                'subject' => $this->ticket->subject,
                'status' => $this->ticket->status,
                'initial_message' => $this->ticket->initial_message,
                'created_at' => $this->ticket->created_at,
                'updated_at' => $this->ticket->updated_at,
                'merchant' => $this->ticket->merchant,
            ],
            'change_type' => $this->changeType,
        ];
    }
}
