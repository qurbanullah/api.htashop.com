<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    $this->withoutMiddleware(\App\Http\Middleware\ApiAuthenticate::class);
});

it('allows admins to filter tickets by website submitters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $portalTicket = Ticket::factory()->create([
        'title' => 'Portal ticket',
    ]);

    $websiteTicket = Ticket::factory()->create([
        'user_id' => null,
        'guest_name' => 'Website Guest',
        'guest_email' => 'website-guest@example.com',
        'title' => 'Website ticket',
    ]);

    $this->actingAs($admin);

    $response = $this->getJson('/api/v1/admin/tickets?filters[submitter_type]=website');

    $response->assertOk()->assertJson(['success' => true]);

    $tickets = $response->json('data.data');

    expect($tickets)->toHaveCount(1);
    expect($tickets[0]['id'])->toBe($websiteTicket->id);
    expect($tickets[0]['uuid'])->toBe($websiteTicket->uuid);
    expect($tickets[0]['user_id'])->toBeNull();
});

it('allows admins to filter tickets by portal submitters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $portalTicket = Ticket::factory()->create([
        'title' => 'Portal ticket',
    ]);

    Ticket::factory()->create([
        'user_id' => null,
        'guest_name' => 'Website Guest',
        'guest_email' => 'website-guest@example.com',
        'title' => 'Website ticket',
    ]);

    $this->actingAs($admin);

    $response = $this->getJson('/api/v1/admin/tickets?filters[submitter_type]=portal');

    $response->assertOk()->assertJson(['success' => true]);

    $tickets = $response->json('data.data');

    expect($tickets)->toHaveCount(1);
    expect($tickets[0]['id'])->toBe($portalTicket->id);
    expect($tickets[0]['uuid'])->toBe($portalTicket->uuid);
    expect($tickets[0]['user_id'])->toBe($portalTicket->user_id);
});
