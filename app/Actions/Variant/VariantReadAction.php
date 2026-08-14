<?php

namespace App\Actions\Variant;

use App\Models\Variant;
use Illuminate\Pagination\LengthAwarePaginator;

class VariantReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        return Variant::query()
            ->with([
                'dams.collections',
                'product.dams.collections',
                'product.tenant.dams.collections',
                'product.organization.dams.collections',
                'product',
                'categories',
                'features',
                'tags',
                'attributeValues.definition.measurement',
                'attributeValues.definition.labels',
                'attributeValues.option',
                'attributeValues.unit.measurement',
                'specificationValues.definition.measurement',
                'specificationValues.definition.labels',
                'specificationValues.option',
                'specificationValues.unit.measurement',
            ])
            ->when(data_get($filters, 'product_id'), fn ($query, $productId) => $query->where('product_id', $productId))
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->whereHas('product', fn ($productQuery) => $productQuery->where('tenant_id', $tenantId)))
            ->when(data_get($filters, 'organization_id'), fn ($query, $organizationId) => $query->whereHas('product', fn ($productQuery) => $productQuery->where('organization_id', $organizationId)))
            ->when(!is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->when(data_get($filters, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(data_get($filters, 'definition_group_name') || data_get($filters, 'definition_section_name') || data_get($filters, 'definition_kind'), function ($query) use ($filters) {
                $query->whereHas('values.definition', function ($definitionQuery) use ($filters) {
                    $definitionQuery
                        ->when(data_get($filters, 'definition_kind'), fn ($inner, $kind) => $inner->where('kind', $kind))
                        ->when(data_get($filters, 'definition_group_name'), fn ($inner, $groupName) => $inner->where('group_name', $groupName))
                        ->when(data_get($filters, 'definition_section_name'), fn ($inner, $sectionName) => $inner->where('section_name', $sectionName));
                });
            })
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('slug', 'like', '%' . $search . '%')
                        ->orWhere('summary', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }
}
