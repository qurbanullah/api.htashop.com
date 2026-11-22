<?php

namespace App\Http\Requests\V1\Punchout;

use App\Models\BuyerParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminBuyerPartyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $party = BuyerParty::query()->where('uuid', $this->route('uuid'))->first();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'network_id' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('buyer_parties', 'network_id')->ignore($party?->id),
            ],
            'duns' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'size:2'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
