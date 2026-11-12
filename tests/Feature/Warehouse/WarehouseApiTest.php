<?php

namespace Tests\Feature\Warehouse;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function () {
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $this->withoutMiddleware(ApiAuthenticate::class);

    $this->tenant = Tenant::create([
        'name' => 'HTAShop',
        'slug' => 'htashop',
        'is_active' => true,
    ]);

    $this->organization = Organization::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'HTAShop',
        'slug' => 'htashop',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $this->user = User::factory()->create();

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

/**
 * The payload the management portals send.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function warehousePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lahore Depot',
        'code' => 'LHR-1',
        'contact_name' => 'Ali Raza',
        'email' => 'depot@htashop.com',
        'phone' => '+92 300 1234567',
        'address_line_1' => '12 Main Boulevard',
        'address_line_2' => 'Gulberg III',
        'city' => 'Lahore',
        'state' => 'Punjab',
        'postal_code' => '54000',
        'country' => 'Pakistan',
        'is_active' => true,
    ], $overrides);
}

test('a warehouse saves its contact and address details and reads them back flat', function () {
    $response = $this->postJson('/api/v1/warehouses', warehousePayload());

    $response->assertCreated()
        ->assertJsonPath('data.contact_name', 'Ali Raza')
        ->assertJsonPath('data.email', 'depot@htashop.com')
        ->assertJsonPath('data.city', 'Lahore')
        ->assertJsonPath('data.state', 'Punjab')
        ->assertJsonPath('data.postal_code', '54000')
        ->assertJsonPath('data.country', 'Pakistan')
        // The same values are still exposed on the nested address.
        ->assertJsonPath('data.address.city', 'Lahore')
        ->assertJsonPath('data.address.contact_name', 'Ali Raza');

    $warehouse = Warehouse::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($warehouse->country)->toBe('Pakistan')
        // Mirrored onto the warehouse row as well as the address row.
        ->and($warehouse->city)->toBe('Lahore')
        ->and($warehouse->contact_name)->toBe('Ali Raza')
        ->and($warehouse->address)->not->toBeNull()
        ->and($warehouse->address->city)->toBe('Lahore')
        ->and($warehouse->address->contact_name)->toBe('Ali Raza');
});

test('editing a warehouse updates the address row and the flat fields follow', function () {
    $created = $this->postJson('/api/v1/warehouses', warehousePayload())->json('data.uuid');

    $this->putJson("/api/v1/warehouses/{$created}", [
        'name' => 'Lahore Depot (Main)',
        'contact_name' => 'Sara Khan',
        'city' => 'Islamabad',
        'country' => 'Pakistan',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Lahore Depot (Main)')
        ->assertJsonPath('data.contact_name', 'Sara Khan')
        ->assertJsonPath('data.city', 'Islamabad')
        ->assertJsonPath('data.address.city', 'Islamabad');

    $this->getJson("/api/v1/warehouses/{$created}")
        ->assertOk()
        ->assertJsonPath('data.contact_name', 'Sara Khan')
        ->assertJsonPath('data.city', 'Islamabad');

    $warehouse = Warehouse::query()->where('uuid', $created)->firstOrFail();

    expect($warehouse->city)->toBe('Islamabad')
        ->and($warehouse->contact_name)->toBe('Sara Khan')
        ->and($warehouse->address()->first()?->city)->toBe('Islamabad')
        ->and($warehouse->address()->first()?->contact_name)->toBe('Sara Khan');
});

test('the address row wins over stale legacy columns', function () {
    $warehouse = Warehouse::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Legacy Depot',
        'slug' => 'legacy-depot',
        'city' => 'Old City',
        'is_active' => true,
    ]);

    $warehouse->address()->create([
        'type' => 'warehouse',
        'is_primary' => true,
        'city' => 'New City',
    ]);

    $this->getJson("/api/v1/warehouses/{$warehouse->uuid}")
        ->assertOk()
        ->assertJsonPath('data.city', 'New City')
        ->assertJsonPath('data.address.city', 'New City');
});

test('the warehouse list exposes the saved address so cards are not blank', function () {
    $this->postJson('/api/v1/warehouses', warehousePayload())->assertCreated();

    $this->getJson('/api/v1/warehouses')
        ->assertOk()
        ->assertJsonPath('data.0.city', 'Lahore')
        ->assertJsonPath('data.0.contact_name', 'Ali Raza')
        ->assertJsonPath('data.0.country', 'Pakistan');
});
