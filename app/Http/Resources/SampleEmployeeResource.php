<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SampleEmployeeResource extends JsonResource
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
            'name' => $this->getEmployableName(),
        ];
    }

    public function getEmployableName(): string
    {
        if (!$this->employable_type) {
            return '';
        }

        if ($this->employable_type === 'User') {
            return $this->employable ? ($this->employable->name ?? '') : '';
        }

        if ($this->employable_type === 'Driver') {
            return $this->employable ? ($this->employable->user->name ?? '') : '';
        }

        return '';
    }
}
