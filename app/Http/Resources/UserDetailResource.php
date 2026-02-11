<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * This resource is used for detailed user views (like edit page)
     * where we need role and permission information.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // Add role with permissions if available
        if (isset($this->role)) {
            $data['role'] = $this->role;
        } elseif ($this->roles && $this->roles->isNotEmpty()) {
            $role = $this->roles->first();
            $data['role'] = [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions ?? []
            ];
        }

        return $data;
    }
}

