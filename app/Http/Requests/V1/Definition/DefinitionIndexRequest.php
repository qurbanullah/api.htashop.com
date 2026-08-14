<?php

namespace App\Http\Requests\V1\Definition;

use Illuminate\Foundation\Http\FormRequest;

class DefinitionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id'    => ['nullable', 'integer'],
            'kind'         => ['nullable', 'string', 'max:100'],
            'target_type'  => ['nullable', 'string', 'max:100'],
            'value_type'   => ['nullable', 'string', 'max:100'],
            'group_name'   => ['nullable', 'string', 'max:255'],
            'section_name' => ['nullable', 'string', 'max:255'],
            'is_active'    => ['nullable', 'boolean'],
            'search'       => ['nullable', 'string', 'max:255'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
