<?php

namespace App\Http\Resources\V1\Bom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BomRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'reference_number' => 'BOM-'.$this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'title' => $this->title,
            'notes' => $this->notes,
            'status' => $this->status,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'uuid' => $line->uuid,
                'part_name' => $line->part_name,
                'part_number' => $line->part_number,
                'specification' => $line->specification,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'target_unit_price' => $line->target_unit_price !== null ? (float) $line->target_unit_price : null,
                'source_url' => $line->source_url,
                'notes' => $line->notes,
            ])->values()->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
