<?php

namespace App\Http\Requests\V1\Measurement;

use App\Models\Measurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeasurementUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $measurement = Measurement::query()->where('uuid', $this->route('uuid'))->first();
        $tenantId = (int) ($this->input('tenant_id') ?? $measurement?->tenant_id);

        return [
            'tenant_id' => ['sometimes', 'integer', 'exists:tenants,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('measurements', 'code')->ignore($measurement?->id)->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
