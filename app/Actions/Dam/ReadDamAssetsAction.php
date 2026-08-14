<?php

namespace App\Actions\Dam;

use App\Models\Dam;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReadDamAssetsAction
{
    public function handle(array $filters = []): Collection
    {
        return Dam::query()
            ->with('collections')
            ->when(data_get($filters, 'tenant_id'), function ($query, $tenantId) {
                $query->where(function ($query) use ($tenantId) {
                    $query->where(function ($query) use ($tenantId) {
                        $query->where('damable_type', Product::class)
                            ->whereExists(function ($exists) use ($tenantId) {
                                $exists->select(DB::raw(1))
                                    ->from('products')
                                    ->whereColumn('products.id', 'dams.damable_id')
                                    ->where('products.tenant_id', $tenantId);
                            });
                    })
                    ->orWhere(function ($query) use ($tenantId) {
                        $query->where('damable_type', Variant::class)
                            ->whereExists(function ($exists) use ($tenantId) {
                                $exists->select(DB::raw(1))
                                    ->from('variants')
                                    ->join('products', 'products.id', 'variants.product_id')
                                    ->whereColumn('variants.id', 'dams.damable_id')
                                    ->where('products.tenant_id', $tenantId);
                            });
                    })
                    ->orWhere(function ($query) use ($tenantId) {
                        $query->where('damable_type', Organization::class)
                            ->whereExists(function ($exists) use ($tenantId) {
                                $exists->select(DB::raw(1))
                                    ->from('organizations')
                                    ->whereColumn('organizations.id', 'dams.damable_id')
                                    ->where('organizations.tenant_id', $tenantId);
                            });
                    })
                    ->orWhere(function ($query) use ($tenantId) {
                        $query->where('damable_type', Tenant::class)
                            ->where('damable_id', $tenantId);
                    });
                });
            })
            ->when(data_get($filters, 'organization_id'), function ($query, $organizationId) {
                $query->where(function ($query) use ($organizationId) {
                    $query->where(function ($query) use ($organizationId) {
                        $query->where('damable_type', Product::class)
                            ->whereExists(function ($exists) use ($organizationId) {
                                $exists->select(DB::raw(1))
                                    ->from('products')
                                    ->whereColumn('products.id', 'dams.damable_id')
                                    ->where('products.organization_id', $organizationId);
                            });
                    })
                    ->orWhere(function ($query) use ($organizationId) {
                        $query->where('damable_type', Variant::class)
                            ->whereExists(function ($exists) use ($organizationId) {
                                $exists->select(DB::raw(1))
                                    ->from('variants')
                                    ->join('products', 'products.id', 'variants.product_id')
                                    ->whereColumn('variants.id', 'dams.damable_id')
                                    ->where('products.organization_id', $organizationId);
                            });
                    })
                    ->orWhere(function ($query) use ($organizationId) {
                        $query->where('damable_type', Organization::class)
                            ->where('damable_id', $organizationId);
                    });
                });
            })
            ->when(data_get($filters, 'damable_type'), fn ($query, $damableType) => $query->where('damable_type', $damableType))
            ->when(data_get($filters, 'damable_id'), fn ($query, $damableId) => $query->where('damable_id', $damableId))
            ->when(data_get($filters, 'collection_name'), fn ($query, $collectionName) => $query->where('collection_name', $collectionName))
            ->when(data_get($filters, 'collection_key'), function ($query, $collectionKey) {
                $query->whereHas('collections', fn ($collectionQuery) => $collectionQuery->where('key', $collectionKey));
            })
            ->orderBy('collection_name')
            ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderByDesc('is_current')
            ->orderBy('id')
            ->get();
    }
}
