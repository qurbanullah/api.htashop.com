<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * The admin portal shares `/login` with the storefront and the management
 * portal, so it identifies itself with `X-Client: admin`. Only the super-admin
 * and admin roles (roles.id 1 and 2) may use it; a vendor is a valid user
 * everywhere else but must be refused here, before a session is issued.
 */
beforeEach(function (): void {
    ensurePersonalAccessClient();

    Role::findOrCreate('super-admin', 'api');
    Role::findOrCreate('admin', 'api');
    Role::findOrCreate('vendor', 'api');
});

it('refuses a vendor signing in to the admin panel', function (): void {
    $vendor = User::factory()->create();
    $vendor->assignRole('vendor');

    $this->withHeader('X-Client', 'admin')
        ->postJson('/api/v1/login', ['email' => $vendor->email, 'password' => 'password'])
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.code', 'admin_access_required')
        ->assertJsonMissingPath('data.access_token');

    // No session was started, so there is no refresh token to exchange either.
    expect(RefreshToken::query()->count())->toBe(0);
});

it('lets an administrator sign in to the admin panel', function (string $role): void {
    $admin = User::factory()->create();
    $admin->assignRole($role);

    $response = $this->withHeader('X-Client', 'admin')
        ->postJson('/api/v1/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.user.can_access_admin', true);

    expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty();
})->with(['super-admin', 'admin']);

it('leaves sign-in for the other portals unchanged', function (): void {
    $vendor = User::factory()->create();
    $vendor->assignRole('vendor');

    $this->withHeader('X-Client', 'manage')
        ->postJson('/api/v1/login', ['email' => $vendor->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.user.can_access_admin', false);
});

it('refuses an admin-panel session bootstrap for a non-admin token', function (): void {
    $vendor = User::factory()->create();
    $vendor->assignRole('vendor');

    $token = signInDevice($vendor->email)['access_token'];

    $this->withToken($token)
        ->withHeader('X-Client', 'admin')
        ->getJson('/api/v1/user')
        ->assertForbidden()
        ->assertJsonPath('data.code', 'admin_access_required');
});

it('lets an administrator bootstrap an admin-panel session', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $token = signInDevice($admin->email)['access_token'];

    $this->withToken($token)
        ->withHeader('X-Client', 'admin')
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.can_access_admin', true);
});

it('still lets a demoted administrator read their own profile outside the admin panel', function (): void {
    $vendor = User::factory()->create();
    $vendor->assignRole('vendor');

    $token = signInDevice($vendor->email)['access_token'];

    $this->withToken($token)
        ->withHeader('X-Client', 'manage')
        ->getJson('/api/v1/user')
        ->assertOk();
});
