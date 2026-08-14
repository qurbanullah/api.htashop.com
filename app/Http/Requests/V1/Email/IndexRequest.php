<?php

namespace App\Http\Requests\V1\Email;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('emails', 'email'),
            ],
            'is_primary' => 'boolean',
        
        ];
    }
}
