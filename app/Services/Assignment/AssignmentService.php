<?php

namespace App\Services\Assignment;

use App\Actions\Assignment\AssignmentCreateAction;
use App\Actions\Assignment\AssignmentDeleteAction;
use App\Actions\Assignment\AssignmentReadAction;
use App\Actions\Assignment\AssignmentSearchByIdAction;
use App\Actions\Assignment\AssignmentUpdateAction;
use App\Models\Assignment;
use Illuminate\Pagination\LengthAwarePaginator;

class AssignmentService
{
    public function create(array $data): Assignment
    {
        return (new AssignmentCreateAction())->handle($data);
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        return (new AssignmentReadAction())->handle($filters);
    }

    public function searchById(int $id): Assignment
    {
        return (new AssignmentSearchByIdAction())->handle($id);
    }

    public function update(Assignment $assignment, array $data): Assignment
    {
        return (new AssignmentUpdateAction())->handle($assignment, $data);
    }

    public function delete(Assignment $assignment): bool
    {
        return (new AssignmentDeleteAction())->handle($assignment);
    }
}
