<?php

namespace App\Http\Resources\V1\Quote;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResponseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'part_number' => $this->part_number,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount_amount' => (float) $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'line_total' => (float) $this->line_total,
            'lead_time_days' => $this->lead_time_days,
        ];
    }
}
