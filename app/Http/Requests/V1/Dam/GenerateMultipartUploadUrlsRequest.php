<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class GenerateMultipartUploadUrlsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'key' => 'required|string|max:500',
            'upload_id' => 'required|string|max:255',
            'part_numbers' => 'required|array|min:1|max:1000',
            'part_numbers.*' => 'required|integer|min:1|max:10000',
            'expires_in' => 'nullable|integer|min:60|max:3600',
        ];
    }
}
