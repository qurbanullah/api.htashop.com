<?php

namespace App\Http\Requests\V1\Label;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabelStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->input('tenant_id');

        return [
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('labels', 'slug')->where(function ($query) use ($tenantId) {
                if (is_null($tenantId)) {
                    return $query->whereNull('tenant_id');
                }
                return $query->where('tenant_id', $tenantId);
            })],
            'display_name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'sorting' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
