<?php

namespace App\Services\Label;

use App\Actions\Label\LabelCreateAction;
use App\Actions\Label\LabelDeleteAction;
use App\Actions\Label\LabelReadAction;
use App\Actions\Label\LabelSearchByUuidAction;
use App\Actions\Label\LabelUpdateAction;
use App\Models\Label;
use Illuminate\Pagination\LengthAwarePaginator;

class LabelService
{
    public function create(array $data): Label
    {
        return (new LabelCreateAction())->handle($data);
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        return (new LabelReadAction())->handle($filters);
    }

    public function searchByUuid(string $uuid): Label
    {
        return (new LabelSearchByUuidAction())->handle($uuid);
    }

    public function update(Label $label, array $data): Label
    {
        return (new LabelUpdateAction())->handle($label, $data);
    }

    public function delete(Label $label): bool
    {
        return (new LabelDeleteAction())->handle($label);
    }
}
