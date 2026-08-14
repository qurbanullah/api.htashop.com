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

it('creates, lists, updates, and deletes labels', function () {
    $storeResponse = $this->postJson('/api/v1/labels', [
        'tenant_id' => $this->tenant->id,
        'slug' => 'length',
        'display_name' => 'Length',
        'description' => 'Dimensional specifications',
        'sorting' => 10,
        'is_active' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJsonPath('data.slug', 'length')
        ->assertJsonPath('data.display_name', 'Length');

    $uuid = $storeResponse->json('data.uuid');

    $this->getJson('/api/v1/labels?tenant_id=' . $this->tenant->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'length');

    $this->putJson('/api/v1/labels/' . $uuid, [
        'display_name' => 'Overall Length',
    ])
        ->assertOk()
        ->assertJsonPath('data.display_name', 'Overall Length');

    $this->deleteJson('/api/v1/labels/' . $uuid)
        ->assertOk();
});

it('creates and reads system labels alongside tenant labels', function () {
    $this->postJson('/api/v1/labels', [
        'slug' => 'dimension',
        'display_name' => 'Dimension',
        'description' => 'System-level label available to all tenants',
        'sorting' => 5,
        'is_active' => true,
    ])->assertStatus(201)->assertJsonPath('data.scope', 'system');

    $this->postJson('/api/v1/labels', [
        'tenant_id' => $this->tenant->id,
        'slug' => 'segment',
        'display_name' => 'Segment',
        'description' => 'Tenant-specific taxonomy label',
        'sorting' => 10,
        'is_active' => true,
    ])->assertStatus(201)->assertJsonPath('data.scope', 'tenant');

    $this->getJson('/api/v1/labels?tenant_id=' . $this->tenant->id)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['slug' => 'dimension', 'scope' => 'system'])
        ->assertJsonFragment(['slug' => 'segment', 'scope' => 'tenant']);
});

it('uses a related label for definition display metadata', function () {
    $labelResponse = $this->postJson('/api/v1/labels', [
        'tenant_id' => $this->tenant->id,
        'slug' => 'pressure',
        'display_name' => 'Pressure',
        'is_active' => true,
    ])->assertStatus(201);

    $labelId = $labelResponse->json('data.id');

    $definitionResponse = $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'label_ids' => [$labelId],
        'name' => 'Operating Pressure',
        'kind' => 'specification',
        'value_type' => 'number',
    ]);

    $definitionResponse
        ->assertStatus(201)
        ->assertJsonPath('data.display_label', 'Pressure')
        ->assertJsonPath('data.label.slug', 'pressure')
        ->assertJsonPath('data.labels.0.slug', 'pressure')
        ->assertJsonPath('data.measurement_type', 'pressure');
});
