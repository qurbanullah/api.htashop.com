<?php

namespace App\Http\Requests\V1\Specification;

use Illuminate\Foundation\Http\FormRequest;

class PopularRequest6a160a9cbbcaf extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'q' => 'required|string|min:2',
            'type' => 'string|in:product,service',
            'limit' => 'integer|min:1|max:100',
        
        ];
    }
}
