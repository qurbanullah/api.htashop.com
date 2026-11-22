<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class AdminBuyerPartyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'network_id' => ['nullable', 'string', 'max:64', 'unique:buyer_parties,network_id'],
            'duns' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'size:2'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
