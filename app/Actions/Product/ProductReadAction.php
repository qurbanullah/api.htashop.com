<?php

namespace App\Actions\Product;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
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
                'variants',
                'attributeValues.definition.measurement',
                'attributeValues.definition.labels',
                'attributeValues.option',
                'attributeValues.unit.measurement',
                'specificationValues.definition.measurement',
                'specificationValues.definition.labels',
                'specificationValues.option',
                'specificationValues.unit.measurement',
            ])
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(data_get($filters, 'organization_id'), fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when(!is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->when(data_get($filters, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(data_get($filters, 'category_id'), fn ($query, $categoryId) => $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->where('categories.id', $categoryId)))
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
                        ->orWhere('summary', 'like', '%' . $search . '%')
                        ->orWhere('sku', 'like', '%' . $search . '%')
                        ->orWhere('seller_sku', 'like', '%' . $search . '%')
                        ->orWhere('part_number', 'like', '%' . $search . '%')
                        ->orWhere('barcode', 'like', '%' . $search . '%')
                        ->orWhere('model_number', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }
}
