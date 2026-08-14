<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;


it('can be created using a factory', function () {
    $auditLog = AuditLog::factory()->create([
        'event' => 'updated',
        'description' => 'User updated profile',
    ]);

    expect($auditLog)->toBeInstanceOf(AuditLog::class);
    expect($auditLog->event)->toBe('updated');
    expect($auditLog->description)->toBe('User updated profile');
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $auditLog = AuditLog::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($auditLog->user)->toBeInstanceOf(User::class);
    expect($auditLog->user->id)->toBe($user->id);
    expect($auditLog->causer->id)->toBe($user->id); // causer alias check
});

it('belongs to an auditable entity via morph relation', function () {
    $user = User::factory()->create();
    $auditLog = AuditLog::factory()->create([
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
    ]);

    expect($auditLog->auditable)->toBeInstanceOf(User::class);
    expect($auditLog->auditable->id)->toBe($user->id);
});

it('can filter by event scope', function () {
    AuditLog::factory()->create(['event' => 'created']);
    AuditLog::factory()->create(['event' => 'deleted']);

    expect(AuditLog::event('created')->count())->toBe(1);
    expect(AuditLog::event('deleted')->count())->toBe(1);
});

it('can filter by user scope', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    AuditLog::factory()->create(['user_id' => $user1->id]);
    AuditLog::factory()->create(['user_id' => $user2->id]);

    expect(AuditLog::byUser($user1->id)->count())->toBe(1);
});

it('can filter by auditable type scope', function () {
    AuditLog::factory()->create(['auditable_type' => User::class]);

    expect(AuditLog::forModel(User::class)->count())->toBe(1);
});

it('calculates diff attribute of old and new values', function () {
    $auditLog = AuditLog::factory()->make([
        'old_values' => ['name' => 'Alice', 'role' => 'editor'],
        'new_values' => ['name' => 'Bob', 'role' => 'editor'],
    ]);

    $changes = $auditLog->changes;

    expect($changes)->toHaveKey('name');
    expect($changes['name']['old'])->toBe('Alice');
    expect($changes['name']['new'])->toBe('Bob');
    expect($changes)->not->toHaveKey('role');
});

it('returns human readable event description attribute', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    
    $auditLogCreated = AuditLog::factory()->create([
        'user_id' => $user->id,
        'event' => 'created',
        'auditable_type_name' => 'Profile',
    ]);

    $auditLogUpdated = AuditLog::factory()->create([
        'user_id' => $user->id,
        'event' => 'updated',
        'auditable_type_name' => 'Profile',
    ]);

    $auditLogDeleted = AuditLog::factory()->create([
        'user_id' => $user->id,
        'event' => 'deleted',
        'auditable_type_name' => 'Profile',
    ]);

    $auditLogRestored = AuditLog::factory()->create([
        'user_id' => $user->id,
        'event' => 'restored',
        'auditable_type_name' => 'Profile',
    ]);

    $auditLogCustom = AuditLog::factory()->create([
        'user_id' => $user->id,
        'event' => 'approved',
        'auditable_type_name' => 'License',
    ]);

    expect($auditLogCreated->event_description)->toBe('John Doe created Profile');
    expect($auditLogUpdated->event_description)->toBe('John Doe updated Profile');
    expect($auditLogDeleted->event_description)->toBe('John Doe deleted Profile');
    expect($auditLogRestored->event_description)->toBe('John Doe restored Profile');
    expect($auditLogCustom->event_description)->toBe('John Doe performed approved on License');
});
