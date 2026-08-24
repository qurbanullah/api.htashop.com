<?php

namespace App\Http\Resources\V1\Definition;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'kind' => $this->kind,
            'value_type' => $this->value_type,
            'display_type' => $this->display_type,
            'swatch_type' => $this->swatch_type,
            'group_name' => $this->group_name,
            'measurement_id' => $this->measurement_id,
            'unit_id' => $this->unit_id,
            'is_required' => $this->is_required,
            'is_filterable' => $this->is_filterable,
            'is_searchable' => $this->is_searchable,
            'is_multi' => $this->is_multi,
            'is_active' => $this->is_active,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'code' => $o->code,
                'metadata' => $o->metadata,
            ])),
        ];
    }
}
