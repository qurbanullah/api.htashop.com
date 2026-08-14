<?php

namespace App\Http\Requests\V1\Unit;

use App\Models\Measurement;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unit = Unit::query()->where('uuid', $this->route('uuid'))->first();
        $measurementId = (int) ($this->input('measurement_id') ?? $unit?->measurement_id);

        return [
            'tenant_id' => ['sometimes', 'integer', 'exists:tenants,id'],
            'measurement_id' => ['sometimes', 'integer', 'exists:measurements,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('units', 'code')->ignore($unit?->id)->where(fn ($query) => $query->where('measurement_id', $measurementId))],
            'symbol' => ['nullable', 'string', 'max:50'],
            'factor' => ['nullable', 'numeric'],
            'offset' => ['nullable', 'numeric'],
            'precision' => ['nullable', 'integer', 'min:0', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $unit = Unit::query()->where('uuid', $this->route('uuid'))->first();

            if (!$unit) {
                return;
            }

            $tenantId = (int) ($this->input('tenant_id') ?? $unit->tenant_id);
            $measurementId = (int) ($this->input('measurement_id') ?? $unit->measurement_id);
            $measurement = Measurement::query()->find($measurementId);

            if ($measurement && $measurement->tenant_id !== $tenantId) {
                $validator->errors()->add('measurement_id', 'The selected measurement does not belong to the selected tenant.');
            }
        });
    }
}
