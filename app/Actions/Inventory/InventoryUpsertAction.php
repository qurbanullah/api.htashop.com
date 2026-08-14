<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;

class InventoryUpsertAction
{
    public function handle(array $data): Inventory
    {
        return Inventory::updateOrCreate(
            [
                'stockable_type' => $data['stockable_type'],
                'stockable_id' => $data['stockable_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
            ],
            $data
        );
    }
}
