<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;

class InventoryReadAction
{
    public function handle(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return Inventory::query()
            ->with(['warehouse', 'stockable'])
            ->when(data_get($filters, 'tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when(data_get($filters, 'stockable_type'), fn ($q, $t) => $q->where('stockable_type', $t))
            ->when(data_get($filters, 'stockable_id'), fn ($q, $id) => $q->where('stockable_id', $id))
            ->when(data_get($filters, 'warehouse_id'), fn ($q, $id) => $q->where('warehouse_id', $id))
            ->get();
    }
}
