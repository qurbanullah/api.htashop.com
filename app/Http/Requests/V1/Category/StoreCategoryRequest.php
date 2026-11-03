<?php

namespace App\Http\Requests\V1\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug',
            'code' => ['nullable', 'string', 'size:2', 'unique:categories,code'],
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'featured_rank' => 'nullable|integer|min:0',
            'order' => 'nullable|integer|min:0',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'image' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom attribute names for validation errors
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'parent category',
            'is_active' => 'active status',
            'is_featured' => 'featured status',
            'featured_rank' => 'featured order',
            'order' => 'sort order',
            'code' => 'category code',
            'icon' => 'icon',
            'color' => 'brand colour',
            'image' => 'image URL',
        ];
    }

    /**
     * Only root categories can appear in the storefront top navigation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('is_featured') && $this->filled('parent_id')) {
                $validator->errors()->add(
                    'is_featured',
                    'Only root categories can be featured in the top navigation.'
                );
            }
        });
    }
}
