<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferAreaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ownership_id' => $this->ownership_id,
            'ownership_type' => $this->ownership_type,
            'source' => optional($this->ownership)->name,
            'owner_id' => $this->owner_id,
            'owner_type' => $this->owner_type,
            'owner' => optional($this->owner)->name,
            'shipments_count' => (int) $this->shipments_count,
        ];
    }
}
