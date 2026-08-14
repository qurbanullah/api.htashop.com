<?php

namespace App\Actions\Price;

use App\Models\Price;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Pagination\LengthAwarePaginator;

class PriceReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $resolved = $this->resolvePriceable(data_get($filters, 'priceable_type'), data_get($filters, 'priceable_uuid'));

        return Price::query()
            ->with(['tenant', 'organization', 'contract', 'catalog', 'currency', 'unit', 'priceable'])
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(data_get($filters, 'organization_id'), fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when(data_get($filters, 'contract_id'), fn ($query, $contractId) => $query->where('contract_id', $contractId))
            ->when(data_get($filters, 'catalog_id'), fn ($query, $catalogId) => $query->where('catalog_id', $catalogId))
            ->when(data_get($filters, 'currency_id'), fn ($query, $currencyId) => $query->where('currency_id', $currencyId))
            ->when(!is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->when($resolved, fn ($query) => $query->where('priceable_type', $resolved['type'])->where('priceable_id', $resolved['id']))
            ->orderByDesc('priority')
            ->orderBy('amount')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }

    private function resolvePriceable(?string $type, ?string $uuid): ?array
    {
        if (!$type || !$uuid) {
            return null;
        }

        $model = match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->first(),
            'variant' => Variant::query()->where('uuid', $uuid)->first(),
            default => null,
        };

        if (!$model) {
            return null;
        }

        return ['type' => $model::class, 'id' => $model->id];
    }
}
