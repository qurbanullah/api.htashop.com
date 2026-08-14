<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;

class InventoryUpdateAction
{
    public function handle(Inventory $inventory, array $data): Inventory
    {
        $inventory->update($data);
        return $inventory->fresh();
    }
}
