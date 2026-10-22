<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class PunchoutBuyerRotateSecretRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shared_secret' => ['required', 'string', 'min:16', 'max:255'],
            'keep_previous' => ['nullable', 'boolean'],
        ];
    }
}
