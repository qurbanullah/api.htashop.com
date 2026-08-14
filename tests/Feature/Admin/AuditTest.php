<?php

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

it('records an audit when admin resets password', function () {
    Role::findOrCreate('admin', 'api');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();

    $this->actingAs($admin, 'api');

    $response = $this->postJson('/api/v1/users/' . $target->id . '/reset-password', [
        'password' => 'adminReset1',
        'password_confirmation' => 'adminReset1',
    ]);

    $response->assertStatus(200)->assertJson(['success' => true]);

    $audit = Audit::where('event', 'user.password.reset')->latest()->first();

    expect($audit)->not->toBeNull();
    expect($audit->actor_user_id)->toBe($admin->id);
    expect($audit->target_id)->toBe($target->id);
});
