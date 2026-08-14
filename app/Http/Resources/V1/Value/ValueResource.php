<?php

namespace App\Http\Resources\V1\Value;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'definition_id' => $this->definition_id,
            'option_id' => $this->option_id,
            'unit_id' => $this->unit_id,
            'value_text' => $this->value_text,
            'value_number' => $this->value_number,
            'value_boolean' => $this->value_boolean,
            'value_date' => $this->value_date?->toDateString(),
            'value_datetime' => $this->value_datetime?->toISOString(),
            'value_json' => $this->value_json,
            'normalized_number' => $this->normalized_number,
            'locale' => $this->locale,
            'channel' => $this->channel,
            'metadata' => $this->metadata,
            'definition' => $this->whenLoaded('definition', fn () => $this->definition ? [
                'id' => $this->definition->id,
                'uuid' => $this->definition->uuid,
                'name' => $this->definition->name,
                'display_label' => $this->definition->displayLabel(),
                'code' => $this->definition->code,
                'measurement_type' => $this->definition->measurementType(),
                'measurement' => $this->definition->measurementPayload(),
                'label' => $this->definition->labelPayload(),
                'labels' => $this->definition->labelsPayload(),
            ] : null),
            'valuable' => $this->whenLoaded('valuable', fn () => $this->valuable ? [
                'type' => $this->valuable instanceof Product ? 'product' : 'variant',
                'id' => $this->valuable->id,
                'uuid' => $this->valuable->uuid,
                'name' => $this->valuable->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
