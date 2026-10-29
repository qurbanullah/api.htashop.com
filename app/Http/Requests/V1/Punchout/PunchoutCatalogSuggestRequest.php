<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class PunchoutCatalogSuggestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
