<?php

namespace App\Http\Requests\V1\Bom;

use Illuminate\Foundation\Http\FormRequest;

class SubmitBomRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.part_name' => ['required', 'string', 'max:255'],
            'lines.*.part_number' => ['nullable', 'string', 'max:100'],
            'lines.*.specification' => ['nullable', 'string', 'max:2000'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.target_unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.source_url' => ['nullable', 'string', 'max:2048'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
