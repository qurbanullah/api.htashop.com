<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Definition;
use App\Models\Measurement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\Unit;
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

it('lists tenant definitions and updates one', function () {
    $definition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Size',
        'code' => 'size',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Material',
        'code' => 'material',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/definitions?tenant_id=' . $this->tenant->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Definitions retrieved successfully',
        ])
        ->assertJsonCount(2, 'data');

    $this->putJson('/api/v1/definitions/' . $definition->uuid, [
        'name' => 'Display Size',
        'is_searchable' => true,
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Definition updated successfully',
            'data' => [
                'uuid' => $definition->uuid,
                'name' => 'Display Size',
                'code' => 'display_size',
                'is_searchable' => true,
            ],
        ]);
});

it('deletes a definition', function () {
    $definition = Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Weight',
        'code' => 'weight',
        'kind' => 'attribute',
        'value_type' => 'number',
        'is_active' => true,
    ]);

    $this->deleteJson('/api/v1/definitions/' . $definition->uuid)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Definition deleted successfully',
            'data' => null,
        ]);

    $this->assertSoftDeleted('definitions', ['id' => $definition->id]);
});

it('rejects a parent definition from another tenant', function () {
    $otherTenant = Tenant::create([
        'name' => 'Other Tenant',
        'slug' => 'other-tenant',
        'is_active' => true,
    ]);

    $foreignParent = Definition::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Foreign Parent',
        'code' => 'foreign_parent',
        'kind' => 'attribute',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'parent_id' => $foreignParent->id,
        'name' => 'Child Definition',
        'value_type' => 'text',
    ])
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'status_code' => 422,
        ]);

    expect(Definition::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('creates grouped definitions and filters by group and section', function () {
    $storeResponse = $this->postJson('/api/v1/definitions', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Voltage',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'electrical',
    ]);

    $uuid = $storeResponse->json('data.uuid');

    Definition::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Weight',
        'code' => 'weight',
        'kind' => 'specification',
        'value_type' => 'number',
        'group_name' => 'technical',
        'section_name' => 'mechanical',
        'is_active' => true,
    ]);

    $storeResponse
        ->assertStatus(201)
        ->assertJsonPath('data.group_name', 'technical');

    $this->getJson('/api/v1/definitions/' . $uuid)
        ->assertOk()
        ->assertJsonPath('data.group_name', 'technical');

    $this->getJson('/api/v1/definitions?tenant_id=' . $this->tenant->id . '&group_name=technical&section_name=electrical')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'voltage');
});
