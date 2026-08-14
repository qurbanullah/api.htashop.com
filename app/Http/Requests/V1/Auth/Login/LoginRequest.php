<?php

namespace App\Http\Requests\V1\Auth\Login;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'turnstileToken' => ['nullable', 'string'],
        ];
    }
}
