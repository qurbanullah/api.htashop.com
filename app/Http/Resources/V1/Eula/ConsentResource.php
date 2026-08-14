<?php

namespace App\Http\Resources\V1\Eula;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'eula' => $this->whenLoaded('eula', function () {
                return [
                    'id' => $this->eula->id,
                    'uuid' => $this->eula->uuid,
                    'version' => $this->eula->version,
                    'title' => $this->eula->title,
                    'software_id' => $this->eula->software_id,
                    'version_id' => $this->eula->version_id,
                    'software' => $this->eula->software ? [
                        'id' => $this->eula->software->id,
                        'name' => $this->eula->software->name,
                    ] : null,
                    'software_version' => $this->eula->version ? [
                        'id' => $this->eula->version->id,
                        'version' => $this->eula->version->version,
                    ] : null,
                ];
            }),
            'consentable_type' => $this->consentable_type,
            'consentable_id' => $this->consentable_id,
            'consentable' => $this->whenLoaded('consentable'),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'metadata' => $this->metadata,
            'accepted_at' => $this->accepted_at->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
