<?php

use App\Models\User;
use App\Models\Avatar;
use App\Jobs\Avatars\OptimizeAvatarJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Setup test authentication guard
    config()->set('auth.guards.api', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
});

it('rejects unauthenticated avatar requests', function () {
    $this->postJson('/api/v1/user/avatar', [])
        ->assertStatus(401);

    $this->deleteJson('/api/v1/user/avatar')
        ->assertStatus(401);
});

it('validates avatar upload request payloads', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    // Missing required fields
    $this->postJson('/api/v1/user/avatar', [
        'filename' => 'avatar.jpg',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    // Missing required variants
    $this->postJson('/api/v1/user/avatar', [
        'avatar_variants' => [
            'original' => 'avatars/original.jpg',
        ],
        'filename' => 'avatar.jpg',
        'mime_type' => 'image/jpeg',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('uploads user avatar successfully, creates records, and dispatches optimize job', function () {
    Queue::fake();
    Storage::fake('idrivee2');

    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    $payload = [
        'avatar_variants' => [
            'original' => 'avatars/original.jpg',
            'thumb' => 'avatars/thumb.jpg',
            'small' => 'avatars/small.jpg',
            'medium' => 'avatars/medium.jpg',
        ],
        'filename' => 'avatar-123.jpg',
        'mime_type' => 'image/jpeg',
    ];

    $response = $this->postJson('/api/v1/user/avatar', $payload);
    
    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'name', 'email']
        ]);

    // Assert records exist in database
    expect(Avatar::where('avatareable_id', $user->id)->count())->toBe(4);
    
    // Assert job was dispatched using assertPushed
    Queue::assertPushed(OptimizeAvatarJob::class, function ($job) use ($user) {
        return $job->userId === $user->id && $job->avatarFilename === 'avatar-123.jpg';
    });
});

it('returns 404 when deleting a non-existent avatar', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    $this->deleteJson('/api/v1/user/avatar')
        ->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No avatar found to delete');
});

it('deletes user avatar successfully and purges storage and database', function () {
    Storage::fake('idrivee2');
    Storage::disk('idrivee2')->put('avatars/medium.jpg', 'content');

    $user = User::factory()->create();
    $this->actingAs($user, 'api');

    Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'medium',
        'path' => 'avatars/medium.jpg',
    ]);

    expect(Storage::disk('idrivee2')->exists('avatars/medium.jpg'))->toBeTrue();

    $response = $this->deleteJson('/api/v1/user/avatar');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Avatar deleted successfully');

    expect(Storage::disk('idrivee2')->exists('avatars/medium.jpg'))->toBeFalse();
    expect(Avatar::where('avatareable_id', $user->id)->count())->toBe(0);
});
