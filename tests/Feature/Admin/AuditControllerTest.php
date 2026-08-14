<?php

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

it('allows admin to list audits', function () {
    Role::findOrCreate('admin', 'api');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // create an audit record
    $audit = Audit::create([
        'event' => 'test.audit',
        'action' => 'test.action',
        'actor_user_id' => $admin->id,
        'auditable_type' => User::class,
        'auditable_id' => $admin->id,
    ]);

    $this->actingAs($admin, 'api');

    $response = $this->getJson('/api/v1/admin/audits');

    $response->assertStatus(200);
});

it('prevents non-admin from listing audits', function () {
    Role::findOrCreate('admin', 'api');

    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    $response = $this->getJson('/api/v1/admin/audits');

    $response->assertStatus(403);
});
