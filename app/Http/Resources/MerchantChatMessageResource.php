<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MerchantChatMessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'merchant_chat_session_id' => $this->merchant_chat_session_id,
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
        ];
    }
}
