<?php

namespace App\Actions\Price;

use App\Models\Price;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PriceCreateAction
{
    public function handle(array $data): Price
    {
        return DB::transaction(function () use ($data): Price {
            $priceable = $this->resolvePriceable(data_get($data, 'priceable_type'), data_get($data, 'priceable_uuid'));

            $price = $priceable->prices()->create([
                'tenant_id' => data_get($data, 'tenant_id'),
                'organization_id' => data_get($data, 'organization_id'),
                'contract_id' => data_get($data, 'contract_id'),
                'catalog_id' => data_get($data, 'catalog_id'),
                'currency_id' => data_get($data, 'currency_id'),
                'unit_id' => data_get($data, 'unit_id'),
                'type' => data_get($data, 'type', 'fixed'),
                'amount' => data_get($data, 'amount'),
                'min_quantity' => data_get($data, 'min_quantity', 1),
                'max_quantity' => data_get($data, 'max_quantity'),
                'starts_at' => data_get($data, 'starts_at'),
                'ends_at' => data_get($data, 'ends_at'),
                'priority' => data_get($data, 'priority', 0),
                'is_active' => data_get($data, 'is_active', true),
                'metadata' => data_get($data, 'metadata'),
            ]);

            return $price->load(['tenant', 'organization', 'contract', 'catalog', 'currency', 'unit', 'priceable']);
        });
    }

    private function resolvePriceable(string $type, string $uuid): Model
    {
        return match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->firstOrFail(),
            'variant' => Variant::query()->where('uuid', $uuid)->firstOrFail(),
            default => throw new \InvalidArgumentException('Unsupported priceable type.'),
        };
    }
}
