<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Organization;
use App\Models\Subscribe;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    Role::findOrCreate('super-admin', 'api');
    $this->withoutMiddleware(ApiAuthenticate::class);
});

it('returns global newsletter subscriber counts for super admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    Subscribe::query()->create(['type' => 'newsletter', 'email' => 'one@example.com', 'is_subscribed' => true, 'subscribed_at' => now()]);
    Subscribe::query()->create(['type' => 'newsletter', 'email' => 'two@example.com', 'is_subscribed' => true, 'subscribed_at' => now()]);
    Subscribe::query()->create(['type' => 'newsletter', 'email' => 'out@example.com', 'is_subscribed' => false, 'unsubscribed_at' => now()]);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/newsletter/subscribers')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'total' => 3,
                'active' => 2,
                'unsubscribed' => 1,
            ],
        ]);
});

it('scopes newsletter subscriber counts to the admin tenant', function () {
    $tenantA = Tenant::query()->create(['name' => 'A', 'slug' => 'a-' . Str::uuid(), 'domain' => 'a.example.com', 'is_active' => true]);
    $tenantB = Tenant::query()->create(['name' => 'B', 'slug' => 'b-' . Str::uuid(), 'domain' => 'b.example.com', 'is_active' => true]);

    $organization = Organization::query()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'A Org',
        'slug' => 'a-org-' . Str::uuid(),
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->memberships()->create([
        'tenant_id' => $tenantA->id,
        'organization_id' => $organization->id,
        'role' => 'admin',
        'is_primary' => true,
        'is_active' => true,
    ]);

    Subscribe::query()->create(['type' => 'newsletter', 'tenant_id' => $tenantA->id, 'email' => 'a@example.com', 'is_subscribed' => true, 'subscribed_at' => now()]);
    Subscribe::query()->create(['type' => 'newsletter', 'tenant_id' => $tenantA->id, 'email' => 'a2@example.com', 'is_subscribed' => false, 'unsubscribed_at' => now()]);
    Subscribe::query()->create(['type' => 'newsletter', 'tenant_id' => $tenantB->id, 'email' => 'b@example.com', 'is_subscribed' => true, 'subscribed_at' => now()]);

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/newsletter/subscribers')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'total' => 2,
                'active' => 1,
                'unsubscribed' => 1,
            ],
        ]);
});
