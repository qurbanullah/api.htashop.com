<?php

namespace App\Services\Punchout;

use App\Actions\Punchout\PunchoutSessionReadAction;
use App\Actions\Punchout\PunchoutSessionSearchByUuidAction;
use App\Models\PunchoutSession;
use Illuminate\Pagination\LengthAwarePaginator;

class PunchoutSessionService
{
    public function __construct(
        protected PunchoutSessionReadAction $readAction,
        protected PunchoutSessionSearchByUuidAction $searchByUuidAction,
    ) {
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        return $this->readAction->handle($filters);
    }

    public function searchByUuid(string $uuid): PunchoutSession
    {
        return $this->searchByUuidAction->handle($uuid);
    }
}
