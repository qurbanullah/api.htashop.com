<?php

namespace App\Http\Requests\V1\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFeaturedCategoriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Admin role is enforced by the route middleware.
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // The nav is a fixed-size slot list, so the payload must never
            // exceed what the storefront can actually render.
            'category_ids' => [
                'present',
                'array',
                'max:'.(int) config('catalog.top_nav.limit', 6),
            ],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    /**
     * The top nav only renders root categories; silently accepting a child
     * would leave an editor wondering why their selection never appears.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = $this->input('category_ids', []);

            if (! is_array($ids) || $ids === []) {
                return;
            }

            $childIds = Category::query()
                ->whereIn('id', $ids)
                ->whereNotNull('parent_id')
                ->pluck('id');

            if ($childIds->isNotEmpty()) {
                $validator->errors()->add(
                    'category_ids',
                    'Only root categories can be shown in the top navigation.'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_ids.max' => 'The storefront can show at most :max categories in the top navigation.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'category_ids' => 'top navigation categories',
        ];
    }
}
