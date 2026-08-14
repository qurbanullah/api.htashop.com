<?php

namespace App\Actions\Price;

use App\Models\Price;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PriceUpdateAction
{
    public function handle(Price $price, array $data): Price
    {
        return DB::transaction(function () use ($price, $data): Price {
            $priceable = null;

            if (array_key_exists('priceable_type', $data) || array_key_exists('priceable_uuid', $data)) {
                $priceable = $this->resolvePriceable(
                    data_get($data, 'priceable_type', $this->typeAlias($price->priceable_type)),
                    data_get($data, 'priceable_uuid', data_get($price->priceable, 'uuid'))
                );
            }

            $attributes = [
                'tenant_id' => data_get($data, 'tenant_id', $price->tenant_id),
                'organization_id' => data_get($data, 'organization_id', $price->organization_id),
                'contract_id' => array_key_exists('contract_id', $data) ? data_get($data, 'contract_id') : $price->contract_id,
                'catalog_id' => array_key_exists('catalog_id', $data) ? data_get($data, 'catalog_id') : $price->catalog_id,
                'currency_id' => data_get($data, 'currency_id', $price->currency_id),
                'unit_id' => array_key_exists('unit_id', $data) ? data_get($data, 'unit_id') : $price->unit_id,
                'type' => data_get($data, 'type', $price->type),
                'amount' => data_get($data, 'amount', $price->amount),
                'min_quantity' => data_get($data, 'min_quantity', $price->min_quantity),
                'max_quantity' => array_key_exists('max_quantity', $data) ? data_get($data, 'max_quantity') : $price->max_quantity,
                'starts_at' => array_key_exists('starts_at', $data) ? data_get($data, 'starts_at') : $price->starts_at,
                'ends_at' => array_key_exists('ends_at', $data) ? data_get($data, 'ends_at') : $price->ends_at,
                'priority' => data_get($data, 'priority', $price->priority),
                'is_active' => data_get($data, 'is_active', $price->is_active),
                'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $price->metadata,
            ];

            if ($priceable) {
                $attributes['priceable_type'] = $priceable::class;
                $attributes['priceable_id'] = $priceable->id;
            }

            $price->update($attributes);

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

    private function typeAlias(string $modelClass): string
    {
        return match ($modelClass) {
            Product::class => 'product',
            Variant::class => 'variant',
            default => throw new \InvalidArgumentException('Unsupported priceable model.'),
        };
    }
}
