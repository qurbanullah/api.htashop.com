<?php

namespace App\Http\Resources\V1\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'warehouse_id' => $this->warehouse_id,
            'stockable_type' => $this->stockable_type,
            'stockable_id' => $this->stockable_id,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'reserved' => $this->reserved,
            'available' => $this->getAvailableAttribute(),
            'low_stock_threshold' => $this->low_stock_threshold,
            'track_inventory' => $this->track_inventory,
            'is_active' => $this->is_active,
            'is_low_stock' => $this->isLowStock(),
            'warehouse' => $this->whenLoaded('warehouse'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
