<?php

namespace App\Traits\Dam;

use App\Actions\Dam\SyncDamAssetCollectionsAction;
use App\Models\Dam;
use App\Models\DamCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait Damable
{
    /**
     * Polymorphic relation for DAM assets
     */
    public function dams(): MorphMany
    {
        /** @var Model $model */
        $model = $this;

        return $model->morphMany(Dam::class, 'damable');
    }

    /**
     * Attach a DAM asset to this model. Accepts array of attributes or an existing Dam instance.
     */
    public function attachDam($asset, string $collection = 'default')
    {
        $collectionKeys = [];

        if ($asset instanceof Dam) {
            $asset->damable()->associate($this);
            $asset->collection_name = $collection;
            $asset->save();

            $collectionKeys = [$collection];

            if ($asset->relationLoaded('collections')) {
                $collectionKeys = array_merge($collectionKeys, $asset->collections->pluck('key')->all());
            }

                app(SyncDamAssetCollectionsAction::class)->handle(
                    $asset,
                    $this->resolveDamCollections(array_unique($collectionKeys), $collection)
                );

            return $asset;
        }

        $data = $asset;
        $collectionKeys = data_get($data, 'collection_keys', []);
        unset($data['collection_keys']);
        $data['collection_name'] = $collection;

        $dam = $this->dams()->create($data);

        $keys = collect($collectionKeys)
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->prepend($collection)
            ->unique()
            ->values();

        app(SyncDamAssetCollectionsAction::class)->handle($dam, $this->resolveDamCollections($keys->all(), $collection));

        return $dam;
    }

    protected function resolveDamCollections(array $keys, string $assetTypeKey)
    {
        return collect($keys)
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->unique()
            ->map(function (string $key) use ($assetTypeKey) {
                return DamCollection::query()->firstOrCreate(
                    ['key' => $key],
                    [
                        'name' => Str::headline(str_replace(['_', '-'], ' ', $key)),
                        'kind' => $key === $assetTypeKey ? 'asset_type' : 'label',
                        'is_active' => true,
                        'is_system' => $key === $assetTypeKey,
                    ]
                );
            })
            ->values();
    }

    public function currentDamAssetsQuery(string $collection = 'default'): MorphMany
    {
        /** @var Model $model */
        $model = $this;

        return $model->morphMany(Dam::class, 'damable')
            ->where('collection_name', $collection)
            ->where('is_current', true)
            ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function currentDamAsset(string $collection = 'default', ?string $labelKey = null): ?Dam
    {
        return $this->currentDamAssetsQuery($collection)
            ->when($labelKey, fn ($query) => $query->whereHas('collections', fn ($collectionQuery) => $collectionQuery->where('key', $labelKey)))
            ->with('collections')
            ->first();
    }

    public function primaryDamAsset(string $collection = 'image'): ?Dam
    {
        return $this->currentDamAsset($collection, 'primary')
            ?? $this->currentDamAssetsQuery($collection)->with('collections')->first();
    }
}
