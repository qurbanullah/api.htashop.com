<?php

namespace App\Http\Resources\V1\Punchout;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerPartyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'network_id' => $this->network_id,
            'duns' => $this->duns,
            'country' => $this->country,
            'metadata' => $this->metadata ?? [],
            'buyers_count' => $this->whenCounted('buyers'),
            'items_count' => $this->whenCounted('items'),
            'buyers' => $this->whenLoaded('buyers', fn () => $this->buyers->map(fn ($buyer) => [
                'uuid' => $buyer->uuid,
                'tenant_id' => $buyer->tenant_id,
                'name' => $buyer->name,
                'code' => $buyer->code,
            ])->values()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
