<?php

namespace App\Http\Resources\V1\Warehouse;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $address = $this->relationLoaded('address') ? $this->address : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
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
            ] : null,
        ];
    }
}
