<?php

namespace App\Http\Requests\V1\Industry;

use Illuminate\Foundation\Http\FormRequest;

class IndustryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer', 'exists:industries,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
