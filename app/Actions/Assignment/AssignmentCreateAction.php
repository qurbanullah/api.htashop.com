<?php

namespace App\Actions\Assignment;

use App\Models\Assignment;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AssignmentCreateAction
{
    public function handle(array $data): Assignment
    {
        return DB::transaction(function () use ($data): Assignment {
            $assignable = $this->resolveAssignable(data_get($data, 'assignable_type'), data_get($data, 'assignable_uuid'));

            if (data_get($data, 'is_primary', false)) {
                $assignable->assignments()
                    ->where('role', data_get($data, 'role'))
                    ->update(['is_primary' => false]);
            }

            $assignment = $assignable->assignments()->create([
                'tenant_id' => data_get($data, 'tenant_id'),
                'organization_id' => data_get($data, 'organization_id'),
                'role' => data_get($data, 'role'),
                'is_primary' => data_get($data, 'is_primary', false),
                'metadata' => data_get($data, 'metadata'),
            ]);

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
}
