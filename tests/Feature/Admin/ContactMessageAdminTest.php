<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    $this->withoutMiddleware(ApiAuthenticate::class);
});

it('allows admins to list and filter contact messages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    ContactMessage::query()->create([
        'name' => 'Alice Contact',
        'email' => 'alice@example.com',
        'subject' => 'General Inquiry',
        'message' => 'Need product information.',
        'status' => 'new',
        'metadata' => ['source_page' => 'https://frontend.volvicon.com/about/contact'],
    ]);

    ContactMessage::query()->create([
        'name' => 'Bob Reader',
        'email' => 'bob@example.com',
        'subject' => 'Partnership',
        'message' => 'Let us discuss a partnership.',
        'status' => 'read',
        'metadata' => ['source_page' => 'https://frontend.volvicon.com/about/contact'],
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
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $message = ContactMessage::query()->create([
        'name' => 'Charlie Contact',
        'email' => 'charlie@example.com',
        'subject' => 'Demo Request',
        'message' => 'Please contact me for a demo.',
        'status' => 'new',
        'metadata' => ['source_page' => 'https://frontend.volvicon.com/about/contact'],
    ]);

    $this->actingAs($admin);

    $response = $this->getJson("/api/v1/admin/contact-messages/{$message->id}");

    $response->assertOk()->assertJson(['success' => true]);
    expect($response->json('data.email'))->toBe('charlie@example.com');

    $message->refresh();

    expect($message->status)->toBe('read');
    expect($message->read_at)->not->toBeNull();
});

it('returns contact message statistics for admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    ContactMessage::query()->create([
        'name' => 'New Message',
        'email' => 'new@example.com',
        'subject' => 'New',
        'message' => 'New message body',
        'status' => 'new',
    ]);

    ContactMessage::query()->create([
        'name' => 'Read Message',
        'email' => 'read@example.com',
        'subject' => 'Read',
        'message' => 'Read message body',
        'status' => 'read',
    ]);

    ContactMessage::query()->create([
        'name' => 'Replied Message',
        'email' => 'replied@example.com',
        'subject' => 'Replied',
        'message' => 'Replied message body',
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
