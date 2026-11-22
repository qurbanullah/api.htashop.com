<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class PunchoutBuyerPartNumberStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buyer_part_number' => ['required', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'long_description' => ['nullable', 'string', 'max:5000'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'manufacturer_part_number' => ['nullable', 'string', 'max:100'],
            'gtin' => ['nullable', 'string', 'max:14'],
            'unspsc' => ['nullable', 'string', 'max:20'],
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'metadata' => ['nullable', 'array'],
            'remap' => ['nullable', 'boolean'],
        ];
    }
}
