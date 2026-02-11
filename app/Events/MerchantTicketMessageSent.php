<?php

namespace App\Events;

use App\Models\MerchantTicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantTicketMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(MerchantTicketMessage $message)
    {
        info('📨 MerchantTicketMessageSent event triggered', [
            'message_id' => $message->id,
            'ticket_id' => $message->merchant_ticket_id,
            'sender_id' => $message->sender_id,
            'message_type' => $message->message_type,
            'will_broadcast_to' => 'merchant-ticket.' . $message->merchant_ticket_id
        ]);
        $this->message = $message->load(['sender:id,name', 'ticket:id,subject']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('merchant-ticket.' . $this->message->merchant_ticket_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $data = [
            'id' => $this->message->id,
            'merchant_ticket_id' => $this->message->merchant_ticket_id,
            'sender_id' => $this->message->sender_id,
            'message' => $this->message->message,
            'message_type' => $this->message->message_type,
            'attachments' => $this->message->attachments,
            'created_at' => $this->message->created_at,
            'sender' => $this->message->sender,
            'ticket' => $this->message->ticket,
        ];
        info('📨 MerchantTicketMessageSent event broadcasted', $data);
        return $data;
    }
}
