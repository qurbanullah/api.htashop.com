<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePresignedUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filename' => 'required|string|max:255',
            'content_type' => 'required|string|max:100',
            'directory' => 'required|string|max:255',
            'max_file_size' => 'nullable|integer|min:1',
            'expires_in' => 'nullable|integer|min:60|max:3600',
        ];
    }
}
