<?php

namespace App\Http\Resources\V1\Manufacturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'type' => $this->type,
            'logo' => $this->logo,
            'website' => $this->website,
            'country' => $this->country,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ];
    }
}
