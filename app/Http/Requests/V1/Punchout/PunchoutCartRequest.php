<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PunchoutCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol' => ['nullable', 'string', Rule::in(config('punchout.supported_protocols', ['cxml', 'oci']))],
            'session' => ['required', 'uuid'],
            'token' => ['required', 'string', 'max:255'],
            'HOOK_URL' => ['nullable', 'url'],
            'return_url' => ['nullable', 'url'],
            'BUYER_COOKIE' => ['nullable', 'string', 'max:255'],
            'buyer_cookie' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.supplier_part_id' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'items.*.price' => ['nullable'],
            'items.*.currency' => ['nullable', 'string', 'max:10'],
            'items.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
        ];
    }
}
