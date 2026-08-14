<?php

use App\Services\Avatars\AvatarService;
use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->avatarService = new AvatarService();
});

it('can get user avatar url with caching', function () {
    Cache::spy();

    $user = User::factory()->create();
    
    // Stub the model's getAvatarUrl method by creating an avatar database record
    Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'medium',
        'path' => 'avatars/medium.jpg',
    ]);

    $url = $this->avatarService->getUserAvatarUrl($user, 'medium');

    expect($url)->not->toBeNull();
    Cache::shouldHaveReceived('remember')->once();
});

it('can get user avatar urls mapping with caching', function () {
    Cache::spy();

    $user = User::factory()->create();
    Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'thumb',
        'path' => 'avatars/thumb.jpg',
    ]);

    $urls = $this->avatarService->getUserAvatarUrls($user);

    expect($urls)->toHaveKey('thumb');
    Cache::shouldHaveReceived('remember')->once();
});

it('creates avatar from upload and clears cache', function () {
    Cache::spy();
    Storage::fake('idrivee2');

    $user = User::factory()->create();
    $variantPaths = [
        'thumb' => 'avatars/thumb.jpg',
        'medium' => 'avatars/medium.jpg',
    ];

    $result = $this->avatarService->createAvatarFromUpload($user, $variantPaths, 'avatar.jpg');

    expect($result)->toBeTrue();
    expect($user->avatars()->count())->toBe(2);

    // Verifies cache clear (forget was called for each type and the urls map)
    Cache::shouldHaveReceived('forget')->atLeast()->times(6);
});

it('deletes user avatar and clears cache', function () {
    Cache::spy();
    Storage::fake('idrivee2');

    $user = User::factory()->create();
    Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'medium',
        'path' => 'avatars/medium.jpg',
    ]);

    // Force load relationship
    $user->load('avatars');

    $result = $this->avatarService->deleteAvatar($user);

    expect($result)->toBeTrue();
    expect($user->fresh()->avatars()->count())->toBe(0);
    Cache::shouldHaveReceived('forget')->atLeast()->times(6);
});

it('optimizes avatar metadata by checking file size and dimensions', function () {
    Storage::fake('idrivee2');

    // Create a 1x1 pixel tiny transparent PNG content to mock image dimensions
    $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    Storage::disk('idrivee2')->put('avatars/medium.jpg', $tinyPng);

    $user = User::factory()->create();
    $avatar = Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'medium',
        'path' => 'avatars/medium.jpg',
        'file_size' => 0,
        'width' => 0,
        'height' => 0,
    ]);

    // Force load relationship
    $user->load('avatars');

    $result = $this->avatarService->optimizeAvatarIfNeeded($user);

    expect($result)->toBeTrue();
    
    $freshAvatar = $avatar->fresh();
    expect($freshAvatar->file_size)->toBeGreaterThan(0);
    expect($freshAvatar->width)->toBe(1);
    expect($freshAvatar->height)->toBe(1);
});
