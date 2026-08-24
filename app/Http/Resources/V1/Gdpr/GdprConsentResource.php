<?php

namespace App\Http\Resources\V1\Gdpr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GdprConsentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'consent_token' => $this->consent_token,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'categories' => $this->categories,
            'policy_version' => $this->policy_version,
            'source' => $this->source,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
