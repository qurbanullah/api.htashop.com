<?php

namespace App\Http\Resources\V1\Warehouse;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $address = $this->relationLoaded('address') ? $this->address : null;

        // The address row is what the editors write, so it wins; the flat columns
        // are a mirrored legacy view (kept in sync on every save) and are only a
        // fallback for rows created before address editing existed.
        $flat = fn (string $column, ?string $fromAddress): ?string => filled($fromAddress)
            ? $fromAddress
            : $this->{$column};

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'contact_name' => $flat('contact_name', $address?->contact_name),
            'email' => $flat('email', $address?->email),
            'phone' => $flat('phone', $address?->phone),
            'address_line_1' => $flat('address_line_1', $address?->address_line_1),
            'address_line_2' => $flat('address_line_2', $address?->address_line_2),
            'city' => $flat('city', $address?->city),
            'state' => $flat('state', $address?->state),
            'postal_code' => $flat('postal_code', $address?->postal_code),
            'country' => $flat('country', $address?->country?->name),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Polymorphic address (single, type=warehouse)
            'address' => $address ? [
                'id' => $address->id,
                'uuid' => $address->uuid,
                'label' => $address->label,
                'contact_name' => $address->contact_name,
                'email' => $address->email,
                'phone' => $address->phone,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'city_id' => $address->city_id,
                'state' => $address->state,
                'state_code' => $address->state_code,
                'postal_code' => $address->postal_code,
                'country_id' => $address->country_id,
                'country' => $address->country?->name,
            ] : null,
        ];
    }
}
