<?php

namespace App\Services\Revision;

use App\Actions\Revision\RevisionCreateAction;
use App\Actions\Revision\RevisionReadAction;
use App\Actions\Revision\RevisionRestoreAction;
use App\Actions\Revision\RevisionSearchByUuidAction;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class RevisionService
{
    public function __construct(
        protected RevisionCreateAction $createAction,
        protected RevisionReadAction $readAction,
        protected RevisionSearchByUuidAction $searchByUuidAction,
        protected RevisionRestoreAction $restoreAction,
    ) {
    }

    public function create(Model $model, array $payload, array $options = []): Revision
    {
        return $this->createAction->handle($model, $payload, $options);
    }

    public function read(Model $model, array $filters = []): LengthAwarePaginator
    {
        return $this->readAction->handle($model, $filters);
    }

    public function searchByUuid(string $uuid): Revision
    {
        return $this->searchByUuidAction->handle($uuid);
    }

    public function restore(Revision $revision): Model
    {
        return $this->restoreAction->handle($revision);
    }
}
