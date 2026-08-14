<?php

namespace App\Http\Resources\V1\Dam\Concerns;

use App\Http\Resources\V1\Dam\DamAssetResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

trait SerializesDamMedia
{
    protected function serializePrimaryDamAsset($model, string $collection = 'image'): ?array
    {
        $model = $this->resolveDamMediaModel($model);

        if (! method_exists($model, 'primaryDamAsset')) {
            return null;
        }

        $asset = $model->primaryDamAsset($collection);

        if (! $asset) {
            return null;
        }

        return (new DamAssetResource($asset))->resolve();
    }

    protected function serializeDamMedia($model): array
    {
        $model = $this->resolveDamMediaModel($model);

        if (! $model->relationLoaded('dams')) {
            return [];
        }

        /** @var Collection<int, mixed> $assets */
        $assets = $model->dams
            ->where('is_current', true)
            ->sortBy([
                ['collection_name', 'asc'],
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return $assets
            ->groupBy('collection_name')
            ->map(fn (Collection $group) => DamAssetResource::collection($group)->resolve())
            ->all();
    }

    protected function resolveDamMediaModel($model)
    {
        return $model instanceof JsonResource ? $model->resource : $model;
    }
}
