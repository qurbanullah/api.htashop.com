<?php

namespace App\Actions\Dam;

use App\Models\DamCollection;
use Illuminate\Database\Eloquent\Collection;

class ListDamCollectionsAction
{
    public function handle(array $filters = []): Collection
    {
        return DamCollection::query()
            ->when(data_get($filters, 'kind'), fn ($query, $kind) => $query->where('kind', $kind))
            ->when(! is_null(data_get($filters, 'is_active')), fn ($query) => $query->where('is_active', (bool) data_get($filters, 'is_active')))
            ->when(data_get($filters, 'search'), function ($query, $search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('key', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('kind')
            ->orderBy('name')
            ->get();
    }
}
