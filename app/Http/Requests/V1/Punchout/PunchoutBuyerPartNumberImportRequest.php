<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class PunchoutBuyerPartNumberImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'dry_run' => ['nullable', 'boolean'],
            'remap' => ['nullable', 'boolean'],
            'apply_suggestions' => ['nullable', 'boolean'],
        ];
    }
}
