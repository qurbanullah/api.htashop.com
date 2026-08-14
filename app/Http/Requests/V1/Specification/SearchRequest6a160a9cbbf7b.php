<?php

namespace App\Http\Requests\V1\Specification;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest6a160a9cbbf7b extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'type' => 'string|in:product,service',
        
        ];
    }
}
