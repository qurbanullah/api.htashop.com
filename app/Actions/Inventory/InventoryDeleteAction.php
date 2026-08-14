<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;

class InventoryDeleteAction
{
    public function handle(Inventory $inventory): bool
    {
        return (bool) $inventory->delete();
    }
}
