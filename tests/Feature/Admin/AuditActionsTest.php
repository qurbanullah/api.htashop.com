<?php

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

it('records an audit when roles are assigned', function () {
    Role::findOrCreate('admin', 'api');
    Role::findOrCreate('editor', 'api');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();

    $this->actingAs($admin, 'api');

    $response = $this->postJson('/api/v1/users/' . $target->id . '/roles', [
        'roles' => ['editor']
    ]);

    $response->assertStatus(200)->assertJson(['message' => 'Roles assigned successfully']);

    $audit = Audit::where('event', 'user.assign_roles')->latest()->first();

    expect($audit)->not->toBeNull();
    expect($audit->actor_user_id)->toBe($admin->id);
    expect($audit->target_id)->toBe($target->id);
});

it('records an audit when a user is updated', function () {
    Role::findOrCreate('admin', 'api');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin, 'api');

    $response = $this->putJson('/api/v1/users/' . $target->id, [
        'name' => 'New Name'
    ]);

    $response->assertStatus(200)->assertJson(['message' => 'User updated successfully']);

    $audit = Audit::where('event', 'user.updated')->latest()->first();

    expect($audit)->not->toBeNull();
    expect($audit->actor_user_id)->toBe($admin->id);
    expect($audit->target_id)->toBe($target->id);
    expect($audit->new_values['name'])->toBe('New Name');
});
