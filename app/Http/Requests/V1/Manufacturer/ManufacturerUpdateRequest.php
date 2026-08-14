<?php

namespace App\Http\Requests\V1\Manufacturer;

use Illuminate\Foundation\Http\FormRequest;

class ManufacturerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'in:manufacturer,oem'],
            'logo' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
