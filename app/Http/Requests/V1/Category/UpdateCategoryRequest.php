<?php

namespace App\Http\Requests\V1\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // You can add authorization logic here
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $categoryId = $this->route('category');

        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($categoryId),
            ],
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    // Prevent setting parent to itself
                    if ($value == $categoryId) {
                        $fail('A category cannot be its own parent.');
                    }

                    // Prevent circular references (parent is a descendant)
                    if ($value) {
                        $parent = \App\Models\Category::find($value);
                        if ($parent && $this->isDescendant($parent, $categoryId)) {
                            $fail('Cannot set a descendant category as parent.');
                        }
                    }
                },
            ],
            'is_active' => 'boolean',
            'order' => 'nullable|integer|min:0',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'image' => 'nullable|string|max:500',
        ];
    }

    /**
     * Check if a category is a descendant of another
     */
    private function isDescendant($category, $ancestorId): bool
    {
        while ($category->parent_id) {
            if ($category->parent_id == $ancestorId) {
                return true;
            }
            $category = \App\Models\Category::find($category->parent_id);
            if (!$category) {
                break;
            }
        }
        return false;
    }

    /**
     * Get custom attribute names for validation errors
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'parent category',
            'is_active' => 'active status',
            'order' => 'sort order',
        ];
    }
}
