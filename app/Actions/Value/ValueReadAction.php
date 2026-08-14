<?php

namespace App\Actions\Value;

use App\Models\Product;
use App\Models\Value;
use App\Models\Variant;
use App\Support\Punchout\ValuableTypeRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class ValueReadAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $resolved = $this->resolveValuable(data_get($filters, 'valuable_type'), data_get($filters, 'valuable_uuid'));

        return Value::query()
            ->with(['tenant', 'definition', 'option', 'unit', 'valuable'])
            ->when(data_get($filters, 'tenant_id'), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(data_get($filters, 'organization_id'), function ($query, $organizationId) {
                $query->where(function ($query) use ($organizationId) {
                    $query->where(function ($query) use ($organizationId) {
                        $query->where('valuable_type', Product::class)
                            ->whereExists(function ($exists) use ($organizationId) {
                                $exists->select(DB::raw(1))
                                    ->from('products')
                                    ->whereColumn('products.id', 'values.valuable_id')
                                    ->where('products.organization_id', $organizationId);
                            });
                    })
                    ->orWhere(function ($query) use ($organizationId) {
                        $query->where('valuable_type', Variant::class)
                            ->whereExists(function ($exists) use ($organizationId) {
                                $exists->select(DB::raw(1))
                                    ->from('variants')
                                    ->join('products', 'products.id', 'variants.product_id')
                                    ->whereColumn('variants.id', 'values.valuable_id')
                                    ->where('products.organization_id', $organizationId);
                            });
                    })
                    ->orWhereNotIn('valuable_type', [Product::class, Variant::class]);
                });
            })
            ->when(data_get($filters, 'definition_id'), fn ($query, $definitionId) => $query->where('definition_id', $definitionId))
            ->when(data_get($filters, 'locale'), fn ($query, $locale) => $query->where('locale', $locale))
            ->when(data_get($filters, 'channel'), fn ($query, $channel) => $query->where('channel', $channel))
            ->when($resolved, fn ($query) => $query->where('valuable_type', $resolved['type'])->where('valuable_id', $resolved['id']))
            ->orderByDesc('id')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }

    private function resolveValuable(?string $type, ?string $uuid): ?array
    {
        if (!$type || !$uuid) {
            return null;
        }

        $model = ValuableTypeRegistry::resolveModel($type, $uuid);

        if (!$model) {
            return null;
        }

        return ['type' => $model::class, 'id' => $model->id];
    }
}
