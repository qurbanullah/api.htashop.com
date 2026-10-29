<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;

class InventoryUpsertAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $legacyStockableTypes  Additional type values an
     *                                              existing row may have been stored under.
     */
    public function handle(array $data, array $legacyStockableTypes = []): Inventory
    {
        $stockableTypes = array_values(array_unique(array_filter(
            array_merge([$data['stockable_type']], $legacyStockableTypes)
        )));

        $inventory = Inventory::query()
            ->whereIn('stockable_type', $stockableTypes)
            ->where('stockable_id', $data['stockable_id'])
            ->when(
                ($data['warehouse_id'] ?? null) !== null,
                fn ($query) => $query->where('warehouse_id', $data['warehouse_id']),
                fn ($query) => $query->whereNull('warehouse_id')
            )
            ->first();

        if ($inventory) {
            $inventory->update($data);

            return $inventory->fresh();
        }

        return Inventory::create($data);
    }
}
