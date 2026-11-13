<?php

namespace App\Http\Requests\V1\Industry;

use App\Models\Industry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndustryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $industry = Industry::query()->where('code', $this->route('industry'))->first();

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:64'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:industries,id',
                Rule::notIn([$industry?->id]),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
