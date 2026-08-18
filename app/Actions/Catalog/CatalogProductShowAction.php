<?php

namespace App\Actions\Catalog;

use App\Models\Product;

class CatalogProductShowAction
{
    public function handle(string $key): Product
    {
        return Product::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->with([
                'categories',
                'brands',
                'features',
                'manufacturers',
                'highlights' => fn ($query) => $query->orderByPivot('sort_order'),
                'dams' => fn ($query) => $query
                    ->whereIn('collection_name', ['featured', 'gallery'])
                    ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('sort_order'),
                'variants' => fn ($query) => $query
                    ->orderByDesc('is_default')
                    ->with(['dams' => fn ($damQuery) => $damQuery->where('collection_name', 'featured')]),
                'inventories.warehouse',
            ])
            ->where(function ($query) use ($key) {
                if ($this->isUuid($key)) {
                    $query->where('uuid', strtolower($key));

                    return;
                }

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
