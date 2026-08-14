<?php

namespace App\Http\Requests\V1\Unit;

use App\Models\Measurement;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'measurement_id' => ['required', 'integer', 'exists:measurements,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('units', 'code')->where(fn ($query) => $query->where('measurement_id', $this->input('measurement_id')))],
            'symbol' => ['nullable', 'string', 'max:50'],
            'factor' => ['nullable', 'numeric'],
            'offset' => ['nullable', 'numeric'],
            'precision' => ['nullable', 'integer', 'min:0', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $measurement = Measurement::query()->find($this->input('measurement_id'));

            if ($measurement && $measurement->tenant_id !== (int) $this->input('tenant_id')) {
                $validator->errors()->add('measurement_id', 'The selected measurement does not belong to the selected tenant.');
            }
        });
    }
}
