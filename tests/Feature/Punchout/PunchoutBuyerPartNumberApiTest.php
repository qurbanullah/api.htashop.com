<?php

namespace Tests\Feature\Punchout;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');

    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);
});

function partNumberAdmin($test): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $test->actingAs($admin, 'api');

    return $admin;
}

function partNumberManagerMembership(User $user, Tenant $tenant): Membership
{
    $organization = punchoutOrganization($tenant, 'pn-manager-org-'.Str::lower(Str::random(6)));

    return Membership::query()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);
}

function partNumberPayload(int $productId, array $overrides = []): array
{
    return array_merge([
        'buyer_part_number' => '1000048217',
        'short_description' => 'MPU6050 MODULE',
        'long_description' => 'Six axis motion tracking module, MPU6050, I2C interface',
        'unit_of_measure' => 'EA',
        'manufacturer_part_number' => 'MPU-6050',
        'product_id' => $productId,
    ], $overrides);
}

it('creates, lists, updates and deletes buyer part numbers as an admin', function () {
    partNumberAdmin($this);

    $tenant = punchoutTenant('pn-admin-tenant');
    $organization = punchoutOrganization($tenant, 'pn-admin-org');
    $product = punchoutProduct($tenant, $organization, 'Part Number Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    $created = $this->postJson(
        '/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers',
        partNumberPayload($product->id),
    )->assertCreated();

    $uuid = $created->json('data.uuid');

    expect($created->json('data.buyer_part_number'))->toBe('1000048217');
    expect($created->json('data.short_description'))->toBe('MPU6050 MODULE');
    expect($created->json('data.long_description'))->toContain('I2C');
    expect($created->json('data.product.uuid'))->toBe($product->uuid);

    $this->getJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers?q=MPU')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.uuid', $uuid);

    $this->putJson(
        '/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/'.$uuid,
        ['is_active' => false, 'unit_of_measure' => 'PC'],
    )->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers/'.$uuid)
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->getJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 0);
});

it('filters buyer part numbers by product and active state', function () {
    partNumberAdmin($this);

    $tenant = punchoutTenant('pn-filter-tenant');
    $organization = punchoutOrganization($tenant, 'pn-filter-org');
    $first = punchoutProduct($tenant, $organization, 'Filter Product One', ['punchout']);
    $second = punchoutProduct($tenant, $organization, 'Filter Product Two', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    $this->postJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers', partNumberPayload($first->id, [
        'buyer_part_number' => 'AAA-1',
    ]))->assertCreated();

    $this->postJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers', partNumberPayload($second->id, [
        'buyer_part_number' => 'BBB-1',
        'is_active' => false,
    ]))->assertCreated();

    $this->getJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers?product_uuid='.$second->uuid)
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.buyer_part_number', 'BBB-1');

    $this->getJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers?is_active=0')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.buyer_part_number', 'BBB-1');
});

it('returns a conflict when the same code is mapped to a second product', function () {
    partNumberAdmin($this);

    $tenant = punchoutTenant('pn-conflict-tenant');
    $organization = punchoutOrganization($tenant, 'pn-conflict-org');
    $first = punchoutProduct($tenant, $organization, 'Conflict One', ['punchout']);
    $second = punchoutProduct($tenant, $organization, 'Conflict Two', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    $this->postJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers', partNumberPayload($first->id))
        ->assertCreated();

    $this->postJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers', partNumberPayload($second->id))
        ->assertStatus(409);

    $this->postJson('/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers', partNumberPayload($second->id, [
        'remap' => true,
    ]))->assertCreated()->assertJsonPath('data.product.uuid', $second->uuid);
});

it('scopes manager part numbers to the caller tenant', function () {
    $tenant = punchoutTenant('pn-manager-tenant');
    $otherTenant = punchoutTenant('pn-manager-other');
    $organization = punchoutOrganization($tenant, 'pn-manager-org');
    $product = punchoutProduct($tenant, $organization, 'Managed Part Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);
    $foreign = punchoutBuyer($otherTenant);

    $user = User::factory()->create();
    partNumberManagerMembership($user, $tenant);
    $this->actingAs($user, 'api');

    $created = $this->postJson(
        '/api/v1/manage/punchout/buyers/'.$buyer->uuid.'/part-numbers',
        partNumberPayload($product->id),
    )->assertCreated();

    $uuid = $created->json('data.uuid');

    $this->getJson('/api/v1/manage/punchout/buyers/'.$buyer->uuid.'/part-numbers')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);

    $this->getJson('/api/v1/manage/punchout/buyers/'.$foreign->uuid.'/part-numbers')->assertNotFound();
    $this->postJson('/api/v1/manage/punchout/buyers/'.$foreign->uuid.'/part-numbers', partNumberPayload($product->id))
        ->assertNotFound();
    $this->putJson('/api/v1/manage/punchout/buyers/'.$foreign->uuid.'/part-numbers/'.$uuid, ['is_active' => false])
        ->assertNotFound();
    $this->deleteJson('/api/v1/manage/punchout/buyers/'.$foreign->uuid.'/part-numbers/'.$uuid)
        ->assertNotFound();
});

it('rejects a part number for a product from another tenant', function () {
    partNumberAdmin($this);

    $tenant = punchoutTenant('pn-scope-tenant');
    $otherTenant = punchoutTenant('pn-scope-other');
    $otherOrganization = punchoutOrganization($otherTenant, 'pn-scope-other-org');
    $foreignProduct = punchoutProduct($otherTenant, $otherOrganization, 'Foreign Part Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    $this->postJson(
        '/api/v1/admin/punchout/buyers/'.$buyer->uuid.'/part-numbers',
        partNumberPayload($foreignProduct->id),
    )->assertStatus(422);
});
