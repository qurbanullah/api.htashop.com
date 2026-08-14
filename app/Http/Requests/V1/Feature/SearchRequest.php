<?php

namespace App\Http\Requests\V1\Feature;

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
            'slug' => 'nullable|string|max:255|unique:features,slug',
            'icon' => 'nullable|string|max:255',
            'type' => 'required|string|in:product,service',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        
        ];
    }
}
