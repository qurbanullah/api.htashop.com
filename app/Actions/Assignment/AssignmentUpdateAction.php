<?php

namespace App\Actions\Assignment;

use App\Models\Assignment;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AssignmentUpdateAction
{
    public function handle(Assignment $assignment, array $data): Assignment
    {
        return DB::transaction(function () use ($assignment, $data): Assignment {
            $assignable = null;

            if (array_key_exists('assignable_type', $data) || array_key_exists('assignable_uuid', $data)) {
                $assignable = $this->resolveAssignable(
                    data_get($data, 'assignable_type', $this->typeAlias($assignment->assignable_type)),
                    data_get($data, 'assignable_uuid', data_get($assignment->assignable, 'uuid'))
                );
            }

            if (data_get($data, 'is_primary', $assignment->is_primary)) {
                ($assignable ?? $assignment->assignable)->assignments()
                    ->where('role', data_get($data, 'role', $assignment->role))
                    ->where('id', '!=', $assignment->id)
                    ->update(['is_primary' => false]);
            }

            $attributes = [
                'tenant_id' => data_get($data, 'tenant_id', $assignment->tenant_id),
                'organization_id' => array_key_exists('organization_id', $data) ? data_get($data, 'organization_id') : $assignment->organization_id,
                'role' => array_key_exists('role', $data) ? data_get($data, 'role') : $assignment->role,
                'is_primary' => data_get($data, 'is_primary', $assignment->is_primary),
                'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $assignment->metadata,
            ];

            if ($assignable) {
                $attributes['assignable_type'] = $assignable::class;
                $attributes['assignable_id'] = $assignable->id;
            }

            $assignment->update($attributes);

            return $assignment->load(['tenant', 'organization', 'assignable']);
        });
    }

    private function resolveAssignable(string $type, string $uuid): Model
    {
        return match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->firstOrFail(),
            'variant' => Variant::query()->where('uuid', $uuid)->firstOrFail(),
            default => throw new \InvalidArgumentException('Unsupported assignable type.'),
        };
    }

    private function typeAlias(string $modelClass): string
    {
        return match ($modelClass) {
            Product::class => 'product',
            Variant::class => 'variant',
            default => throw new \InvalidArgumentException('Unsupported assignable model.'),
        };
    }
}
