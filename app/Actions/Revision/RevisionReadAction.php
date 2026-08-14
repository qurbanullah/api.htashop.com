<?php

namespace App\Actions\Revision;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class RevisionReadAction
{
    public function handle(Model $model, array $filters = []): LengthAwarePaginator
    {
        return Revision::query()
            ->where('revisable_type', get_class($model))
            ->where('revisable_id', $model->getKey())
            ->when(data_get($filters, 'revision_type'), fn ($query, $type) => $query->where('revision_type', $type))
            ->when(data_get($filters, 'reason'), fn ($query, $reason) => $query->where('reason', 'like', '%' . $reason . '%'))
            ->orderByDesc('revision_number')
            ->paginate((int) data_get($filters, 'per_page', 15));
    }
}
