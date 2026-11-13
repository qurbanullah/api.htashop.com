<?php

namespace App\Http\Requests\V1\Quote;

use Illuminate\Foundation\Http\FormRequest;

class QuoteNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
