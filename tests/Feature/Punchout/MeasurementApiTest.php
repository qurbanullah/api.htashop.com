<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);

    $this->user = User::factory()->create();

    $this->tenant = Tenant::create([
        'name' => 'Acme Tenant',
        'slug' => 'acme-tenant',
        'is_active' => true,
    ]);

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme Vendor',
        'slug' => 'acme-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    Membership::create([
        'tenant_id' => $this->tenant->id,
        'organization_id' => $this->organization->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'api');
});

it('creates, lists, shows, updates, and deletes measurements', function () {
    $storeResponse = $this->postJson('/api/v1/measurements', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Pressure',
        'code' => 'pressure',
        'description' => 'Pressure dimensions and ratings',
        'is_active' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJsonPath('data.code', 'pressure')
        ->assertJsonPath('data.name', 'Pressure');

    $uuid = $storeResponse->json('data.uuid');

    $this->getJson('/api/v1/measurements?tenant_id=' . $this->tenant->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'pressure');

    $this->getJson('/api/v1/measurements/' . $uuid)
        ->assertOk()
        ->assertJsonPath('data.name', 'Pressure');

    $this->putJson('/api/v1/measurements/' . $uuid, [
        'name' => 'Operating Pressure',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Operating Pressure')
        ->assertJsonPath('data.code', 'operating_pressure');

    $this->deleteJson('/api/v1/measurements/' . $uuid)
        ->assertOk();
});
