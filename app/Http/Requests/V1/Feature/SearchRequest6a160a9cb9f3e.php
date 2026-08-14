<?php

namespace App\Http\Requests\V1\Feature;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest6a160a9cb9f3e extends FormRequest
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
