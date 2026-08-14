<?php

namespace App\Actions\Dam;

use App\Models\DamCollection;

class UpdateDamCollectionAction
{
    public function handle(DamCollection $collection, array $data): DamCollection
    {
        $collection->update([
            'key' => data_get($data, 'key', $collection->key),
            'name' => data_get($data, 'name', $collection->name),
            'kind' => data_get($data, 'kind', $collection->kind),
            'description' => data_get($data, 'description', $collection->description),
            'metadata' => data_get($data, 'metadata', $collection->metadata),
            'is_active' => data_get($data, 'is_active', $collection->is_active),
            'is_system' => data_get($data, 'is_system', $collection->is_system),
        ]);

        return $collection->refresh();
    }
}
