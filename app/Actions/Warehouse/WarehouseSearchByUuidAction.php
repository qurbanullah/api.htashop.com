<?php

namespace App\Actions\Warehouse;

use App\Models\Warehouse;

class WarehouseSearchByUuidAction
{
    public function handle(string $uuid): Warehouse
    {
        // The contact/address details live on the polymorphic address row, and the
        // resource flattens them onto the warehouse fields.
        return Warehouse::query()
            ->with('address.country')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
