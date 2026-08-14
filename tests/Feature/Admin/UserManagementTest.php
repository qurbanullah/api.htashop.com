<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

it('allows admin to reset another user password', function () {
    // Create admin role and assign to admin user
    Role::findOrCreate('admin', 'api');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create(['password' => bcrypt('oldpassword')]);

    $this->actingAs($admin, 'api');

    $response = $this->postJson('/api/v1/users/' . $target->id . '/reset-password', [
        'password' => 'newPass123',
        'password_confirmation' => 'newPass123',
    ]);

    $response->assertStatus(200)->assertJson(['success' => true]);

    $this->assertTrue(Hash::check('newPass123', $target->fresh()->password));
});

it('prevents non-admin from resetting another user password', function () {
    $user = User::factory()->create();
    $target = User::factory()->create(['password' => bcrypt('oldpassword')]);

    $this->actingAs($user, 'api');

    $response = $this->postJson('/api/v1/users/' . $target->id . '/reset-password', [
        'password' => 'newPass123',
        'password_confirmation' => 'newPass123',
    ]);

    $response->assertStatus(403);

    $this->assertTrue(Hash::check('oldpassword', $target->fresh()->password));
});

it('allows admin to mark user email verified', function () {
    Role::findOrCreate('admin', 'api');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->unverified()->create();

    $this->actingAs($admin, 'api');

    $response = $this->postJson('/api/v1/users/' . $target->id . '/verify');

    $response->assertStatus(200)->assertJson(['success' => true]);

    expect($target->fresh()->email_verified_at)->not->toBeNull();
});

it('prevents non-admin from verifying user email', function () {
    $user = User::factory()->create();
    $target = User::factory()->unverified()->create();

    $this->actingAs($user, 'api');

    $response = $this->postJson('/api/v1/users/' . $target->id . '/verify');

    $response->assertStatus(403);

    expect($target->fresh()->email_verified_at)->toBeNull();
});
