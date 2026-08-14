<?php

namespace App\Http\Resources\V1\Role;

use App\Http\Resources\V1\Permission\PermissionResource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Permissions - only include when loaded
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),

            // Counts - only include when requested
            'users_count' => $this->when(isset($this->users_count), $this->users_count),
        ];
    }
}
