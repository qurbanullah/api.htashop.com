<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'kind' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
        ];
    }
}
