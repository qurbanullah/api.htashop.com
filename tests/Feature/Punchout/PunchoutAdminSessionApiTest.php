<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\PunchoutSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');

    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);
});

it('allows admins to list and filter punchout sessions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $targetTenant = Tenant::create([
        'name' => 'Target Tenant',
        'slug' => 'target-tenant',
        'is_active' => true,
    ]);

    $otherTenant = Tenant::create([
        'name' => 'Other Tenant',
        'slug' => 'other-tenant',
        'is_active' => true,
    ]);

    $matchingSession = PunchoutSession::create([
        'tenant_id' => $targetTenant->id,
        'protocol' => 'cxml',
        'status' => 'completed',
        'buyer_cookie' => 'target-cookie',
        'return_url' => 'https://buyer.example/return',
        'cart_items' => [
            ['supplier_part_id' => 'SKU-1'],
        ],
        'cart_payload' => ['operation' => 'PunchOutOrderMessage'],
        'completed_at' => now(),
        'cart_returned_at' => now(),
        'last_activity_at' => now(),
    ]);

    PunchoutSession::create([
        'tenant_id' => $otherTenant->id,
        'protocol' => 'oci',
        'status' => 'started',
        'buyer_cookie' => 'other-cookie',
        'return_url' => 'https://buyer.example/other-return',
        'last_activity_at' => now(),
    ]);

    $this->actingAs($admin, 'api');

    $response = $this->getJson('/api/v1/admin/punchout/sessions?tenant_uuid=' . $targetTenant->uuid . '&status=completed&protocol=cxml&search=target-cookie');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Punchout sessions retrieved successfully')
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonCount(1, 'data.items');

    expect($response->json('data.items.0.uuid'))->toBe($matchingSession->uuid);
    expect($response->json('data.items.0.tenant.uuid'))->toBe($targetTenant->uuid);
});

it('allows admins to inspect punchout session audit data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tenant = Tenant::create([
        'name' => 'Audit Tenant',
        'slug' => 'audit-tenant',
        'is_active' => true,
    ]);

    $session = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'oci',
        'status' => 'completed',
        'buyer_cookie' => 'audit-cookie',
        'return_url' => 'https://buyer.example/return',
        'setup_payload' => ['username' => 'buyer-user'],
        'cart_items' => [
            [
                'supplier_part_id' => 'SKU-OCI-1',
                'description' => 'Punchout Saw',
                'quantity' => 1,
            ],
        ],
        'cart_payload' => [
            'operation' => 'BACKGROUND_POST',
            'return_url' => 'https://buyer.example/return',
        ],
        'started_at' => now()->subMinutes(10),
        'completed_at' => now()->subMinutes(5),
        'cart_returned_at' => now()->subMinutes(5),
        'last_activity_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($admin, 'api');

    $this->getJson('/api/v1/admin/punchout/sessions/' . $session->uuid)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Punchout session retrieved successfully')
        ->assertJsonPath('data.uuid', $session->uuid)
        ->assertJsonPath('data.tenant.uuid', $tenant->uuid)
        ->assertJsonPath('data.cart_items.0.supplier_part_id', 'SKU-OCI-1')
        ->assertJsonPath('data.cart_payload.operation', 'BACKGROUND_POST')
        ->assertJsonPath('data.setup_payload.username', 'buyer-user');
});

it('forbids non-admin users from viewing punchout sessions', function () {
    $user = User::factory()->create();

    $tenant = Tenant::create([
        'name' => 'Forbidden Tenant',
        'slug' => 'forbidden-tenant',
        'is_active' => true,
    ]);

    PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'cxml',
        'status' => 'completed',
        'buyer_cookie' => 'forbidden-cookie',
        'return_url' => 'https://buyer.example/return',
        'completed_at' => now(),
        'last_activity_at' => now(),
    ]);

    $this->actingAs($user, 'api');

    $this->getJson('/api/v1/admin/punchout/sessions')
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});
