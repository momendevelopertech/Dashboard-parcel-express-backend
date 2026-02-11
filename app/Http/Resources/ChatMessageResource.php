<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chat_session_id' => $this->chat_session_id,
            'sender_type' => $this->sender_type,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender_name,
            'message' => $this->message,
            'message_type' => $this->message_type,
            'attachments' => $this->attachments,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'sender' => $this->when($this->relationLoaded('sender'), function () {
                return [
                    'id' => $this->sender?->id,
                    'name' => $this->sender?->name,
                ];
            })
        ];
    }
} 