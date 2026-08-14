<?php

namespace App\Http\Requests\V1\Dam;

use Illuminate\Foundation\Http\FormRequest;

class ReadDamAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'damable_type' => 'sometimes|string',
            'damable_id' => 'sometimes|integer',
            'collection_name' => 'sometimes|string|max:100',
            'collection_key' => 'sometimes|string|max:100',
        ];
    }
}
