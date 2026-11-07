<?php

namespace App\Services\Shipping;

use App\Models\PackagingTemplate;
use App\Services\Shipping\Concerns\ResolvesShippingScope;
use Illuminate\Pagination\LengthAwarePaginator;

class PackagingTemplateService
{
    use ResolvesShippingScope;

    private const RELATIONS = [
        'tenant',
        'packagingType',
        'dimensionUnit',
        'weightUnit',
        'tareWeightUnit',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PackagingTemplate>
     */
    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = PackagingTemplate::query()->with(self::RELATIONS);

        if ($user?->isAdmin() && data_get($filters, 'tenant_id') !== null) {
            $query->where('tenant_id', (int) data_get($filters, 'tenant_id'));
        }

        $this->applyReadScope($query, $user);

        return $query
            ->when(data_get($filters, 'is_active'), fn ($q) => $q->where('is_active', true))
            ->when(data_get($filters, 'search'), fn ($q, $search) => $q->where(
                fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"),
            ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) data_get($filters, 'per_page', 100));
    }

    public function searchByUuid(string $uuid): PackagingTemplate
    {
        return PackagingTemplate::query()
            ->with(self::RELATIONS)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PackagingTemplate
    {
        $data['tenant_id'] = $this->resolveWriteTenant($data, auth()->user());

        return PackagingTemplate::query()->create($data)->load(self::RELATIONS);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PackagingTemplate $template, array $data): PackagingTemplate
    {
        $user = auth()->user();

        if (! $user?->isAdmin()) {
            unset($data['tenant_id']);
        }

        $template->fill($data)->save();

        return $template->fresh(self::RELATIONS);
    }

    public function delete(PackagingTemplate $template): bool
    {
        return (bool) $template->delete();
    }
}
