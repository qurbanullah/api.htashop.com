<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'key' => [
                'sometimes',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('dam_collections', 'key')->ignore($this->route('damCollection')),
            ],
            'name' => 'sometimes|string|max:150',
            'kind' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'is_system' => 'sometimes|boolean',
        ];
    }
}
