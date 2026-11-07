<?php

namespace App\Http\Resources\V1\Shipping;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ] : null),

            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'metadata' => $this->metadata ?? [],

            'locations' => $this->whenLoaded('locations', fn () => $this->locations->map(fn ($location) => [
                'id' => $location->id,
                'type' => $location->type,
                'country_id' => $location->country_id,
                'country' => $location->relationLoaded('country') && $location->country
                    ? ['id' => $location->country->id, 'name' => $location->country->name, 'code' => $location->country->code]
                    : null,
                'city_id' => $location->city_id,
                'city' => $location->relationLoaded('city') && $location->city
                    ? ['id' => $location->city->id, 'name' => $location->city->name]
                    : null,
                'state' => $location->state,
                'postal_code_prefix' => $location->postal_code_prefix,
            ])->values()->all()),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
