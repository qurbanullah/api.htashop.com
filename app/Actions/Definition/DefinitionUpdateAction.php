<?php

namespace App\Actions\Definition;

use App\Models\Definition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\Punchout\ValuableTypeRegistry;

class DefinitionUpdateAction
{
    public function handle(Definition $definition, array $data): Definition
    {
        return DB::transaction(function () use ($definition, $data): Definition {
            $applicableTypes = $this->resolveApplicableTypes($definition, $data);

            $definition->update([
                'tenant_id' => data_get($data, 'tenant_id', $definition->tenant_id),
                'parent_id' => array_key_exists('parent_id', $data) ? data_get($data, 'parent_id') : $definition->parent_id,
                'measurement_id' => array_key_exists('measurement_id', $data) ? data_get($data, 'measurement_id') : $definition->measurement_id,
                'unit_id' => array_key_exists('unit_id', $data) ? data_get($data, 'unit_id') : $definition->unit_id,
                'name' => data_get($data, 'name', $definition->name),
                'code' => $this->resolveCode($definition, data_get($data, 'code'), data_get($data, 'name', $definition->name), data_get($data, 'tenant_id', $definition->tenant_id)),
                'kind' => data_get($data, 'kind', $definition->kind),
                'value_type' => data_get($data, 'value_type', $definition->value_type),
                'group_name' => array_key_exists('group_name', $data) ? data_get($data, 'group_name') : $definition->group_name,
                'section_name' => array_key_exists('section_name', $data) ? data_get($data, 'section_name') : $definition->section_name,
                'is_required' => data_get($data, 'is_required', $definition->is_required),
                'is_filterable' => data_get($data, 'is_filterable', $definition->is_filterable),
                'is_searchable' => data_get($data, 'is_searchable', $definition->is_searchable),
                'is_multi' => data_get($data, 'is_multi', $definition->is_multi),
                'is_active' => data_get($data, 'is_active', $definition->is_active),
                'validation' => array_key_exists('validation', $data) ? data_get($data, 'validation') : $definition->validation,
                'config' => array_key_exists('config', $data) ? data_get($data, 'config') : $definition->config,
            ]);

            if (array_key_exists('applicable_types', $data) || !$definition->targets()->exists()) {
                $definition->targets()->delete();

                $definition->targets()->createMany(
                    collect($applicableTypes)
                        ->map(fn (string $type) => ['target_type' => $type])
                        ->all()
                );
            }

            if (array_key_exists('label_ids', $data) || array_key_exists('label_id', $data)) {
                $definition->labels()->sync($this->resolveLabelIds($data));
            }

            return $definition->load(['parent', 'measurement', 'unit.measurement', 'labels', 'targets']);
        });
    }

    private function resolveApplicableTypes(Definition $definition, array $data): array
    {
        if (array_key_exists('applicable_types', $data)) {
            return collect(data_get($data, 'applicable_types', []))
                ->filter(fn ($type) => in_array($type, ValuableTypeRegistry::aliases(), true))
                ->unique()
                ->values()
                ->all();
        }

        $existingTargets = $definition->targets()->pluck('target_type')->values()->all();

        if ($existingTargets !== []) {
            return $existingTargets;
        }

        return ['product'];
    }

    private function resolveCode(Definition $definition, ?string $code, string $name, int $tenantId): string
    {
        $candidate = Str::slug($code ?: $name, '_');
        $base = $candidate;
        $suffix = 1;

        while (Definition::query()->where('tenant_id', $tenantId)->where('code', $candidate)->where('id', '!=', $definition->id)->exists()) {
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
