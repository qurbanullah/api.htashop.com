<?php

namespace App\Support\Punchout;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;

class ValuableTypeRegistry
{
    public static function aliases(): array
    {
        return array_keys(self::map());
    }

    public static function resolveModel(?string $alias, ?string $uuid): ?Model
    {
        if (!$alias || !$uuid) {
            return null;
        }

        $modelClass = self::map()[$alias] ?? null;

        if (!$modelClass) {
            return null;
        }

        return $modelClass::query()->where('uuid', $uuid)->first();
    }

    public static function resolveModelOrFail(string $alias, string $uuid): Model
    {
        $modelClass = self::map()[$alias] ?? null;

        if (!$modelClass) {
            throw new \InvalidArgumentException('Unsupported valuable type.');
        }

        return $modelClass::query()->where('uuid', $uuid)->firstOrFail();
    }

    public static function aliasForModel(Model|string $model): string
    {
        $modelClass = $model instanceof Model ? $model::class : $model;
        $alias = array_search($modelClass, self::map(), true);

        if (!is_string($alias)) {
            throw new \InvalidArgumentException('Unsupported valuable model.');
        }

        return $alias;
    }

    public static function tenantIdFor(Model $model): ?int
    {
        if (isset($model->tenant_id)) {
            return (int) $model->tenant_id;
        }

        if (method_exists($model, 'product')) {
            $product = $model->relationLoaded('product') ? $model->product : $model->product()->first();

            if ($product && isset($product->tenant_id)) {
                return (int) $product->tenant_id;
            }
        }

        return null;
    }

    private static function map(): array
    {
        return [
            'product' => Product::class,
            'variant' => Variant::class,
        ];
    }
}
