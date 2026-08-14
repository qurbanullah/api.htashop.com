<?php

namespace App\Http\Requests\V1\Value;

use App\Models\Definition;
use App\Models\Option;
use App\Support\Punchout\ValuableTypeRegistry;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValueStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'valuable_type' => ['required', 'string', Rule::in(ValuableTypeRegistry::aliases())],
            'valuable_uuid' => ['required', 'string', 'max:36'],
            'definition_id' => ['required', 'integer', 'exists:definitions,id'],
            'option_id' => ['nullable', 'integer', 'exists:options,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'value_text' => ['nullable', 'string'],
            'value_number' => ['nullable', 'numeric'],
            'value_boolean' => ['nullable', 'boolean'],
            'value_date' => ['nullable', 'date'],
            'value_datetime' => ['nullable', 'date'],
            'value_json' => ['nullable', 'array'],
            'locale' => ['nullable', 'string', 'max:20'],
            'channel' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $tenantId = (int) $this->input('tenant_id');
            $valuable = $this->resolveValuable();
            $definition = Definition::query()->find($this->input('definition_id'));
            $option = $this->input('option_id') ? Option::query()->find($this->input('option_id')) : null;
            $unit = $this->input('unit_id') ? Unit::query()->find($this->input('unit_id')) : null;

            if ($valuable && $this->valuableTenantId($valuable) !== $tenantId) {
                $validator->errors()->add('valuable_uuid', 'The selected record does not belong to the selected tenant.');
            }

            if ($definition && $definition->tenant_id !== $tenantId) {
                $validator->errors()->add('definition_id', 'The selected definition does not belong to the selected tenant.');
            }

            if ($definition && !$definition->supportsTargetType((string) $this->input('valuable_type'))) {
                $validator->errors()->add('valuable_type', 'This definition cannot be assigned to the selected record type.');
            }

            if ($option && $definition && $option->definition_id !== $definition->id) {
                $validator->errors()->add('option_id', 'The selected option does not belong to the selected definition.');
            }

            if ($unit && $unit->tenant_id !== $tenantId) {
                $validator->errors()->add('unit_id', 'The selected unit does not belong to the selected tenant.');
            }

            if ($unit && $definition && $definition->measurement_id && $unit->measurement_id !== $definition->measurement_id) {
                $validator->errors()->add('unit_id', 'The selected unit does not belong to the definition measurement.');
            }

            if (!$this->hasAnyValue()) {
                $validator->errors()->add('value_text', 'At least one value field is required.');
            }
        });
    }

    private function resolveValuable(): ?Model
    {
        return ValuableTypeRegistry::resolveModel($this->input('valuable_type'), $this->input('valuable_uuid'));
    }

    private function valuableTenantId(Model $valuable): ?int
    {
        return ValuableTypeRegistry::tenantIdFor($valuable);
    }

    private function hasAnyValue(): bool
    {
        return $this->filled('value_text') || !is_null($this->input('value_number')) || !is_null($this->input('value_boolean')) || $this->filled('value_date') || $this->filled('value_datetime') || !is_null($this->input('value_json')) || $this->filled('option_id');
    }
}
