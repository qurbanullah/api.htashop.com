<?php

namespace App\Actions\Revision;

use App\Models\Revision;

class RevisionSearchByUuidAction
{
    public function handle(string $uuid): Revision
    {
        return Revision::query()
            ->with(['revisable', 'createdBy'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
