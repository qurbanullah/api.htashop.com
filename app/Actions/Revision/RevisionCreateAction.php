<?php

namespace App\Actions\Revision;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RevisionCreateAction
{
    public function handle(Model $model, array $payload, array $options = []): Revision
    {
        $revisableType = get_class($model);
        $revisableId = $model->getKey();

        $revisionNumber = Revision::query()
            ->where('revisable_type', $revisableType)
            ->where('revisable_id', $revisableId)
            ->max('revision_number');

        $user = Auth::user();

        return Revision::create([
            'revisable_type' => $revisableType,
            'revisable_id' => $revisableId,
            'revision_number' => (int) $revisionNumber + 1,
            'revision_type' => data_get($options, 'revision_type'),
            'reason' => data_get($options, 'reason'),
            'payload' => $payload,
            'metadata' => data_get($options, 'metadata'),
            'created_by' => Auth::id(),
            'created_by_type' => $user instanceof Model ? $user->getMorphClass() : null,
        ]);
    }
}
