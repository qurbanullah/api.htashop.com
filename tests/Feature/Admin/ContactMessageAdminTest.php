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
    $this->withoutMiddleware(ApiAuthenticate::class);
    Cache::flush();
});

/**
 * Create a tenant + organization and bind the user as its primary admin.
 */
function makeAdminUser(): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Admin Tenant',
        'slug' => 'admin-tenant-' . Str::uuid(),
        'domain' => 'admin-tenant.example.com',
        'is_active' => true,
    ]);

    $organization = Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Admin Org',
        'slug' => 'admin-org-' . Str::uuid(),
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

it('allows admins to list and filter contact messages within their tenant', function () {
    [$admin, $tenant] = makeAdminUser();

    ContactMessage::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Alice Contact',
        'email' => 'alice@example.com',
        'subject' => 'General Inquiry',
        'message' => 'Need product information.',
        'status' => 'new',
        'metadata' => ['source_page' => 'https://htashop.com/contact'],
    ]);

    ContactMessage::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bob Reader',
        'email' => 'bob@example.com',
        'subject' => 'Partnership',
        'message' => 'Let us discuss a partnership.',
        'status' => 'read',
        'metadata' => ['source_page' => 'https://htashop.com/contact'],
    ]);

    $this->actingAs($admin);

    $response = $this->getJson('/api/v1/admin/contact-messages?status=new&search=alice');

    $response->assertOk()->assertJson(['success' => true]);

    $messages = $response->json('data.data');

    expect($messages)->toHaveCount(1);
    expect($messages[0]['email'])->toBe('alice@example.com');
    expect($messages[0]['status'])->toBe('new');
});

it('shows contact message details and marks new messages as read', function () {
    [$admin, $tenant] = makeAdminUser();

    $message = ContactMessage::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Charlie Contact',
        'email' => 'charlie@example.com',
        'subject' => 'Demo Request',
        'message' => 'Please contact me for a demo.',
        'status' => 'new',
        'metadata' => ['source_page' => 'https://htashop.com/contact'],
    ]);

    $this->actingAs($admin);

    $response = $this->getJson("/api/v1/admin/contact-messages/{$message->id}");

    $response->assertOk()->assertJson(['success' => true]);
    expect($response->json('data.email'))->toBe('charlie@example.com');
    expect($response->json('data.uuid'))->toBe($message->uuid);

    $message->refresh();

    expect($message->status)->toBe('read');
    expect($message->read_at)->not->toBeNull();
});

it('returns tenant-scoped contact message statistics for admins', function () {
    [$admin, $tenant] = makeAdminUser();

    ContactMessage::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'New Message',
        'email' => 'new@example.com',
        'subject' => 'New',
        'message' => 'New message body here',
        'status' => 'new',
    ]);

    ContactMessage::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Read Message',
        'email' => 'read@example.com',
        'subject' => 'Read',
        'message' => 'Read message body here',
        'status' => 'read',
    ]);

    ContactMessage::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Replied Message',
        'email' => 'replied@example.com',
        'subject' => 'Replied',
        'message' => 'Replied message body here',
        'status' => 'replied',
    ]);

    $this->actingAs($admin);

    $response = $this->getJson('/api/v1/admin/contact-messages/statistics');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'total' => 3,
            'new' => 1,
            'read' => 1,
            'replied' => 1,
        ],
    ]);
});

it('lets admins reply to a contact message and stores the response', function () {
    [$admin, $tenant] = makeAdminUser();

    $message = ContactMessage::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Reply Me',
        'email' => 'reply@example.com',
        'subject' => 'Support',
        'message' => 'I need help with a recent order.',
        'status' => 'new',
    ]);

    $this->actingAs($admin);

    $response = $this->patchJson("/api/v1/admin/contact-messages/{$message->id}/reply", [
        'reply_message' => 'Thanks for reaching out. We have resolved your issue.',
        'reply_subject' => 'Re: Support',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'message' => 'Reply sent successfully',
    ]);
    expect($response->json('data.status'))->toBe('replied');

    $message->refresh();

    expect($message->status)->toBe('replied');
    expect($message->admin_response)->toBe('Thanks for reaching out. We have resolved your issue.');
    expect($message->replied_by)->toBe($admin->id);
    expect($message->replied_at)->not->toBeNull();
});

it('cannot see contact messages from another tenant', function () {
    [$admin, $tenant] = makeAdminUser();

    $otherTenant = Tenant::query()->create([
        'name' => 'Other Tenant',
        'slug' => 'other-tenant-' . Str::uuid(),
        'domain' => 'other-tenant.example.com',
        'is_active' => true,
    ]);

    $foreignMessage = ContactMessage::query()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Foreign',
        'email' => 'foreign@example.com',
        'subject' => 'Private',
        'message' => 'This belongs to another tenant.',
        'status' => 'new',
    ]);

    $this->actingAs($admin);

    $response = $this->getJson("/api/v1/admin/contact-messages/{$foreignMessage->id}");

    $response->assertNotFound()->assertJson(['success' => false]);
});
