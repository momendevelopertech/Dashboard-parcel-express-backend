<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ChatMessageResource;

class ChatSessionResource extends JsonResource
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
            'session_id' => $this->session_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'tracking_number' => $this->tracking_number,
            'assigned_agent_id' => $this->assigned_agent_id,
            'status' => $this->status,
            'priority' => $this->priority,
            'initial_message' => $this->initial_message,
            'tags' => $this->tags,
            'ticket_id' => $this->ticket_id,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'notes' => $this->notes,
            'rating' => $this->rating,
            'feedback' => $this->feedback,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'unread_messages_count' => $this->unread_messages_count ?? 0,
            // Relationships
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
            'ticket' => $this->when($this->relationLoaded('ticket') && $this->ticket, function () {
                return [
                    'id' => $this->ticket->id,
                    'ticket_number' => $this->ticket->ticket_number,
                ];
            }),
            'assigned_agent' => $this->when($this->relationLoaded('assignedAgent') && $this->assignedAgent, function () {
                return [
                    'id' => $this->assignedAgent->id,
                    'name' => $this->assignedAgent->name,
                    'email' => $this->assignedAgent->email,
                ];
            })
        ];
    }
}
