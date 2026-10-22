<?php

namespace App\Http\Requests\V1\Punchout;

use App\Models\Contract;
use App\Models\Organization;
use App\Models\PunchoutBuyer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagerPunchoutBuyerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $buyer = $this->buyer();

        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('punchout_buyers', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($buyer?->id),
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
            $buyer = $this->buyer();

            if (! $buyer) {
                return;
            }

            $tenantId = $this->tenantId();
            $organizationId = $this->exists('organization_id')
                ? $this->input('organization_id')
                : $buyer->organization_id;
            $contractId = $this->exists('contract_id')
                ? $this->input('contract_id')
                : $buyer->contract_id;

            $organization = $organizationId ? Organization::query()->find($organizationId) : null;
            $contract = $contractId ? Contract::query()->find($contractId) : null;

            if ($organization && $organization->tenant_id !== $tenantId) {
                $validator->errors()->add('organization_id', 'The selected organization does not belong to your tenant.');
            }

            if ($contract && $contract->tenant_id !== $tenantId) {
                $validator->errors()->add('contract_id', 'The selected contract does not belong to your tenant.');
            }

            if ($contract && $organization && $contract->buyer_id !== $organization->id) {
                $validator->errors()->add('contract_id', 'The selected contract is not held by the selected organization.');
            }
        });
    }

    private function buyer(): ?PunchoutBuyer
    {
        $uuid = $this->route('uuid');

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return PunchoutBuyer::query()
            ->where('tenant_id', $this->tenantId())
            ->where('uuid', $uuid)
            ->first();
    }

    private function tenantId(): int
    {
        return (int) $this->attributes->get('punchout_manager_tenant_id', 0);
    }
}
