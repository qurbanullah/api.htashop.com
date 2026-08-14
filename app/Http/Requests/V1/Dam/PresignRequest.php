<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class PresignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_name' => 'required|string|max:255',
            'content_type' => 'required|string|max:100',
            'prefix' => 'sometimes|string|max:255',
            'expires' => 'sometimes|integer|min:60|max:3600',
        ];
    }
}
