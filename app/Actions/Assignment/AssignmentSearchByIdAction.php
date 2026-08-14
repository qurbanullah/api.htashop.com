<?php

namespace App\Actions\Assignment;

use App\Models\Assignment;

class AssignmentSearchByIdAction
{
    public function handle(int $id): Assignment
    {
        return Assignment::query()
            ->with(['tenant', 'organization', 'assignable'])
            ->findOrFail($id);
    }
}
