<?php

namespace Tests\Feature\Punchout;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Contract;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PunchoutBuyer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutMiddleware(ApiAuthenticate::class);
});

function managerMembership(User $user, Tenant $tenant, ?Organization $organization = null): Membership
{
    $organization ??= punchoutOrganization($tenant, 'manager-org-'.Str::lower(Str::random(6)));

    return Membership::query()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);
}

function managerBuyerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Managed Procurement',
        'code' => 'MANAGED',
        'protocol' => 'cxml',
        'sender_identity' => 'managed-network',
        'from_identity' => 'managed-buyer',
        'to_identity' => 'htashop',
        'shared_secret' => 'managed-shared-secret-value',
        'default_currency' => 'USD',
        'default_locale' => 'en',
    ], $overrides);
}

it('lists only buyers that belong to the manager tenant', function () {
    $tenant = punchoutTenant('manager-list-tenant');
    $otherTenant = punchoutTenant('manager-list-other');
    $user = User::factory()->create();
    managerMembership($user, $tenant);

    $owned = punchoutBuyer($tenant, ['code' => 'owned']);
    punchoutBuyer($otherTenant, ['code' => 'other']);

    $this->actingAs($user, 'api');

    $response = $this->getJson('/api/v1/manage/punchout/buyers?tenant_uuid='.$otherTenant->uuid)
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);

    expect($response->json('data.items.0.uuid'))->toBe($owned->uuid);
    expect($response->json('data.items.0.tenant_id'))->toBe($tenant->id);
});

it('forces the manager tenant onto a new buyer', function () {
    $tenant = punchoutTenant('manager-create-tenant');
    $otherTenant = punchoutTenant('manager-create-other');
    $user = User::factory()->create();
    managerMembership($user, $tenant);

    $this->actingAs($user, 'api');

    $response = $this->postJson('/api/v1/manage/punchout/buyers', managerBuyerPayload([
        'tenant_id' => $otherTenant->id,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.tenant_id', $tenant->id)
        ->assertJsonPath('data.code', 'managed');

    $buyer = PunchoutBuyer::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($buyer->tenant_id)->toBe($tenant->id);
    expect($buyer->matchesSharedSecret('managed-shared-secret-value'))->toBeTrue();
});

it('rejects organizations and contracts from another tenant', function () {
    $tenant = punchoutTenant('manager-scope-tenant');
    $otherTenant = punchoutTenant('manager-scope-other');
    $user = User::factory()->create();
    managerMembership($user, $tenant);

    $otherOrganization = punchoutBuyerOrganization($otherTenant, 'foreign-buyer');
    $otherVendor = punchoutOrganization($otherTenant, 'foreign-vendor');
    $foreignContract = Contract::query()->create([
        'tenant_id' => $otherTenant->id,
        'vendor_id' => $otherVendor->id,
        'buyer_id' => $otherOrganization->id,
        'name' => 'Foreign Contract',
        'code' => 'FOREIGN',
        'status' => 'active',
    ]);

    $this->actingAs($user, 'api');

    $this->postJson('/api/v1/manage/punchout/buyers', managerBuyerPayload([
        'organization_id' => $otherOrganization->id,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['organization_id']);

    $this->postJson('/api/v1/manage/punchout/buyers', managerBuyerPayload([
        'contract_id' => $foreignContract->id,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['contract_id']);
});

it('treats another tenant buyer as missing', function () {
    $tenant = punchoutTenant('manager-isolation-tenant');
    $otherTenant = punchoutTenant('manager-isolation-other');
    $user = User::factory()->create();
    managerMembership($user, $tenant);

    $foreign = punchoutBuyer($otherTenant, ['code' => 'foreign']);

    $this->actingAs($user, 'api');

    $this->getJson('/api/v1/manage/punchout/buyers/'.$foreign->uuid)->assertNotFound();
    $this->putJson('/api/v1/manage/punchout/buyers/'.$foreign->uuid, ['name' => 'Hijacked'])
        ->assertNotFound();
    $this->deleteJson('/api/v1/manage/punchout/buyers/'.$foreign->uuid)->assertNotFound();
    $this->postJson('/api/v1/manage/punchout/buyers/'.$foreign->uuid.'/rotate-secret', [
        'shared_secret' => 'replacement-secret-value',
    ])->assertNotFound();
});

it('rotates a secret inside the manager tenant', function () {
    $tenant = punchoutTenant('manager-rotate-tenant');
    $user = User::factory()->create();
    managerMembership($user, $tenant);
    $buyer = punchoutBuyer($tenant);

    $this->actingAs($user, 'api');

    $this->postJson('/api/v1/manage/punchout/buyers/'.$buyer->uuid.'/rotate-secret', [
        'shared_secret' => 'replacement-secret-value',
        'keep_previous' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.has_shared_secret', true)
        ->assertJsonPath('data.has_previous_shared_secret', false);

    expect($buyer->fresh()->matchesSharedSecret('replacement-secret-value'))->toBeTrue();
    expect($buyer->fresh()->matchesSharedSecret('buyer-shared-secret'))->toBeFalse();
});

it('returns tenant-scoped options without the tenant picker', function () {
    $tenant = punchoutTenant('manager-options-tenant');
    $otherTenant = punchoutTenant('manager-options-other');
    $user = User::factory()->create();
    managerMembership($user, $tenant);

    $organization = punchoutBuyerOrganization($tenant, 'manager-options-buyer');
    $foreignOrganization = punchoutBuyerOrganization($otherTenant, 'manager-options-foreign');
    $vendor = punchoutOrganization($tenant, 'manager-options-vendor');
    $contract = Contract::query()->create([
        'tenant_id' => $tenant->id,
        'vendor_id' => $vendor->id,
        'buyer_id' => $organization->id,
        'name' => 'Manager Contract',
        'code' => 'MGR-1',
        'status' => 'active',
    ]);

    $this->actingAs($user, 'api');

    $response = $this->getJson('/api/v1/manage/punchout/buyers/options?organization_id='.$organization->id)
        ->assertOk()
        ->assertJsonMissingPath('data.tenants')
        ->assertJsonFragment(['uuid' => $organization->uuid])
        ->assertJsonMissing(['uuid' => $foreignOrganization->uuid])
        ->assertJsonCount(1, 'data.contracts')
        ->assertJsonPath('data.contracts.0.uuid', $contract->uuid);
});

it('denies managers without an active membership', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    $this->getJson('/api/v1/manage/punchout/buyers')
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});

it('denies managers whose tenant is inactive', function () {
    $tenant = punchoutTenant('manager-inactive-tenant');
    $tenant->update(['is_active' => false]);

    $user = User::factory()->create();
    managerMembership($user, $tenant);

    $this->actingAs($user, 'api');

    $this->getJson('/api/v1/manage/punchout/buyers')
        ->assertStatus(403)
        ->assertJsonPath('success', false);
});
