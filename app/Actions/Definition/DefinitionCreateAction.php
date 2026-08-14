<?php

namespace App\Actions\Definition;

use App\Models\Definition;
use App\Support\Punchout\ValuableTypeRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DefinitionCreateAction
{
    public function handle(array $data): Definition
    {
        return DB::transaction(function () use ($data): Definition {
            $applicableTypes = $this->resolveApplicableTypes($data);

            $definition = Definition::create([
                'tenant_id' => data_get($data, 'tenant_id'),
                'parent_id' => data_get($data, 'parent_id'),
                'measurement_id' => data_get($data, 'measurement_id'),
                'unit_id' => data_get($data, 'unit_id'),
                'name' => data_get($data, 'name'),
                'code' => $this->resolveCode(data_get($data, 'code'), data_get($data, 'name'), data_get($data, 'tenant_id')),
                'kind' => data_get($data, 'kind', 'attribute'),
                'value_type' => data_get($data, 'value_type', 'text'),
                'group_name' => data_get($data, 'group_name'),
                'section_name' => data_get($data, 'section_name'),
                'is_required' => data_get($data, 'is_required', false),
                'is_filterable' => data_get($data, 'is_filterable', false),
                'is_searchable' => data_get($data, 'is_searchable', false),
                'is_multi' => data_get($data, 'is_multi', false),
                'is_active' => data_get($data, 'is_active', true),
                'validation' => data_get($data, 'validation'),
                'config' => data_get($data, 'config'),
            ]);

            $definition->targets()->createMany(
                collect($applicableTypes)
                    ->map(fn (string $type) => ['target_type' => $type])
                    ->all()
            );

            $definition->labels()->sync($this->resolveLabelIds($data));

            return $definition->load(['parent', 'measurement', 'unit.measurement', 'labels', 'targets']);
        });
    }

    private function resolveApplicableTypes(array $data): array
    {
        if (array_key_exists('applicable_types', $data)) {
            return collect(data_get($data, 'applicable_types', []))
                ->filter(fn ($type) => in_array($type, ValuableTypeRegistry::aliases(), true))
                ->unique()
                ->values()
                ->all();
        }

        return ['product'];
    }

    private function resolveCode(?string $code, string $name, int $tenantId): string
    {
        $candidate = Str::slug($code ?: $name, '_');
        $base = $candidate;
        $suffix = 1;

        while (Definition::query()->where('tenant_id', $tenantId)->where('code', $candidate)->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function resolveLabelIds(array $data): array
    {
        return collect(data_get($data, 'label_ids', []))
            ->when(data_get($data, 'label_id'), fn ($collection, $labelId) => $collection->push($labelId))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
