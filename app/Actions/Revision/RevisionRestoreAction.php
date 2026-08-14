<?php

namespace App\Actions\Revision;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RevisionRestoreAction
{
    public function handle(Revision $revision): Model
    {
        return DB::transaction(function () use ($revision): Model {
            $model = $revision->revisable;
            $payload = $revision->payload ?? [];

            if (! $model) {
                throw new \RuntimeException('Revision belongs to an unknown model.');
            }

            $attributes = Arr::only($payload, [
                'tenant_id',
                'organization_id',
                'product_id',
                'name',
                'slug',
                'status',
                'summary',
                'description',
                'configuration',
                'is_default',
                'is_active',
                'metadata',
            ]);

            if (! empty($attributes)) {
                $model->update($attributes);
            }

            foreach (['category_ids' => 'categories', 'feature_ids' => 'features', 'tag_ids' => 'tags'] as $payloadKey => $relation) {
                if (array_key_exists($payloadKey, $payload) && method_exists($model, $relation)) {
                    $model->{$relation}()->sync($payload[$payloadKey] ?? []);
                }
            }

            return $model->refresh();
        });
    }
}
