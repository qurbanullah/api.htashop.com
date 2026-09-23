<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Feedback;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // CACHE_STORE=array persists within the process, so cached tenant lookups
    // and feedback statistics from a previous test could leak into this one.
    // Flush before creating any rows; rows must exist before requests cache.
    Cache::flush();

    Queue::fake();
});

it('attaches public feedback to the tenant resolved from the request header', function () {
    $tenant = Tenant::create([
        'name' => 'HTShop Demo Store',
        'slug' => 'htshop-demo',
        'domain' => 'demo.htashop.com',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/feedback', [
        'type' => 'suggestion',
        'name' => 'Jane Shopper',
        'email' => 'jane-shopper@example.com',
        'subject' => 'Checkout experience',
        'message' => 'Please add a guest checkout option to speed up the purchase flow.',
        'priority' => 'medium',
    ], ['X-Tenant-Domain' => 'demo.htashop.com']);

    $response->assertCreated()->assertJson([
        'success' => true,
        'message' => 'Feedback submitted successfully! We will review your feedback and get back to you if needed.',
    ]);

    $uuid = $response->json('data.uuid');
    expect($uuid)->toBeString()->not->toBeEmpty();

    $feedback = Feedback::where('uuid', $uuid)->first();
    expect($feedback)->not->toBeNull();
    expect($feedback->tenant_id)->toBe($tenant->id);
    expect($feedback->type)->toBe('suggestion');
    expect($feedback->source)->toBe('api');
});

it('exposes feedback publicly by reference uuid', function () {
    $tenant = Tenant::create([
        'name' => 'HTShop Demo Store',
        'slug' => 'htshop-demo',
        'domain' => 'demo.htashop.com',
        'is_active' => true,
    ]);

    $feedback = Feedback::create([
        'tenant_id' => $tenant->id,
        'type' => 'feature_request',
        'name' => 'Bob User',
        'email' => 'bob-user@example.com',
        'subject' => 'Dark mode',
        'message' => 'Please add a dark mode toggle to the account settings page.',
        'status' => 'new',
        'priority' => 'medium',
        'source' => 'api',
    ]);

    $response = $this->getJson("/api/v1/feedback/{$feedback->uuid}");

    $response->assertOk()->assertJson(['success' => true]);

    expect($response->json('data.uuid'))->toBe((string) $feedback->uuid);
    expect($response->json('data.email'))->toBe('bob-user@example.com');
    expect($response->json('data.type_display'))->toBe('Feature Request');
});

it('scopes admin feedback lookup and list to the membership tenant', function () {
    Role::findOrCreate('admin', 'api');
    $this->withoutMiddleware(ApiAuthenticate::class);

    $tenantA = Tenant::create([
        'name' => 'Tenant Alpha',
        'slug' => 'tenant-alpha',
        'domain' => 'alpha.example.com',
        'is_active' => true,
    ]);

    $tenantB = Tenant::create([
        'name' => 'Tenant Beta',
        'slug' => 'tenant-beta',
        'domain' => 'beta.example.com',
        'is_active' => true,
    ]);

    $organizationA = Organization::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Alpha Vendor',
        'slug' => 'alpha-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $organizationB = Organization::create([
        'tenant_id' => $tenantB->id,
        'name' => 'Beta Vendor',
        'slug' => 'beta-vendor',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Membership::create([
        'tenant_id' => $tenantA->id,
        'organization_id' => $organizationA->id,
        'user_id' => $admin->id,
        'role' => 'owner',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $feedbackA = Feedback::create([
        'tenant_id' => $tenantA->id,
        'type' => 'bug_report',
        'name' => 'Alice Admin',
        'email' => 'alice@alpha.example.com',
        'subject' => 'Broken image links',
        'message' => 'Several product images fail to load on the catalog page.',
        'status' => 'new',
        'priority' => 'high',
        'source' => 'api',
    ]);

    $feedbackB = Feedback::create([
        'tenant_id' => $tenantB->id,
        'type' => 'feedback',
        'name' => 'Beta Shopper',
        'email' => 'beta-shopper@example.com',
        'subject' => 'Beta store feedback',
        'message' => 'The beta store checkout was quick and painless overall.',
        'status' => 'new',
        'priority' => 'low',
        'source' => 'api',
    ]);

    $this->actingAs($admin);

    // Tenant A admin must not be able to open tenant B feedback.
    $this->getJson("/api/v1/admin/feedbacks/{$feedbackB->uuid}")->assertNotFound();

    // Tenant A admin list must not include tenant B rows.
    $response = $this->getJson('/api/v1/admin/feedbacks');
    $response->assertOk()->assertJson(['success' => true]);

    $items = $response->json('data.data');
    expect($items)->toHaveCount(1);
    expect($items[0]['uuid'])->toBe((string) $feedbackA->uuid);
});
