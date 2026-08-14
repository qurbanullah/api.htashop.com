<?php

namespace App\Http\Requests\V1\Specification;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:specifications,slug',
            'type' => 'required|string|in:product,service',
            'unit' => 'nullable|string|max:50',
            'group' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        
        ];
    }
}
