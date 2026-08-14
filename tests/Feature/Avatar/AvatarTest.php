<?php

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

it('can be created using a factory', function () {
    $avatar = Avatar::factory()->create([
        'type' => 'thumb',
        'width' => 150,
        'height' => 150,
    ]);

    expect($avatar)->toBeInstanceOf(Avatar::class);
    expect($avatar->type)->toBe('thumb');
    expect($avatar->width)->toBe(150);
});

it('belongs to an avatareable entity via morph relation', function () {
    $user = User::factory()->create();
    $avatar = Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
    ]);

    expect($avatar->avatareable)->toBeInstanceOf(User::class);
    expect($avatar->avatareable->id)->toBe($user->id);
});

it('retrieves avatar by type', function () {
    $user = User::factory()->create();
    $avatar = Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'medium',
    ]);

    $retrieved = Avatar::getByType($user->id, User::class, 'medium');

    expect($retrieved)->not->toBeNull();
    expect($retrieved->id)->toBe($avatar->id);
});

it('retrieves all avatar paths', function () {
    $user = User::factory()->create();
    Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'thumb',
        'path' => 'avatars/thumb.jpg',
    ]);
    Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'medium',
        'path' => 'avatars/medium.jpg',
    ]);

    $paths = Avatar::getAllPaths($user->id, User::class);

    expect($paths)->toHaveKey('thumb', 'avatars/thumb.jpg');
    expect($paths)->toHaveKey('medium', 'avatars/medium.jpg');
});

it('deletes all avatars for an avatareable entity', function () {
    Storage::fake('idrivee2');
    Storage::disk('idrivee2')->put('avatars/thumb.jpg', 'fake content');

    $user = User::factory()->create();
    Avatar::factory()->create([
        'avatareable_type' => User::class,
        'avatareable_id' => $user->id,
        'type' => 'thumb',
        'path' => 'avatars/thumb.jpg',
    ]);

    expect(Storage::disk('idrivee2')->exists('avatars/thumb.jpg'))->toBeTrue();

    $result = Avatar::deleteAllForAvatareable($user->id, User::class);

    expect($result)->toBeTrue();
    expect(Storage::disk('idrivee2')->exists('avatars/thumb.jpg'))->toBeFalse();
    expect(Avatar::where('avatareable_id', $user->id)->count())->toBe(0);
});

it('creates avatars from uploaded files paths', function () {
    Storage::fake('idrivee2');

    $user = User::factory()->create();
    
    $variantPaths = [
        'thumb' => 'avatars/new_thumb.jpg',
        'medium' => 'avatars/new_medium.jpg',
    ];

    $result = Avatar::createFromUpload($user, $variantPaths, 'new_avatar.jpg', 'image/jpeg', ['source' => 'test']);

    expect($result)->toBeTrue();
    expect($user->avatars()->count())->toBe(2);
    expect($user->avatars()->where('type', 'thumb')->first()->path)->toBe('avatars/new_thumb.jpg');
});
