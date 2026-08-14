<?php

namespace App\Http\Requests\V1\Label;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $label = Label::query()->where('uuid', $this->route('uuid'))->first();
        $tenantId = $this->input('tenant_id');
        if (is_null($tenantId)) {
            $tenantId = $label?->tenant_id;
        }

        return [
            'tenant_id' => ['sometimes', 'nullable', 'integer', 'exists:tenants,id'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('labels', 'slug')->ignore($label?->id)->where(function ($query) use ($tenantId) {
                if (is_null($tenantId)) {
                    return $query->whereNull('tenant_id');
                }
                return $query->where('tenant_id', $tenantId);
            })],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'sorting' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
