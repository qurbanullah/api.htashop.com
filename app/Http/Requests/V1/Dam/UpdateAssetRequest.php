<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collection_name' => 'sometimes|string|max:100',
            'collection_keys' => 'sometimes|array',
            'collection_keys.*' => 'string|max:100',
            'sort_order' => 'sometimes|nullable|integer|min:0',
        ];
    }
}
