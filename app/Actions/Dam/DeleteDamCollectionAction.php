<?php

namespace App\Actions\Dam;

use App\Models\DamCollection;
use Illuminate\Validation\ValidationException;

class DeleteDamCollectionAction
{
    public function handle(DamCollection $collection): void
    {
        if ($collection->dams()->exists()) {
            throw ValidationException::withMessages([
                'collection' => ['DAM collection is attached to one or more assets and cannot be deleted.'],
            ]);
        }

        $collection->delete();
    }
}
