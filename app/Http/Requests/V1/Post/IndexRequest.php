<?php

namespace App\Http\Requests\V1\Post;

use App\Enums\PostTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:200',
            'limit' => 'nullable|integer|min:1|max:1000',
            'latest' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'tags' => 'nullable|string',
            'categories' => 'nullable|string',
            // Each post type has its own public section; the storefront lists a
            // section by filtering this endpoint on its type.
            'type' => ['nullable', 'string', Rule::in(PostTypeEnum::values())],
        ];
    }
}
