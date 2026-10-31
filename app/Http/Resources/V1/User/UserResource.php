<?php

namespace App\Http\Resources\V1\User;

use App\Http\Resources\V1\Permission\PermissionResource;
use App\Http\Resources\V1\Profile\ProfileResource;
use App\Http\Resources\V1\Role\RoleResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Ensure roles are loaded for authentication context
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        // Ensure avatars are loaded
        if (! $this->relationLoaded('avatars')) {
            $this->load('avatars');
        }

        // Get role names as array
        $roleNames = $this->roles->pluck('name')->toArray();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'status' => $this->status ?? User::STATUS_ACTIVE,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'is_active' => ($this->status ?? User::STATUS_ACTIVE) === User::STATUS_ACTIVE,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'last_login_ip' => $this->last_login_ip,
            'avatar_url' => $this->getAvatarUrl('medium'),
            'avatar_urls' => $this->getAvatarUrls(),
            'onboarding_completed' => $this->onboarding_completed,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'deleted_by_name' => $this->deleted_by_name,
            'restored_at' => $this->restored_at?->toISOString(),

            // Authentication context - always include
            'roles' => $roleNames,
            'can_access_admin' => in_array('admin', $roleNames) || in_array('super-admin', $roleNames),
            'can_access_manage' => ! empty($roleNames), // Any authenticated user with roles can access manage

            // Detailed role resources - only include when explicitly loaded
            'role_details' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'academic_profile' => new ProfileResource($this->whenLoaded('academicProfile')),

            // Counts - only include when loaded
            'reviews_count' => $this->when(isset($this->reviews_count), $this->reviews_count),
            'review_assignments_count' => $this->when(isset($this->review_assignments_count), $this->review_assignments_count),
        ];
    }
}
