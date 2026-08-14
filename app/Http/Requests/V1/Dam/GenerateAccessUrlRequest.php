<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAccessUrlRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'key' => 'required|string|max:500',
            'filename' => 'nullable|string|max:255',
            'expires_in' => 'nullable|integer|min:60|max:3600',
        ];
    }
}
