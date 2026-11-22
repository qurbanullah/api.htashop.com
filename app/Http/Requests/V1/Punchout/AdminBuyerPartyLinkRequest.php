<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class AdminBuyerPartyLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buyer_uuids' => ['required', 'array', 'min:1'],
            'buyer_uuids.*' => ['uuid'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }
}
