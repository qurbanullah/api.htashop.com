<?php

namespace App\Http\Requests\V1\Punchout;

use Illuminate\Foundation\Http\FormRequest;

class AdminPunchoutSessionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id'   => ['nullable', 'integer'],
            'tenant_uuid' => ['nullable', 'string', 'max:36'],
            'status'      => ['nullable', 'string', 'max:50'],
            'protocol'    => ['nullable', 'string', 'in:cxml,oci'],
            'search'      => ['nullable', 'string', 'max:255'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
