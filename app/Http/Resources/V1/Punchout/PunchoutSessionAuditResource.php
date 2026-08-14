<?php

namespace App\Http\Resources\V1\Punchout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PunchoutSessionAuditResource extends JsonResource
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
            'cart_items' => $this->cart_items,
            'cart_payload' => $this->cart_payload,
            'expires_at' => optional($this->expires_at)?->toIso8601String(),
            'started_at' => optional($this->started_at)?->toIso8601String(),
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
            'cart_returned_at' => optional($this->cart_returned_at)?->toIso8601String(),
            'last_activity_at' => optional($this->last_activity_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'tenant' => [
                'id' => $this->tenant?->id,
                'uuid' => $this->tenant?->uuid,
                'slug' => $this->tenant?->slug,
                'name' => $this->tenant?->name,
            ],
        ];
    }
}
