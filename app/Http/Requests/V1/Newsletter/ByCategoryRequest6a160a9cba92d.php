<?php

namespace App\Http\Requests\V1\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

class ByCategoryRequest6a160a9cba92d extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'key' => 'required|string',
            'expires_in' => 'nullable|integer|min:60|max:3600',
        
        ];
    }
}
