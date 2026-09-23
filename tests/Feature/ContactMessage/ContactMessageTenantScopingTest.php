<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\ContactMessage;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    Role::findOrCreate('super-admin', 'api');
    $this->withoutMiddleware(ApiAuthenticate::class);
    Cache::flush();
});

/**
 * Create a tenant + organization and bind the user as its primary admin.
 */
function makeScopedAdmin(string $tenantName): array
{
    $tenant = Tenant::query()->create([
        'name' => $tenantName,
        'slug' => Str::slug($tenantName) . '-' . Str::uuid(),
        'domain' => Str::slug($tenantName) . '.example.com',
        'is_active' => true,
    ]);

    $organization = Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => $tenantName . ' Org',
        'slug' => Str::slug($tenantName) . '-org-' . Str::uuid(),
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $admin->memberships()->create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'role' => 'admin',
        'is_primary' => true,
        'is_active' => true,
    ]);

    return [$admin, $tenant];
}

it('attaches a public contact message to the tenant from the domain header', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Shop Alpha',
        'slug' => 'shop-alpha',
        'domain' => 'shop-alpha.example.com',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/contact', [
        'first_name' => 'Jane',
        'last_name' => 'Buyer',
        'email' => 'jane@example.com',
        'phone' => '+1 555 0100',
        'subject' => 'general',
        'message' => 'I need help locating a product on the store.',
        'consent' => true,
    ], ['X-Tenant-Domain' => 'shop-alpha.example.com']);

    $response->assertCreated()->assertJson([
        'success' => true,
        'data' => [
            'status' => 'new',
        ],
    ]);

    expect($response->json('data.uuid'))->not->toBeNull();

    $message = ContactMessage::query()->latest('id')->first();

    expect($message)->not->toBeNull();
    expect($message->tenant_id)->toBe($tenant->id);
    expect($message->uuid)->toBe($response->json('data.uuid'));
});

it('falls back to the first active tenant for public submissions without headers', function () {
    $tenant = Tenant::query()->create([
        'name' => 'Default Market',
        'slug' => 'default-market',
        'domain' => 'default-market.example.com',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/contact', [
        'first_name' => 'Grace',
        'last_name' => 'Guest',
        'email' => 'grace@example.com',
        'subject' => 'support',
        'message' => 'Please help me track my order, thank you.',
        'consent' => true,
    ]);

    $response->assertCreated();

    expect(ContactMessage::query()->latest('id')->first()->tenant_id)->toBe($tenant->id);
});

it('scopes admin list and detail access to the admin tenant', function () {
    [$admin, $tenant] = makeScopedAdmin('Scoped Store');
    [, $otherTenant] = makeScopedAdmin('Other Store');

    $ownMessage = ContactMessage::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Own Visitor',
        'email' => 'own@example.com',
        'subject' => 'General Inquiry',
        'message' => 'Question about my own tenant.',
        'status' => 'new',
    ]);

    $foreignMessage = ContactMessage::query()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Foreign Visitor',
        'email' => 'foreign@example.com',
        'subject' => 'General Inquiry',
        'message' => 'Question from another tenant.',
        'status' => 'new',
    ]);

    $this->actingAs($admin);

    $list = $this->getJson('/api/v1/admin/contact-messages');
    $list->assertOk();

    $messages = $list->json('data.data');

    expect($messages)->toHaveCount(1);
    expect($messages[0]['id'])->toBe($ownMessage->id);

    $detail = $this->getJson("/api/v1/admin/contact-messages/{$foreignMessage->id}");
    $detail->assertNotFound()->assertJson(['success' => false]);
});
