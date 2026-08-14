<?php

namespace App\Actions\Dam;

use App\Models\Dam;
use App\Models\DamCollection;
use Illuminate\Support\Collection;

class SyncDamAssetCollectionsAction
{
    /**
     * @param  Collection<int, DamCollection>  $collections
     */
    public function handle(Dam $dam, Collection $collections): void
    {
        if ($collections->isEmpty()) {
            return;
        }

        $payload = $collections
            ->keyBy('id')
            ->map(fn () => ['sort_order' => $dam->sort_order])
            ->all();

        $dam->collections()->syncWithoutDetaching($payload);
    }
}
