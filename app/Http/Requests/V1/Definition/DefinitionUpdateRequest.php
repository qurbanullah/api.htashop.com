<?php

namespace App\Http\Requests\V1\Definition;

use App\Models\Definition;
use App\Models\Label;
use App\Models\Measurement;
use App\Support\Punchout\ValuableTypeRegistry;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DefinitionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'integer', 'exists:tenants,id'],
            'parent_id' => ['nullable', 'integer', 'exists:definitions,id'],
            'measurement_id' => ['nullable', 'integer', 'exists:measurements,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'label_id' => ['nullable', 'integer', 'exists:labels,id'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'kind' => ['sometimes', 'string', 'max:100'],
            'value_type' => ['sometimes', 'string', 'max:100'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'section_name' => ['nullable', 'string', 'max:255'],
            'is_required' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'is_multi' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'applicable_types' => ['nullable', 'array'],
            'applicable_types.*' => ['string', Rule::in(ValuableTypeRegistry::aliases())],
            'validation' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $definition = Definition::query()->where('uuid', $this->route('uuid'))->first();

            if (!$definition) {
                return;
            }

            $tenantId = (int) ($this->input('tenant_id') ?? $definition->tenant_id);
            $parentId = $this->input('parent_id');
            $measurementId = $this->input('measurement_id', $definition->measurement_id);
            $unitId = $this->input('unit_id', $definition->unit_id);
            $labelIds = collect($this->input('label_ids', []));

            if ($this->filled('label_id')) {
                $labelIds->push((int) $this->input('label_id'));
            }

            if ($parentId) {
                $parent = Definition::query()->find($parentId);

                if ($parent && $parent->tenant_id !== $tenantId) {
                    $validator->errors()->add('parent_id', 'The selected parent definition does not belong to the selected tenant.');
                }
            }

            if ($measurementId) {
                $measurement = Measurement::query()->find($measurementId);

                if ($measurement && $measurement->tenant_id !== $tenantId) {
                    $validator->errors()->add('measurement_id', 'The selected measurement does not belong to the selected tenant.');
                }
            }

            if ($unitId && $measurementId) {
                $unit = Unit::query()->find($unitId);

                if ($unit && $unit->measurement_id !== (int) $measurementId) {
                    $validator->errors()->add('unit_id', 'The selected unit does not belong to the selected measurement.');
                }

                if ($unit && $unit->tenant_id !== $tenantId) {
                    $validator->errors()->add('unit_id', 'The selected unit does not belong to the selected tenant.');
                }
            }

            if ($labelIds->isNotEmpty()) {
                $invalidLabelIds = Label::query()
                    ->whereIn('id', $labelIds->unique()->values())
                    ->where('tenant_id', '!=', $tenantId)
                    ->pluck('id')
                    ->all();

                if ($invalidLabelIds !== []) {
                    $validator->errors()->add('label_ids', 'One or more selected labels do not belong to the selected tenant.');
                }
            }
        });
    }
}
