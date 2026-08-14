<?php

namespace App\Actions\Assignment;

use App\Models\Assignment;

class AssignmentDeleteAction
{
    public function handle(Assignment $assignment): bool
    {
        return (bool) $assignment->delete();
    }
}
