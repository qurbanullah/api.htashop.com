<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PunchoutCatalogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'feature_ids' => ['nullable', 'array'],
            'feature_ids.*' => ['integer', 'exists:features,id'],
            // No min_price/max_price: punchout prices are resolved per buyer from
            // contracts and price lists *after* retrieval, so filtering on the
            // catalog price here would contradict the price the buyer sees.
            'sort' => ['nullable', 'string', Rule::in(['newest', 'price_asc', 'price_desc', 'name', 'best_sellers', 'trending'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ];
    }
}
