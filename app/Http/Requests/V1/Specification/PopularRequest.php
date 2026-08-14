<?php

namespace App\Http\Requests\V1\Specification;

use Illuminate\Foundation\Http\FormRequest;

class PopularRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'type' => 'string|in:product,service',
            'limit' => 'integer|min:1|max:100',
        
        ];
    }
}
