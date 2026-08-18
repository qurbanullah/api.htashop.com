<?php

namespace App\Actions\Highlight;

use App\Models\Highlight;
use Illuminate\Pagination\LengthAwarePaginator;

class HighlightListAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $tenantId = data_get($filters, 'tenant_id');
        $scopeTenant = (bool) data_get($filters, 'scope_tenant', false);

        return Highlight::query()
            ->with('categories:id,name,slug')
            ->when(data_get($filters, 'only_active'), fn ($query) => $query->where('is_active', true))
            ->when($scopeTenant, function ($query) use ($tenantId) {
                // Global highlights are shared; merchant highlights are isolated
                // to their creating tenant.
                $query->where(function ($inner) use ($tenantId) {
                    $inner->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                });
            })
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('label', 'like', '%' . $search . '%')
                        ->orWhere('heading', 'like', '%' . $search . '%')
                        ->orWhere('body', 'like', '%' . $search . '%');
                });
            })
            ->when(! empty(data_get($filters, 'category_ids')), function ($query) use ($filters) {
                $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->whereIn('categories.id', data_get($filters, 'category_ids')));
            })
            ->orderBy('sort_order')
            ->orderBy('label')
            ->paginate((int) data_get($filters, 'per_page', 20));
    }
}
