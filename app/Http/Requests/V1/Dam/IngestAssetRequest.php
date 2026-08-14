<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class IngestAssetRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'damable_type' => 'required|string',
            'damable_id' => 'required|integer',
            'file_name' => 'required|string',
            'object_key' => 'required|string',
            'mime_type' => 'sometimes|string',
            'bucket' => 'sometimes|string',
            'disk' => 'sometimes|string',
            'collection_name' => 'sometimes|string',
            'collection_keys' => 'sometimes|array',
            'metadata' => 'sometimes|array',
        ];
    }
}
