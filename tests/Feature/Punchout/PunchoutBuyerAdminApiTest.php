<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Contract;
use App\Models\PunchoutBuyer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Punchout\PunchoutBuyerService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');

    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);
});

function punchoutBuyerPayload(Tenant $tenant, array $overrides = []): array
{
    return array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Acme Procurement',
        'code' => 'ACME',
        'protocol' => 'cxml',
        'sender_identity' => 'acme-network',
        'from_identity' => 'acme-buyer',
        'to_identity' => 'htashop',
        'shared_secret' => 'acme-shared-secret-value',
        'default_currency' => 'USD',
        'default_locale' => 'en',
    ], $overrides);
}

it('creates, lists, updates and rotates a punchout buyer', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::create([
        'name' => 'Buyer Tenant',
        'slug' => 'buyer-tenant',
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'api');

    $created = $this->postJson('/api/v1/admin/punchout/buyers', punchoutBuyerPayload($tenant));

    $created->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'acme')
        ->assertJsonPath('data.has_shared_secret', true)
        ->assertJsonPath('data.has_previous_shared_secret', false)
        ->assertJsonMissingPath('data.shared_secret_hash');

    $uuid = (string) $created->json('data.uuid');

    $this->getJson('/api/v1/admin/punchout/buyers?tenant_uuid='.$tenant->uuid.'&search=acme')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonCount(1, 'data.items');

    $this->putJson('/api/v1/admin/punchout/buyers/'.$uuid, [
        'name' => 'Acme Procurement EU',
        'allowed_return_domains' => ['*.acme.example'],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme Procurement EU')
        ->assertJsonPath('data.allowed_return_domains.0', '*.acme.example');

    $this->postJson('/api/v1/admin/punchout/buyers/'.$uuid.'/rotate-secret', [
        'shared_secret' => 'rotated-shared-secret-value',
    ])
        ->assertOk()
        ->assertJsonPath('data.has_shared_secret', true)
        ->assertJsonPath('data.has_previous_shared_secret', true);

    $buyer = PunchoutBuyer::query()->where('uuid', $uuid)->firstOrFail();

    expect($buyer->matchesSharedSecret('rotated-shared-secret-value'))->toBeTrue();
    expect($buyer->matchesSharedSecret('acme-shared-secret-value'))->toBeTrue();

    $this->deleteJson('/api/v1/admin/punchout/buyers/'.$uuid)
        ->assertOk();

    expect(PunchoutBuyer::query()->where('uuid', $uuid)->exists())->toBeFalse();
});

it('rejects a cxml buyer without a shared secret', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::create([
        'name' => 'No Secret Tenant',
        'slug' => 'no-secret-tenant',
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'api');

    $payload = punchoutBuyerPayload($tenant);
    unset($payload['shared_secret']);

    $this->postJson('/api/v1/admin/punchout/buyers', $payload)
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['shared_secret']);
});

it('forbids non-admin users from managing punchout buyers', function () {
    $user = User::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Forbidden Buyer Tenant',
        'slug' => 'forbidden-buyer-tenant',
        'is_active' => true,
    ]);

    $this->actingAs($user, 'api');

    $this->getJson('/api/v1/admin/punchout/buyers')
        ->assertStatus(403);

    $this->postJson('/api/v1/admin/punchout/buyers', punchoutBuyerPayload($tenant))
        ->assertStatus(403);
});

it('returns tenant and tenant-scoped options for the buyer form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::create([
        'name' => 'Options Tenant',
        'slug' => 'options-tenant',
        'is_active' => true,
    ]);

    $buyerOrganization = punchoutBuyerOrganization($tenant, 'options-buyer');
    $vendor = punchoutOrganization($tenant, 'options-vendor');

    Contract::query()->create([
        'tenant_id' => $tenant->id,
        'vendor_id' => $vendor->id,
        'buyer_id' => $buyerOrganization->id,
        'name' => 'Options Contract',
        'code' => 'OPT-1',
        'status' => 'active',
    ]);

    $this->actingAs($admin, 'api');

    $this->getJson('/api/v1/admin/punchout/buyers/options')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['uuid' => $tenant->uuid])
        ->assertJsonPath('data.organizations', [])
        ->assertJsonPath('data.contracts', []);

    $this->getJson('/api/v1/admin/punchout/buyers/options?tenant_uuid='.$tenant->uuid.'&organization_id='.$buyerOrganization->id)
        ->assertOk()
        ->assertJsonCount(2, 'data.organizations')
        ->assertJsonFragment(['uuid' => $buyerOrganization->uuid])
        ->assertJsonCount(1, 'data.contracts')
        ->assertJsonPath('data.contracts.0.code', 'OPT-1')
        ->assertJsonPath('data.contracts.0.buyer_name', $buyerOrganization->name);
});

it('drops a rotated secret when the overlap window is not requested', function () {
    $tenant = Tenant::create([
        'name' => 'Rotate Tenant',
        'slug' => 'rotate-tenant',
        'is_active' => true,
    ]);

    $buyer = punchoutBuyer($tenant);
    $service = app(PunchoutBuyerService::class);

    $service->rotateSecret($buyer, 'brand-new-shared-secret', false);

    expect($buyer->fresh()->matchesSharedSecret('brand-new-shared-secret'))->toBeTrue();
    expect($buyer->fresh()->matchesSharedSecret('buyer-shared-secret'))->toBeFalse();
});
