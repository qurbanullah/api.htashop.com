<?php

namespace App\Http\Requests\V1\Asset;

use Illuminate\Foundation\Http\FormRequest;

class GenerateUrlRequest extends FormRequest
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
