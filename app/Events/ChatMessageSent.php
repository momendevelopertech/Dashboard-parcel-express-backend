<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(ChatMessage $message)
    {
        info('ChatMessageSent event constructor', ['message' => $message]);
        
        // Ensure all required relationships are loaded
        try {
            $this->message = $message->load(['sender:id,name', 'chatSession:id,session_id,customer_name,customer_email']);
        } catch (\Exception $e) {
            info('Error loading relationships for ChatMessageSent', ['error' => $e->getMessage()]);
            $this->message = $message;
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('chat-session.' . $this->message->chat_session_id),
            new PrivateChannel('chat-admin'), // For admin panel to show all messages
            new PrivateChannel('notifications'),
            // new Channel('chat-admin-public'), // TEMPORARY: Public channel for testing
        ];
        
        info('Broadcasting on channels.', ['channels' => $channels]);

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        info('Broadcasting with data.', ['data' => $this->message]);
        
        // Ensure attachments is always an array and safely serializable
        $attachments = $this->message->attachments;
        if (is_string($attachments)) {
            $attachments = json_decode($attachments, true) ?: [];
        }
        if (!is_array($attachments)) {
            $attachments = [];
        }
        
        return [
            'id' => $this->message->id,
            'chat_session_id' => $this->message->chat_session_id,
            'sender_type' => $this->message->sender_type,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender_name,
            'message' => $this->message->message,
            'message_type' => $this->message->message_type,
            'attachments' => $attachments,
            'created_at' => $this->message->created_at->toDateTimeString(),
            'sender' => $this->message->sender ? [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ] : null,
            'session' => $this->message->chatSession ? [
                'id' => $this->message->chatSession->id,
                'session_id' => $this->message->chatSession->session_id,
                'customer_name' => $this->message->chatSession->customer_name,
                'customer_email' => $this->message->chatSession->customer_email,
            ] : null
        ];
    }
}
