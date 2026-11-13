<?php

namespace App\Http\Resources\V1\Measurement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'base_unit_id' => $this->base_unit_id,
            'base_unit' => $this->when(
                $this->relationLoaded('baseUnit') && $this->baseUnit !== null,
                fn (): array => [
                    'id' => $this->baseUnit->id,
                    'code' => $this->baseUnit->code,
                    'symbol' => $this->baseUnit->symbol,
                    'name' => $this->baseUnit->name,
                ],
            ),
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
