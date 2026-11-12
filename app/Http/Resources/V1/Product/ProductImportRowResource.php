<?php

namespace App\Http\Resources\V1\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One staged row. `errors` is the operator-facing report: a field → message map
 * that mirrors the importer's validation messages.
 */
class ProductImportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => (int) $this->row_number,
            'external_id' => $this->external_id,
            'status' => $this->status,
            'errors' => (object) ($this->errors ?? []),
            'warnings' => array_values((array) ($this->warnings ?? [])),
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'uuid' => $this->product->uuid,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ] : null),
            'raw' => $this->when($request->boolean('include_raw'), fn () => (object) ($this->raw ?? [])),
        ];
    }
}
