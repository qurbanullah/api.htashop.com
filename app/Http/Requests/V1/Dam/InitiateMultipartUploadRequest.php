<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class InitiateMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'filename' => 'required|string|max:255',
            'content_type' => 'required|string|max:100',
            'directory' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1',
            'chunk_size' => 'required|integer|min:5242880|max:524288000',
        ];
    }
}
