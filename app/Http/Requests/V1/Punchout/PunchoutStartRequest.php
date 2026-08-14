<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class PunchoutStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session' => ['required', 'uuid'],
            'token' => ['required', 'string', 'max:255'],
            'protocol' => ['nullable', 'string', 'max:50'],
        ];
    }
}
