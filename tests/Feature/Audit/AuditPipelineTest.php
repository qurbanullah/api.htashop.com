<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\Audit;
use App\Models\AuditExport;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('writes one canonical audit row with a hashed ip and no raw address', function (): void {
    Role::findOrCreate('admin', 'api');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $target = User::factory()->create();

    $this->actingAs($admin, 'api')
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->postJson('/api/v1/users/'.$target->id.'/reset-password', [
            'password' => 'adminReset1',
            'password_confirmation' => 'adminReset1',
        ])
        ->assertOk();

    $audit = Audit::query()->where('event', 'user.password.reset')->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->uuid)->not->toBeEmpty()
        ->and($audit->request_id)->not->toBeEmpty()
        ->and($audit->ip_hash)->not->toBeEmpty()
        ->and($audit->ip_address)->toBeNull()
        ->and($audit->actor_type)->toBe(User::class)
        ->and($audit->actor_id)->toBe($admin->id);
});

it('redacts secrets from before/after snapshots', function (): void {
    $user = User::factory()->create();

    $audit = app(AuditLogger::class)->record($user, 'user.updated', $user, [
        'old_values' => ['name' => 'Old', 'password' => 'secret-old'],
        'new_values' => ['name' => 'New', 'password' => 'secret-new', 'api_key' => 'sk-live'],
    ]);

    expect($audit->old_values['password'])->toBe('[redacted]')
        ->and($audit->new_values['password'])->toBe('[redacted]')
        ->and($audit->new_values['api_key'])->toBe('[redacted]')
        ->and($audit->new_values['name'])->toBe('New');
});

it('exports an idempotent ndjson day with a verifiable manifest', function (): void {
    Storage::fake('observability');

    $date = Carbon::yesterday('UTC')->startOfDay();

    $audit = Audit::query()->create([
        'event' => 'order.refunded',
        'auditable_type' => 'App\\Models\\Order',
        'auditable_id' => 42,
        'actor_type' => User::class,
        'actor_id' => 7,
        'changed_columns' => ['status'],
        'reason' => 'customer request',
        'request_id' => 'req_test_123',
        'ip_hash' => hash('sha256', '203.0.113.9'),
        'occurred_at' => $date->copy()->addHour(),
    ]);

    $this->artisan('audit:export', ['--date' => $date->toDateString()])->assertExitCode(0);
    $this->artisan('audit:export', ['--date' => $date->toDateString()])->assertExitCode(0);

    $export = AuditExport::query()->where('export_date', $date)->firstOrFail();
    expect($export->status)->toBe('completed')
        ->and($export->rows)->toBe(1)
        ->and(AuditExport::query()->count())->toBe(1);

    $disk = Storage::disk('observability');
    expect($disk->exists($export->path))->toBeTrue()
        ->and($disk->exists($export->manifest_path))->toBeTrue();

    $ndjson = $disk->get($export->path);
    $lines = array_values(array_filter(explode("\n", trim($ndjson))));

    expect($lines)->toHaveCount(1);

    $payload = json_decode($lines[0], true);
    expect($payload['uuid'])->toBe($audit->uuid)
        ->and($payload['event'])->toBe('order.refunded')
        ->and($payload['ip_hash'])->toBe($audit->ip_hash);

    // Raw IP and secrets must never be exported.
    expect($ndjson)->not->toContain('203.0.113.9')
        ->and($ndjson)->not->toContain('password');

    $this->artisan('audit:verify', ['--date' => $date->toDateString()])->assertExitCode(0);
});

it('refuses to prune a day that has no completed export', function (): void {
    $old = Carbon::now('UTC')->subDays(120)->startOfDay();

    $audit = Audit::query()->create([
        'event' => 'legacy.event',
        'actor_type' => 'system',
        'occurred_at' => $old->copy()->addHour(),
    ]);

    $this->artisan('audit:prune', ['--days' => 30, '--dry-run' => true])->assertExitCode(0);
    expect(Audit::query()->whereKey($audit->id)->exists())->toBeTrue();

    $this->artisan('audit:prune', ['--days' => 30])->assertExitCode(0);
    expect(Audit::query()->whereKey($audit->id)->exists())->toBeTrue();
});

it('prunes only a day that has a completed export', function (): void {
    Storage::fake('observability');

    $old = Carbon::now('UTC')->subDays(120)->startOfDay();

    $audit = Audit::query()->create([
        'event' => 'legacy.event',
        'actor_type' => 'system',
        'occurred_at' => $old->copy()->addHour(),
    ]);

    $this->artisan('audit:export', ['--date' => $old->toDateString()])->assertExitCode(0);
    $this->artisan('audit:prune', ['--days' => 30])->assertExitCode(0);

    expect(Audit::query()->whereKey($audit->id)->exists())->toBeFalse();
});

it('verifies the manifest chain and detects a tampered ndjson file', function (): void {
    Storage::fake('observability');

    $dayOne = Carbon::now('UTC')->subDays(3)->startOfDay();
    $dayTwo = $dayOne->copy()->addDay();

    Audit::query()->create([
        'event' => 'day.one',
        'actor_type' => 'system',
        'occurred_at' => $dayOne->copy()->addHour(),
    ]);

    Audit::query()->create([
        'event' => 'day.two',
        'actor_type' => 'system',
        'occurred_at' => $dayTwo->copy()->addHour(),
    ]);

    $this->artisan('audit:export', ['--date' => $dayOne->toDateString()])->assertExitCode(0);
    $this->artisan('audit:export', ['--date' => $dayTwo->toDateString()])->assertExitCode(0);
    $this->artisan('audit:verify')->assertExitCode(0);

    // Re-exporting an earlier day must not break the chain when the data is
    // unchanged; the manifest hash excludes volatile fields.
    $this->artisan('audit:export', ['--date' => $dayOne->toDateString(), '--force' => true])->assertExitCode(0);
    $this->artisan('audit:verify')->assertExitCode(0);

    $export = AuditExport::query()->where('export_date', $dayTwo)->firstOrFail();
    Storage::disk('observability')->put($export->path, "tampered\n");

    $this->artisan('audit:verify', ['--date' => $dayTwo->toDateString()])->assertExitCode(1);
});
