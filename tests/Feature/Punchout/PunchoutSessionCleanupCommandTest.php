<?php

use App\Models\PunchoutSession;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

it('expires open timed out sessions and prunes old terminal sessions', function () {
    $tenant = Tenant::create([
        'name' => 'Cleanup Tenant',
        'slug' => 'cleanup-tenant',
        'is_active' => true,
    ]);

    $expirableSession = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'cxml',
        'status' => 'started',
        'buyer_cookie' => 'expire-me',
        'return_url' => 'https://buyer.example/return',
        'expires_at' => now()->subHour(),
        'started_at' => now()->subHours(2),
        'last_activity_at' => now()->subHours(2),
    ]);

    $oldCompletedSession = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'oci',
        'status' => 'completed',
        'buyer_cookie' => 'old-completed',
        'return_url' => 'https://buyer.example/return',
        'completed_at' => now()->subDays(10),
        'last_activity_at' => now()->subDays(10),
    ]);

    $oldExpiredSession = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'cxml',
        'status' => 'expired',
        'buyer_cookie' => 'old-expired',
        'return_url' => 'https://buyer.example/return',
        'expires_at' => now()->subDays(10),
        'last_activity_at' => now()->subDays(10),
    ]);

    $recentCompletedSession = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'oci',
        'status' => 'completed',
        'buyer_cookie' => 'recent-completed',
        'return_url' => 'https://buyer.example/return',
        'completed_at' => now()->subDay(),
        'last_activity_at' => now()->subDay(),
    ]);

    $this->artisan('punchout:sessions:cleanup', ['--retention-days' => 7])
        ->expectsOutput('Punchout session cleanup completed.')
        ->assertExitCode(0);

    expect($expirableSession->fresh()?->status)->toBe('expired');
    expect(PunchoutSession::query()->whereKey($oldCompletedSession->id)->exists())->toBeFalse();
    expect(PunchoutSession::query()->whereKey($oldExpiredSession->id)->exists())->toBeFalse();
    expect(PunchoutSession::query()->whereKey($recentCompletedSession->id)->exists())->toBeTrue();
});

it('supports dry run cleanup without mutating sessions', function () {
    $tenant = Tenant::create([
        'name' => 'Dry Run Cleanup Tenant',
        'slug' => 'dry-run-cleanup-tenant',
        'is_active' => true,
    ]);

    $expirableSession = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'cxml',
        'status' => 'started',
        'buyer_cookie' => 'expire-me',
        'return_url' => 'https://buyer.example/return',
        'expires_at' => now()->subHour(),
        'started_at' => now()->subHours(2),
        'last_activity_at' => now()->subHours(2),
    ]);

    $oldCompletedSession = PunchoutSession::create([
        'tenant_id' => $tenant->id,
        'protocol' => 'oci',
        'status' => 'completed',
        'buyer_cookie' => 'old-completed',
        'return_url' => 'https://buyer.example/return',
        'completed_at' => now()->subDays(10),
        'last_activity_at' => now()->subDays(10),
    ]);

    $this->artisan('punchout:sessions:cleanup', ['--retention-days' => 7, '--dry-run' => true])
        ->expectsOutput('Punchout session cleanup completed.')
        ->assertExitCode(0);

    expect($expirableSession->fresh()?->status)->toBe('started');
    expect(PunchoutSession::query()->whereKey($oldCompletedSession->id)->exists())->toBeTrue();
});
