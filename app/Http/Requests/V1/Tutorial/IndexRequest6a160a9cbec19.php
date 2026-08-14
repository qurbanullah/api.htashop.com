<?php

namespace App\Http\Requests\V1\Tutorial;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest6a160a9cbec19 extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'limit' => 'nullable|integer|min:1|max:100',
        
        ];
    }
}
