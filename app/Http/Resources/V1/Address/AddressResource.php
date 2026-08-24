<?php

namespace App\Http\Resources\V1\Address;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'addressable_type' => $this->addressable_type,
            'addressable_id' => $this->addressable_id,
            'type' => $this->type,
            'label' => $this->label,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'city_id' => $this->city_id,
            'state' => $this->state,
            'state_code' => $this->state_code,
            'postal_code' => $this->postal_code,
            'country_id' => $this->country_id,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country->id,
                'name' => $this->country->name,
                'code' => $this->country->code,
            ]),
        ];
    }
}
