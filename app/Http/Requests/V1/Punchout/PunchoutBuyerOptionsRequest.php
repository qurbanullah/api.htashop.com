<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class PunchoutBuyerOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenant_uuid' => ['nullable', 'string', 'max:36'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
