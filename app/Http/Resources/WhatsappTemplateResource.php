<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsappTemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        preg_match_all('/\{\{(.*?)\}\}/', $this->message, $matches);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'message' => $this->message,
            'variables' => isset($matches[1]) ? $matches[1] : []
           
        ];
    }
}
