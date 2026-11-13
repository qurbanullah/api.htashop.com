<?php

namespace App\Http\Requests\V1\Quote;

use Illuminate\Foundation\Http\FormRequest;

class QuoteAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
