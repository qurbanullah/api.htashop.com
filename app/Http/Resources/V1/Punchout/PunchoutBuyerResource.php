<?php

namespace App\Http\Resources\V1\Punchout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PunchoutBuyerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id,
            'contract_id' => $this->contract_id,
            'name' => $this->name,
            'code' => $this->code,
            'protocol' => $this->protocol,
            'sender_identity' => $this->sender_identity,
            'from_identity' => $this->from_identity,
            'to_identity' => $this->to_identity,
            'oci_username' => $this->oci_username,
            'allowed_return_domains' => $this->allowed_return_domains ?? [],
            'default_currency' => $this->default_currency,
            'default_locale' => $this->default_locale,
            'settings' => $this->settings ?? [],
            'is_active' => (bool) $this->is_active,
            'has_shared_secret' => is_string($this->shared_secret_hash) && $this->shared_secret_hash !== '',
            'has_previous_shared_secret' => is_string($this->previous_shared_secret_hash) && $this->previous_shared_secret_hash !== '',
            'has_oci_password' => is_string($this->oci_password_hash) && $this->oci_password_hash !== '',
            'secret_rotated_at' => optional($this->secret_rotated_at)?->toIso8601String(),
            'last_seen_at' => optional($this->last_seen_at)?->toIso8601String(),
            'organization' => $this->whenLoaded('organization', fn () => $this->organization ? [
                'id' => $this->organization->id,
                'uuid' => $this->organization->uuid,
                'name' => $this->organization->name,
            ] : null),
            'contract' => $this->whenLoaded('contract', fn () => $this->contract ? [
                'id' => $this->contract->id,
                'uuid' => $this->contract->uuid,
                'name' => $this->contract->name,
                'code' => $this->contract->code,
                'status' => $this->contract->status,
            ] : null),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
