<?php

namespace App\Services\Dam;

use App\Actions\Dam\CreateDamCollectionAction;
use App\Actions\Dam\DeleteDamCollectionAction;
use App\Actions\Dam\ListDamCollectionsAction;
use App\Actions\Dam\UpdateDamCollectionAction;
use App\Models\DamCollection;
use Illuminate\Database\Eloquent\Collection;

class DamCollectionService
{
    public function __construct(
        protected CreateDamCollectionAction $createDamCollectionAction,
        protected DeleteDamCollectionAction $deleteDamCollectionAction,
        protected ListDamCollectionsAction $listDamCollectionsAction,
        protected UpdateDamCollectionAction $updateDamCollectionAction,
    ) {
    }

    public function list(array $filters = []): Collection
    {
        return $this->listDamCollectionsAction->handle($filters);
    }

    public function create(array $data): DamCollection
    {
        return $this->createDamCollectionAction->handle($data);
    }

    public function update(DamCollection $collection, array $data): DamCollection
    {
        return $this->updateDamCollectionAction->handle($collection, $data);
    }

    public function delete(DamCollection $collection): void
    {
        $this->deleteDamCollectionAction->handle($collection);
    }
}
