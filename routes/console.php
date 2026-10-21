<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send posts that are due (status = scheduled, scheduled_at <= now).
Schedule::command('post:send-scheduled')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

// Regenerate the storefront sitemaps daily so crawler requests are always served
// from a warm cache (robots.txt + pages, products, posts and categories).
Schedule::command('sitemap:generate')
    ->dailyAt('02:30')
    ->withoutOverlapping(30)
    ->onOneServer();

// Refresh draft knowledge entries from published content (posts, tutorials).
// Entries are created as drafts and must be published in the admin portal.
Schedule::command('knowledge:ingest')
    ->dailyAt('03:15')
    ->withoutOverlapping(60)
    ->onOneServer();

// Drop refresh tokens that expired past their retention window. Rows past their
// absolute expiry can no longer be exchanged, and pruning keeps the table (and
// the reuse-detection lookups) small.
Schedule::command('auth:prune-refresh-tokens')
    ->dailyAt('03:45')
    ->withoutOverlapping(30)
    ->onOneServer();

// Audit evidence: export the previous UTC day to object storage, verify the
// hash chain, then prune only days that have a completed export. Export MUST
// run before prune.
Schedule::command('audit:export')
    ->dailyAt('01:30')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('audit:verify')
    ->dailyAt('02:00')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('audit:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping(30)
    ->onOneServer();

// Product analytics: Redis counters become daily aggregates. The lock inside
// the command plus onOneServer/withoutOverlapping keeps this single-run.
Schedule::command('analytics:flush')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->onOneServer();

// Data-quality check: purchase events vs confirmed orders. A failure is a
// warning signal, not a customer-facing outage.
Schedule::command('analytics:reconcile')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();
