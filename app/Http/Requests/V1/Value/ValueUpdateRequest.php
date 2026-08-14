<?php

namespace App\Http\Requests\V1\Value;

use App\Models\Definition;
use App\Models\Option;
use App\Support\Punchout\ValuableTypeRegistry;
use App\Models\Unit;
use App\Models\Value;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValueUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'integer', 'exists:tenants,id'],
            'valuable_type' => ['sometimes', 'string', Rule::in(ValuableTypeRegistry::aliases())],
            'valuable_uuid' => ['sometimes', 'string', 'max:36'],
            'definition_id' => ['sometimes', 'integer', 'exists:definitions,id'],
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
            $value = Value::query()->with(['valuable', 'definition'])->find($this->route('id'));

            if (!$value) {
                return;
            }

            $tenantId = (int) ($this->input('tenant_id') ?? $value->tenant_id);
            $valuable = $this->resolveValuable($value);
            $definition = $this->filled('definition_id') ? Definition::query()->find($this->input('definition_id')) : $value->definition;
            $option = $this->filled('option_id') ? Option::query()->find($this->input('option_id')) : ($value->option_id ? Option::query()->find($value->option_id) : null);
            $unit = $this->filled('unit_id') ? Unit::query()->find($this->input('unit_id')) : ($value->unit_id ? Unit::query()->find($value->unit_id) : null);

            if ($valuable && $this->valuableTenantId($valuable) !== $tenantId) {
                $validator->errors()->add('valuable_uuid', 'The selected record does not belong to the selected tenant.');
            }

            if ($definition && $definition->tenant_id !== $tenantId) {
                $validator->errors()->add('definition_id', 'The selected definition does not belong to the selected tenant.');
            }

            if ($definition && !$definition->supportsTargetType($this->resolvedValuableType($valuable ?? $value->valuable))) {
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
        });
    }

    private function resolveValuable(Value $value): ?Model
    {
        $type = $this->input('valuable_type');
        $uuid = $this->input('valuable_uuid');

        if (!$type || !$uuid) {
            return $value->valuable;
        }

        return ValuableTypeRegistry::resolveModel($type, $uuid);
    }

    private function valuableTenantId(Model $valuable): ?int
    {
        return ValuableTypeRegistry::tenantIdFor($valuable);
    }

    private function resolvedValuableType(mixed $valuable): string
    {
        return ValuableTypeRegistry::aliasForModel($valuable);
    }
}
