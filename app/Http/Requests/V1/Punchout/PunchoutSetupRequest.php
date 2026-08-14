<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PunchoutSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol' => ['nullable', 'string', Rule::in(config('punchout.supported_protocols', ['cxml', 'oci']))],
            'HOOK_URL' => ['nullable', 'url'],
            'return_url' => ['nullable', 'url'],
            'BUYER_COOKIE' => ['nullable', 'string', 'max:255'],
            'buyer_cookie' => ['nullable', 'string', 'max:255'],
            'USERNAME' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'PASSWORD' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
