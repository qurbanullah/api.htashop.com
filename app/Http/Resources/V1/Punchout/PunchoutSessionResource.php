<?php

namespace App\Http\Resources\V1\Punchout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PunchoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'protocol' => $this->protocol,
            'status' => $this->status,
            'buyer_cookie' => $this->buyer_cookie,
            'return_url' => $this->return_url,
            'setup_payload' => $this->setup_payload,
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'last_activity_at' => optional($this->last_activity_at)?->toIso8601String(),
            'tenant' => [
                'uuid' => $this->tenant?->uuid,
                'slug' => $this->tenant?->slug,
                'name' => $this->tenant?->name,
            ],
        ];
    }
}
