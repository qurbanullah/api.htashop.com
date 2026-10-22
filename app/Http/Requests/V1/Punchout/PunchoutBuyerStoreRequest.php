<?php

namespace App\Http\Requests\V1\Punchout;

use App\Models\Contract;
use App\Models\Organization;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PunchoutBuyerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = (int) $this->input('tenant_id');

        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('punchout_buyers', 'code')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId),
                ),
            ],
            'protocol' => ['nullable', 'string', 'in:cxml,oci'],
            'sender_identity' => ['nullable', 'string', 'max:255'],
            'from_identity' => ['nullable', 'string', 'max:255'],
            'to_identity' => ['nullable', 'string', 'max:255'],
            'shared_secret' => ['nullable', 'string', 'min:16', 'max:255'],
            'oci_username' => ['nullable', 'string', 'max:255'],
            'oci_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'allowed_return_domains' => ['nullable', 'array'],
            'allowed_return_domains.*' => ['string', 'max:255', 'regex:/^(\*\.)?[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)+$/'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'default_locale' => ['nullable', 'string', 'max:10'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $tenantId = (int) $this->input('tenant_id');
            $organization = $this->filled('organization_id')
                ? Organization::query()->find($this->input('organization_id'))
                : null;
            $contract = $this->filled('contract_id')
                ? Contract::query()->find($this->input('contract_id'))
                : null;

            if ($organization && $organization->tenant_id !== $tenantId) {
                $validator->errors()->add('organization_id', 'The selected organization does not belong to the selected tenant.');
            }

            if ($contract && $contract->tenant_id !== $tenantId) {
                $validator->errors()->add('contract_id', 'The selected contract does not belong to the selected tenant.');
            }

            if ($contract && $organization && $contract->buyer_id !== $organization->id) {
                $validator->errors()->add('contract_id', 'The selected contract is not held by the selected organization.');
            }

            if ($this->input('protocol') === 'cxml' && ! $this->filled('shared_secret')) {
                $validator->errors()->add('shared_secret', 'A shared secret is required for a cXML buyer.');
            }

            if ($this->input('protocol') === 'oci' && (! $this->filled('oci_username') || ! $this->filled('oci_password'))) {
                $validator->errors()->add('oci_username', 'An OCI username and password are required for an OCI buyer.');
            }
        });
    }
}
