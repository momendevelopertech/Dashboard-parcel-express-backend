<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MerchantChatSessionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->merchant->name,
            'merchant_email' => $this->merchant->email,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'initial_message' => $this->initial_message,
            'assigned_agent_id' => $this->assigned_agent_id,
            'assigned_agent' => $this->assignedAgent ? $this->assignedAgent->name : null,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'duration' => $this->duration,
            'tags' => $this->tags,
            'notes' => $this->notes,
            'rating' => $this->rating,
            'feedback' => $this->feedback,
            'unread_messages_count' => $this->unreadMessages->count(),
            'messages' => MerchantChatMessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
