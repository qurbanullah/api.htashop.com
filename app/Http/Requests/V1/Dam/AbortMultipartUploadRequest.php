<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class AbortMultipartUploadRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'key' => 'required|string|max:500',
            'upload_id' => 'required|string|max:255',
        ];
    }
}
