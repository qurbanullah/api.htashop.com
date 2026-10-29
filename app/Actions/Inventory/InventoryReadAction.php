<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Collection;

class InventoryReadAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Inventory>
     */
    public function handle(array $filters = []): Collection
    {
        return Inventory::query()
            ->with(['warehouse', 'stockable'])
            ->when(data_get($filters, 'tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when(data_get($filters, 'tenant_ids'), fn ($q, $ids) => $q->whereIn('tenant_id', (array) $ids))
            ->when(data_get($filters, 'organization_ids'), function ($query, $organizationIds) {
                $organizationIds = array_map('intval', (array) $organizationIds);

                $query->where(function ($scope) use ($organizationIds) {
                    $scope->where(function ($productScope) use ($organizationIds) {
                        $productScope->whereIn('stockable_type', [Product::class, 'product'])
                            ->whereIn('stockable_id', Product::query()
                                ->select('id')
                                ->whereIn('organization_id', $organizationIds));
                    })->orWhere(function ($variantScope) use ($organizationIds) {
                        $variantScope->whereIn('stockable_type', [Variant::class, 'variant'])
                            ->whereIn('stockable_id', Variant::query()
                                ->select('id')
                                ->whereHas('product', fn ($productQuery) => $productQuery->whereIn('organization_id', $organizationIds)));
                    });
                });
            })
            ->when(data_get($filters, 'stockable_type'), fn ($q, $t) => $q->where('stockable_type', $t))
            ->when(data_get($filters, 'stockable_id'), fn ($q, $id) => $q->where('stockable_id', $id))
            ->when(data_get($filters, 'warehouse_id'), fn ($q, $id) => $q->where('warehouse_id', $id))
            ->get();
    }
}
