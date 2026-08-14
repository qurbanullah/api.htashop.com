<?php

namespace App\Http\Requests\V1\Tutorial;

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

            'per_page' => 'nullable|integer|min:1|max:200',
            'limit' => 'nullable|integer|min:1|max:1000',
            'latest' => 'nullable|boolean',
        
        ];
    }
}
