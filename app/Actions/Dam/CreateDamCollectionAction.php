<?php

namespace App\Actions\Dam;

use App\Models\DamCollection;
use Illuminate\Support\Str;

class CreateDamCollectionAction
{
    public function handle(array $data): DamCollection
    {
        return DamCollection::create([
            'uuid' => data_get($data, 'uuid', (string) Str::uuid()),
            'key' => data_get($data, 'key'),
            'name' => data_get($data, 'name'),
            'kind' => data_get($data, 'kind', 'label'),
            'description' => data_get($data, 'description'),
            'metadata' => data_get($data, 'metadata'),
            'is_active' => data_get($data, 'is_active', true),
            'is_system' => data_get($data, 'is_system', false),
        ]);
    }
}
