<?php

namespace App\Actions\Price;

use App\Models\Price;

class PriceSearchByIdAction
{
    public function handle(int $id): Price
    {
        return Price::query()->with(['tenant', 'organization', 'contract', 'catalog', 'currency', 'unit', 'priceable'])->findOrFail($id);
    }
}
