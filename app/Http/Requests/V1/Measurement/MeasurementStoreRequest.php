<?php

namespace App\Http\Requests\V1\Measurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeasurementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('measurements', 'code')->where(fn ($query) => $query->where('tenant_id', $this->input('tenant_id')))],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
