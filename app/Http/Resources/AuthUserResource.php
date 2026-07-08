<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
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
            'role' => $this->whenLoaded('role', function () {
                return [
                    'id' => $this->role->id,
                    'role_name' => $this->role->role_name,
                    'permissions' => PermissionResource::collection($this->role->permissions),
                ];
            }),
            'name' => $this->name,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'contact_number' => $this->contact_number,
            'is_active' => $this->is_active,
            'last_login' => $this->last_login,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'google_id' => $this->google_id,
            'theme' => $this->theme,
        ];
    }
}
