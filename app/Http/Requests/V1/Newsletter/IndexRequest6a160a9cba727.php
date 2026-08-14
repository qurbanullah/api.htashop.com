<?php

namespace App\Http\Requests\V1\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest6a160a9cba727 extends FormRequest
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
        
        ];
    }
}
