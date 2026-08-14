<?php

namespace App\Http\Requests\V1\Product;

use Illuminate\Foundation\Http\FormRequest;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id'               => ['nullable', 'integer'],
            'organization_id'         => ['nullable', 'integer'],
            'status'                  => ['nullable', 'string', 'max:100'],
            'category_id'             => ['nullable', 'integer', 'exists:categories,id'],
            'is_active'               => ['nullable', 'boolean'],
            'search'                  => ['nullable', 'string', 'max:255'],
            'definition_group_name'   => ['nullable', 'string', 'max:255'],
            'definition_section_name' => ['nullable', 'string', 'max:255'],
            'definition_kind'         => ['nullable', 'string', 'max:100'],
            'per_page'                => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
