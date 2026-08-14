<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Measurement;
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

    $this->measurement = Measurement::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Pressure',
        'code' => 'pressure',
        'is_active' => true,
    ]);

    $this->actingAs($this->user, 'api');
});

it('creates, lists, shows, updates, and deletes units', function () {
    $storeResponse = $this->postJson('/api/v1/units', [
        'tenant_id' => $this->tenant->id,
        'measurement_id' => $this->measurement->id,
        'name' => 'Pounds per square inch',
        'code' => 'psi',
        'symbol' => 'PSI',
        'factor' => 1,
        'offset' => 0,
        'precision' => 2,
        'is_active' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJsonPath('data.code', 'psi')
        ->assertJsonPath('data.measurement.code', 'pressure');

    $uuid = $storeResponse->json('data.uuid');

    $this->getJson('/api/v1/units?tenant_id=' . $this->tenant->id . '&measurement_id=' . $this->measurement->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'psi');

    $this->getJson('/api/v1/units/' . $uuid)
        ->assertOk()
        ->assertJsonPath('data.measurement.code', 'pressure');

    $this->putJson('/api/v1/units/' . $uuid, [
        'name' => 'Pressure PSI',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pressure PSI')
        ->assertJsonPath('data.code', 'pressure_psi');

    $this->deleteJson('/api/v1/units/' . $uuid)
        ->assertOk();
});
