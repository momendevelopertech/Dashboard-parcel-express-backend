<?php

namespace App\Events;

use Illuminate\Support\Facades\Log;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('notifications.' . $this->notification->notifiable_id)  // Changed channel name pattern
        ];

        // Debug: Log the channels we're broadcasting to
        Log::info('Broadcasting NotificationCreated event to channels:', [
            'notification_id' => $this->notification->id,
            'notifiable_id' => $this->notification->notifiable_id,
            'channels' => array_map(function ($channel) {
                return $channel->name;
            }, $channels)
        ]);

        return $channels;
    }

    public function broadcastWith(): array
    {
        $notificationData = [];
        $rawData = $this->notification->data;
        if (is_array($rawData)) {
            $notificationData = $rawData;
        } elseif (is_string($rawData) && $rawData !== '') {
            $decoded = @json_decode($rawData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $notificationData = $decoded;
            }
        }
        if (!is_array($notificationData)) {
            $notificationData = [];
        }
        $data = [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'content' => $this->notification->content,
            'notifiable_type' => $this->notification->notifiable_type,
            'notifiable_id' => $this->notification->notifiable_id,
            'data' => $notificationData,
            'read_at' => $this->notification->read_at?->toISOString(),
            'created_at' => $this->notification->created_at->toISOString(),
            'updated_at' => $this->notification->updated_at->toISOString(),
        ];

        Log::info('Broadcasting notification data:', [
            'notification_id' => $this->notification->id,
            'data' => $data
        ]);

        return [
            'notification' => $data
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastWhen(): bool
    {
        return $this->notification->read_at === null;
    }
}
