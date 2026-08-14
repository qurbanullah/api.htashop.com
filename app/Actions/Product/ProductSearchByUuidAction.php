<?php

namespace App\Actions\Product;

use App\Models\Product;

class ProductSearchByUuidAction
{
    /**
     * Resolve a product by its full UUID or a public {slug}-{uuid8} route key.
     */
    public function handle(string $key): Product
    {
        return Product::query()
            ->with([
                'tenant.dams.collections',
                'organization.dams.collections',
                'dams.collections',
                'categories',
                'features',
                'tags',
                'manufacturers',
                'brands',
                'variants.dams.collections',
                'variants.categories',
                'variants.features',
                'variants.tags',
                'attributeValues.definition.measurement',
                'attributeValues.definition.labels',
                'attributeValues.option',
                'attributeValues.unit.measurement',
                'specificationValues.definition.measurement',
                'specificationValues.definition.labels',
                'specificationValues.option',
                'specificationValues.unit.measurement',
            ])
            ->where(function ($query) use ($key) {
                if ($this->isUuid($key)) {
                    $query->where('uuid', strtolower($key));

                    return;
                }

                // Composite {slug}-{uuid8} (or a bare uuid8 prefix): match the uuid prefix.
                $segments = explode('-', $key);
                $prefix = (string) end($segments);
                $query->where('uuid', 'like', $prefix . '%');
            })
            ->firstOrFail();
    }

    private function isUuid(string $key): bool
    {
        return preg_match('/^[0-9a-fA-F-]{36}$/', $key) === 1;
    }
}
